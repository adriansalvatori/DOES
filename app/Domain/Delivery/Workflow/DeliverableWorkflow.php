<?php

namespace App\Domain\Delivery\Workflow;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Events\ApprovalReset;
use App\Domain\Delivery\Events\DeliverableMoved;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\BlockOwnerResolver;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\ProductionSamples;
use App\Domain\Delivery\Support\Roles;
use App\Domain\Scheduling\SlaPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one door every status change goes through: matrix, guards, exit rules and side effects.
 *
 * Recognized context keys: `batch` (sent with its batch), `client_approval` (an approval was just recorded),
 * `close_reason` (CloseReason), `note`, `assignee_id`, `planned_for`, `due_target`, `task_id`, `reversal`
 * (a reopened gate task takes the deliverable back), `client_changes`, `reviewer_changes`.
 */
class DeliverableWorkflow
{
    public const RULE_FORWARD = 'forward';

    public const RULE_BACK = 'back';

    public const RULE_MANAGER = 'manager';

    public const RULE_REOPEN = 'reopen';

    public const RULE_CLOSE = 'close';

    public function __construct(
        private SlaPolicy $sla,
        private AlertScheduler $alerts,
        private LegacyProjection $projection,
        private DeliveryLog $log,
        private BlockOwnerResolver $owners,
        private ProductionSamples $samples,
    ) {}

    /**
     * Backward moves anybody may make. Along production every status can go one step back, so a card moved too far
     * by mistake can be put back.
     *
     * @return array<string, list<DeliverableStatus>>
     */
    public static function backwardMoves(): array
    {
        return [
            DeliverableStatus::INTERNAL_REVIEW->value => [DeliverableStatus::IN_DESIGN],
            DeliverableStatus::CLIENT_REVIEW->value => [DeliverableStatus::IN_DESIGN],
            DeliverableStatus::READY_FOR_PRODUCTION->value => [DeliverableStatus::PRODUCTION_FILES],
            DeliverableStatus::PRODUCTION_QUEUE->value => [DeliverableStatus::READY_FOR_PRODUCTION],
            DeliverableStatus::IN_PRODUCTION->value => [DeliverableStatus::PRODUCTION_QUEUE],
            DeliverableStatus::PRODUCED->value => [DeliverableStatus::IN_PRODUCTION],
            DeliverableStatus::FULFILLMENT->value => [DeliverableStatus::PRODUCED],
        ];
    }

    /**
     * The kind of move from one status to another, or null when the matrix does not allow it.
     *
     * @param  array<string, mixed>  $context
     */
    public static function ruleFor(DeliverableStatus $from, DeliverableStatus $to, TransitionReason $reason = TransitionReason::MANUAL, array $context = []): ?string
    {
        if ($from === $to) {
            return null;
        }

        if ($from === DeliverableStatus::DONE) {
            return self::RULE_REOPEN;
        }

        if ($to === DeliverableStatus::DONE) {
            return self::RULE_CLOSE;
        }

        if ($from->next() === $to) {
            return self::RULE_FORWARD;
        }

        if (in_array($to, self::backwardMoves()[$from->value] ?? [], true)) {
            return self::RULE_BACK;
        }

        if ($to === DeliverableStatus::READY && $from->position() > DeliverableStatus::READY->position()) {
            return self::RULE_MANAGER;
        }

        if ($reason === TransitionReason::TASK_COMPLETED && ($context['reversal'] ?? false) && $from->previous() === $to) {
            return self::RULE_BACK;
        }

        return null;
    }

    /**
     * Whether a move passes the matrix for an actor, ignoring the exit rules that need data.
     */
    public function isAllowedByMatrix(DeliverableStatus $from, DeliverableStatus $to, ?User $actor = null): bool
    {
        $rule = self::ruleFor($from, $to);

        if ($rule === null) {
            return false;
        }

        return ! in_array($rule, [self::RULE_MANAGER, self::RULE_REOPEN], true) || $actor === null || Roles::isManager($actor);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(
        Deliverable $deliverable,
        DeliverableStatus $to,
        TransitionReason $reason,
        ?User $actor = null,
        array $context = [],
    ): Deliverable {
        DB::transaction(function () use ($deliverable, $to, $reason, $actor, $context): void {
            $deliverable->refresh();
            $from = $deliverable->status;

            $closeReason = $this->guard($deliverable, $from, $to, $reason, $actor, $context);

            $now = CarbonImmutable::now();
            $dueSoonBefore = $deliverable->due_target !== null
                && CarbonImmutable::instance($deliverable->due_target)->startOfDay()->lte($now->startOfDay());
            $dueBefore = $deliverable->due_target;

            $changes = ['status' => $to];
            $changes += $this->areaChanges($deliverable, $from, $to, $now, $closeReason, $context);

            $approvalsReset = 0;
            $returnsToDesign = $to === DeliverableStatus::IN_DESIGN
                && in_array($from, [DeliverableStatus::INTERNAL_REVIEW, DeliverableStatus::CLIENT_REVIEW], true);
            if ($returnsToDesign || $to === DeliverableStatus::INTERNAL_REVIEW) {
                $approvalsReset = $this->resetApprovals($deliverable, null, $now);
            } elseif ($to === DeliverableStatus::CLIENT_REVIEW) {
                $approvalsReset = $this->resetApprovals($deliverable, [ApprovalSource::CLIENT, ApprovalSource::MANAGER], $now);
            }

            $deliverable->forceFill($changes)->save();
            $this->syncAlerts($deliverable, $from, $to, $now, $dueSoonBefore);

            if ($to === DeliverableStatus::DONE) {
                $this->closeOpenRecords($deliverable, $now);
                $this->samples->ended($deliverable, $closeReason === CloseReason::COMPLETED ? ProductionSamples::DELIVERED : ProductionSamples::CANCELLED, $actor);
            }

            $this->log->record(
                $deliverable,
                'deliverable_moved',
                $actor,
                $from->value,
                $to->value,
                array_filter([
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $reason->value,
                    'note' => $context['note'] ?? null,
                    'close_reason' => $closeReason?->value,
                    'task_id' => $context['task_id'] ?? null,
                    'due_target_from' => $dueBefore?->toDateString(),
                    'due_target_to' => $deliverable->due_target?->toDateString() !== $dueBefore?->toDateString() ? $deliverable->due_target?->toDateString() : null,
                    'approvals_reset' => $approvalsReset ?: null,
                ], fn (mixed $value): bool => $value !== null),
            );

            $this->projection->project($deliverable, $context);

            DB::afterCommit(function () use ($deliverable, $from, $to, $reason, $actor, $approvalsReset): void {
                if ($approvalsReset > 0) {
                    ApprovalReset::dispatch($deliverable, $approvalsReset);
                }
                DeliverableMoved::dispatch($deliverable, $from, $to, $reason, $actor);
            });
        });

        return $deliverable;
    }

    /**
     * Throws when the move is refused. Returns the close reason of a move to done.
     *
     * @param  array<string, mixed>  $context
     */
    private function guard(
        Deliverable $deliverable,
        DeliverableStatus $from,
        DeliverableStatus $to,
        TransitionReason $reason,
        ?User $actor,
        array $context,
    ): ?CloseReason {
        if ($from === $to) {
            throw WorkflowException::alreadyInStatus($to);
        }

        $rule = self::ruleFor($from, $to, $reason, $context);
        if ($rule === null) {
            throw WorkflowException::invalidTransition($from, $to);
        }

        if (in_array($rule, [self::RULE_MANAGER, self::RULE_REOPEN], true) && $actor !== null && ! Roles::isManager($actor)) {
            throw WorkflowException::managerOnly();
        }

        if ($rule === self::RULE_CLOSE) {
            return $this->closeReasonFor($deliverable, $from, $context);
        }

        if ($rule === self::RULE_FORWARD) {
            if ($deliverable->isBlocked()) {
                throw WorkflowException::blocked();
            }
            if ($deliverable->isOnHold()) {
                throw WorkflowException::onHold();
            }
            $this->assertExitRule($deliverable, $from, $to, $actor, $context);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function closeReasonFor(Deliverable $deliverable, DeliverableStatus $from, array $context): CloseReason
    {
        $given = $context['close_reason'] ?? null;
        if ($given instanceof CloseReason) {
            return $given;
        }

        if ($from !== DeliverableStatus::FULFILLMENT) {
            throw WorkflowException::needsCloseReason();
        }

        if (! $deliverable->receipts()->exists()) {
            throw WorkflowException::needsReceipt();
        }

        return CloseReason::COMPLETED;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertExitRule(Deliverable $deliverable, DeliverableStatus $from, DeliverableStatus $to, ?User $actor, array $context): void
    {
        if ($from === DeliverableStatus::INTERNAL_REVIEW) {
            if (! ($context['batch'] ?? false)) {
                throw WorkflowException::mustSendWithBatch();
            }
            if (! $this->hasStandingApproval($deliverable, [ApprovalSource::REVIEWER, ApprovalSource::MANAGER])) {
                throw WorkflowException::needsReviewerApproval();
            }
        }

        if ($from === DeliverableStatus::CLIENT_REVIEW) {
            $approved = Approval::standing()
                ->where('deliverable_id', $deliverable->getKey())
                ->whereIn('source', [ApprovalSource::CLIENT->value, ApprovalSource::MANAGER->value])
                ->where('measurements_confirmed', true)
                ->where('estimate_approved', true)
                ->exists();

            if (! ($context['client_approval'] ?? false) || ! $approved) {
                throw WorkflowException::needsClientApproval();
            }
        }

        if ($from === DeliverableStatus::PRODUCTION_FILES) {
            $manualByManager = $actor !== null && Roles::isManager($actor) && ! ($context['task_id'] ?? false);
            if (! $manualByManager && $this->requiredGates($deliverable, $to)->contains(fn (Task $task): bool => ! $task->isDone())) {
                throw WorkflowException::gateTasksPending($to);
            }
        }
    }

    /**
     * @param  list<ApprovalSource>  $sources
     */
    private function hasStandingApproval(Deliverable $deliverable, array $sources): bool
    {
        return Approval::standing()
            ->where('deliverable_id', $deliverable->getKey())
            ->whereIn('source', array_map(fn (ApprovalSource $source): string => $source->value, $sources))
            ->exists();
    }

    /**
     * Required gate tasks that lead to a status.
     *
     * @return Collection<int, Task>
     */
    public function requiredGates(Deliverable $deliverable, DeliverableStatus $advancesTo)
    {
        return $deliverable->tasks()
            ->where('required', true)
            ->where('advances_to', $advancesTo->value)
            ->whereIn('kind', [TaskKind::GATE_SEND_PROOF->value, TaskKind::GATE_PREPARE_PRODUCTION_FILES->value, TaskKind::GATE_CUSTOM->value])
            ->get();
    }

    /**
     * Column changes that depend on where the deliverable is going.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function areaChanges(Deliverable $deliverable, DeliverableStatus $from, DeliverableStatus $to, CarbonImmutable $now, ?CloseReason $closeReason, array $context): array
    {
        $changes = [];

        if (isset($context['assignee_id'])) {
            $changes['assignee_id'] = $context['assignee_id'];
        }
        if (array_key_exists('planned_for', $context)) {
            $changes['planned_for'] = $context['planned_for'];
        }
        if (($context['due_target'] ?? null) instanceof CarbonInterface) {
            $changes['due_target'] = $context['due_target']->toDateString();
        }

        if ($from === DeliverableStatus::DONE) {
            $changes += ['close_reason' => null, 'closed_at' => null];
        }

        switch ($to) {
            case DeliverableStatus::READY:
                if ($from !== DeliverableStatus::INTAKE) {
                    $changes['planned_for'] = $context['planned_for'] ?? null;
                }
                break;

            case DeliverableStatus::IN_DESIGN:
                if ($from === DeliverableStatus::CLIENT_REVIEW) {
                    $changes['due_target'] = $this->sla->clientChangesDueTarget($now)->toDateString();
                    $changes['client_revision_count'] = $deliverable->client_revision_count + 1;
                    $changes['planned_for'] ??= $now->toDateString();
                } elseif ($from === DeliverableStatus::INTERNAL_REVIEW) {
                    $changes['internal_revision_count'] = $deliverable->internal_revision_count + 1;
                    $changes['planned_for'] ??= $now->toDateString();
                } elseif (! array_key_exists('planned_for', $changes) && $deliverable->planned_for === null) {
                    $changes['planned_for'] = $now->toDateString();
                }
                break;

            case DeliverableStatus::INTERNAL_REVIEW:
                $changes['reviewer_id'] = $deliverable->reviewer_id ?? $this->owners->defaultReviewer()?->getKey();
                break;

            case DeliverableStatus::CLIENT_REVIEW:
                $changes['last_sent_to_client_at'] = $now;
                break;

            case DeliverableStatus::DONE:
                $changes['close_reason'] = $closeReason;
                $changes['closed_at'] = $now;
                break;

            default:
                break;
        }

        if ($to === DeliverableStatus::PRODUCTION_FILES && $from === DeliverableStatus::CLIENT_REVIEW && ! isset($changes['due_target'])) {
            $changes['due_target'] = $this->sla->approvalDueTarget($deliverable->isUrgent(), $now)->toDateString();
        }

        return $changes;
    }

    /**
     * @param  list<ApprovalSource>|null  $sources  Null resets every standing approval.
     */
    private function resetApprovals(Deliverable $deliverable, ?array $sources, CarbonInterface $now): int
    {
        $query = Approval::standing()->where('deliverable_id', $deliverable->getKey());
        if ($sources !== null) {
            $query->whereIn('source', array_map(fn (ApprovalSource $source): string => $source->value, $sources));
        }

        return $query->update(['reset_at' => $now]);
    }

    /**
     * Opens and closes the alerts that follow the stage change.
     */
    private function syncAlerts(Deliverable $deliverable, DeliverableStatus $from, DeliverableStatus $to, CarbonImmutable $now, bool $dueSoonBefore): void
    {
        if ($from === DeliverableStatus::CLIENT_REVIEW) {
            $this->alerts->resolve($deliverable, array_filter($this->alerts->clientKinds(), fn (AlertKind $kind): bool => $kind !== AlertKind::DELAY_NOTICE));
        }

        if ($from === DeliverableStatus::INTERNAL_REVIEW) {
            $this->alerts->resolve($deliverable, [AlertKind::REVIEWER_FOLLOW_UP]);
        }

        if ($to === DeliverableStatus::CLIENT_REVIEW) {
            $this->alerts->resolve($deliverable, [AlertKind::DELAY_NOTICE]);
            $this->alerts->scheduleClientFollowUps($deliverable, $deliverable->last_sent_to_client_at ?? $now);
        }

        if ($to === DeliverableStatus::INTERNAL_REVIEW) {
            $this->alerts->scheduleReviewerFollowUp($deliverable, $dueSoonBefore);
        }

        if ($to === DeliverableStatus::PRODUCTION_FILES) {
            $this->ensureProductionFilesGate($deliverable);
        }

        if ($to->position() >= DeliverableStatus::CLIENT_REVIEW->position()) {
            Alert::open()->where('deliverable_id', $deliverable->getKey())->where('kind', AlertKind::DELAY_NOTICE->value)->update(['resolved_at' => $now]);
        }

        if ($to === DeliverableStatus::DONE) {
            $this->alerts->resolveAll($deliverable);
        }
    }

    /**
     * Entering production files always carries exactly one required "prepare production files" gate.
     */
    public function ensureProductionFilesGate(Deliverable $deliverable): void
    {
        $exists = $deliverable->tasks()
            ->where('kind', TaskKind::GATE_PREPARE_PRODUCTION_FILES->value)
            ->exists();

        if ($exists) {
            $deliverable->tasks()
                ->where('kind', TaskKind::GATE_PREPARE_PRODUCTION_FILES->value)
                ->where('status', 'done')
                ->update(['status' => 'todo', 'completed_at' => null]);

            return;
        }

        $deliverable->tasks()->create([
            'title' => 'Prepare production files',
            'title_key' => 'Prepare production files',
            'status' => 'todo',
            'kind' => TaskKind::GATE_PREPARE_PRODUCTION_FILES,
            'advances_to' => DeliverableStatus::READY_FOR_PRODUCTION,
            'required' => true,
            'due_date' => now()->toDateString(),
        ]);
    }

    /**
     * A closed deliverable no longer carries open blocks or holds.
     */
    private function closeOpenRecords(Deliverable $deliverable, CarbonInterface $now): void
    {
        Block::open()->where('deliverable_id', $deliverable->getKey())->update([
            'resolved_at' => $now,
            'resolution_note' => __('Closed with the deliverable.'),
        ]);
        Hold::open()->where('deliverable_id', $deliverable->getKey())->update(['ended_at' => $now]);
    }
}
