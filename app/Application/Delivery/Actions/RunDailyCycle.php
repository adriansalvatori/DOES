<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\DeliverableDueToday;
use App\Domain\Delivery\Events\DeliverableOverdue;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Domain\Scheduling\FollowUpPolicy;
use App\Domain\Scheduling\SlaPolicy;
use App\Domain\Scheduling\SlaState;
use App\Domain\Scheduling\WipPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The scheduled cycle. Every step is idempotent, so it can run more than once a day (07:00 for the full cycle,
 * 14:30 to open delay notices).
 *
 * 1. carries unfinished work of earlier days to today;
 * 2. starts ready work planned for today or earlier, respecting each person's WIP limit and skipping blocked work;
 * 3. keeps the client follow-up alerts at 3/6/9 business days and puts silent clients on hold after the ninth
 *    (paused while a production sample is on its way to the client);
 * 3b. archives work that has been on hold with no activity for more than 30 days;
 * 4. opens delay notices for work that is late or due today from 14:30;
 * 5. announces what is due today or overdue (overdue is computed, never stored).
 */
class RunDailyCycle
{
    public function __construct(
        private DeliverableWorkflow $workflow,
        private WipPolicy $wip,
        private FollowUpPolicy $followUps,
        private SlaPolicy $sla,
        private AlertScheduler $alerts,
        private PutOnHold $putOnHold,
        private ArchiveStaleHolds $archiveStaleHolds,
        private BlockDeliverable $blockDeliverable,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    /**
     * @return array{carried_over: int, started: int, skipped_by_wip: int, follow_ups_opened: int, put_on_hold: int, archived_on_hold: int, delay_notices: int, due_today: int, overdue: int}
     */
    public function __invoke(bool $notify = true): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $summary = [
            'carried_over' => $this->carryOver($today),
            'started' => 0,
            'skipped_by_wip' => 0,
            'follow_ups_opened' => 0,
            'put_on_hold' => 0,
            'archived_on_hold' => 0,
            'delay_notices' => 0,
            'due_today' => 0,
            'overdue' => 0,
        ];

        [$summary['started'], $summary['skipped_by_wip']] = $this->startPlannedWork($today);
        [$summary['follow_ups_opened'], $summary['put_on_hold']] = $this->followUpClients();
        $summary['archived_on_hold'] = ($this->archiveStaleHolds)($today);
        $summary['delay_notices'] = $this->openDelayNotices();

        if ($notify) {
            [$summary['due_today'], $summary['overdue']] = $this->announceDueWork($today);
        }

        return $summary;
    }

    private function carryOver(CarbonImmutable $today): int
    {
        $carried = 0;

        Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::IN_DESIGN)
            ->whereNotNull('planned_for')
            ->whereDate('planned_for', '<', $today->toDateString())
            ->notBlocked()
            ->get()
            ->reject(fn (Deliverable $each): bool => $each->isOnHold())
            ->each(function (Deliverable $deliverable) use ($today, &$carried): void {
                DB::transaction(function () use ($deliverable, $today): void {
                    $previous = $deliverable->planned_for->toDateString();
                    $deliverable->forceFill([
                        'planned_for' => $today->toDateString(),
                        'carry_over_count' => $deliverable->carry_over_count + 1,
                    ])->save();

                    $this->log->record($deliverable, 'carried_over', null, $previous, $today->toDateString(), ['carry_over_count' => $deliverable->carry_over_count]);
                    $this->projection->project($deliverable);
                });
                $carried++;
            });

        return $carried;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function startPlannedWork(CarbonImmutable $today): array
    {
        $started = 0;
        $skipped = 0;
        $startedByUser = [];

        $due = Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::READY)
            ->whereNotNull('assignee_id')
            ->whereNotNull('planned_for')
            ->whereDate('planned_for', '<=', $today->toDateString())
            ->notBlocked()
            ->with('assignee')
            ->orderBy('planned_for')
            ->orderBy('id')
            ->get()
            ->reject(fn (Deliverable $each): bool => $each->isOnHold() || $each->assignee === null);

        foreach ($due as $deliverable) {
            $userId = $deliverable->assignee_id;

            if (! $this->wip->hasRoomToStart($deliverable->assignee, $startedByUser[$userId] ?? 0)) {
                $skipped++;

                continue;
            }

            $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::DAILY_CYCLE, null, [
                'planned_for' => $today->toDateString(),
            ]);

            $startedByUser[$userId] = ($startedByUser[$userId] ?? 0) + 1;
            $started++;
        }

        return [$started, $skipped];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function followUpClients(): array
    {
        $opened = 0;
        $held = 0;

        Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::CLIENT_REVIEW)
            ->whereNotNull('last_sent_to_client_at')
            ->whereDoesntHave('samples', fn ($samples) => $samples->active())
            ->get()
            ->reject(fn (Deliverable $each): bool => $each->isOnHold())
            ->each(function (Deliverable $deliverable) use (&$opened, &$held): void {
                $sentAt = $deliverable->last_sent_to_client_at;

                foreach ($this->followUps->dueKinds($sentAt) as $kind) {
                    $exists = $deliverable->alerts()->where('kind', $kind->value)->whereDate('due_on', $this->followUps->dueOn($kind, $sentAt)->toDateString())->exists();
                    if (! $exists) {
                        $this->alerts->open($deliverable, $kind, $this->followUps->dueOn($kind, $sentAt)->toDateString(), $deliverable->assignee_id);
                        $opened++;
                    }
                }

                if ($this->followUps->shouldGoOnHold($sentAt)) {
                    DB::transaction(function () use ($deliverable): void {
                        ($this->putOnHold)($deliverable, HoldReason::NO_RESPONSE, __('No client response after 9 business days.'));
                        if (! $deliverable->blocks()->whereNull('resolved_at')->where('reason', BlockReason::CUSTOMER_SERVICE_REQUIRED->value)->exists()) {
                            ($this->blockDeliverable)($deliverable, BlockReason::CUSTOMER_SERVICE_REQUIRED, __('The client has not responded to the proof.'));
                        }
                    });
                    $held++;
                }
            });

        return [$opened, $held];
    }

    private function openDelayNotices(): int
    {
        $opened = 0;

        Deliverable::query()
            ->inWorkspace()
            ->inStatus($this->sla->delayNoticeStatuses())
            ->whereNotNull('due_target')
            ->get()
            ->each(function (Deliverable $deliverable) use (&$opened): void {
                if ($this->sla->needsDelayNotice($deliverable) && $this->alerts->ensureDelayNotice($deliverable) !== null) {
                    $opened++;
                }
            });

        return $opened;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function announceDueWork(CarbonImmutable $today): array
    {
        $dueToday = 0;
        $overdue = 0;

        Deliverable::query()
            ->inWorkspace()
            ->inStatus($this->sla->trackedStatuses())
            ->whereNotNull('due_target')
            ->whereDate('due_target', '<=', $today->toDateString())
            ->get()
            ->each(function (Deliverable $deliverable) use (&$dueToday, &$overdue, $today): void {
                if (! $this->sla->isTracked($deliverable)) {
                    return;
                }

                if ($deliverable->due_target->isSameDay($today)) {
                    DeliverableDueToday::dispatch($deliverable);
                    $dueToday++;
                } elseif ($this->sla->stateFor($deliverable, $today) === SlaState::OVERDUE) {
                    DeliverableOverdue::dispatch($deliverable);
                    $overdue++;
                }
            });

        return [$dueToday, $overdue];
    }
}
