<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Domain\Scheduling\WipPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a deliverable to a person for a day. Blocked work cannot be planned. The person's WIP limit is enforced
 * (blocked work does not count) unless a manager overrides it. Work still in intake becomes ready.
 */
class PlanDeliverable
{
    use AuthorizesActor;

    public function __construct(
        private DeliverableWorkflow $workflow,
        private WipPolicy $wip,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(
        Deliverable $deliverable,
        User $assignee,
        CarbonInterface $day,
        ?User $actor = null,
        bool $overrideWip = false,
    ): Deliverable {
        $this->authorizeActor($actor, 'plan', $deliverable);

        return DB::transaction(function () use ($deliverable, $assignee, $day, $actor, $overrideWip): Deliverable {
            $deliverable->refresh();

            if ($deliverable->status->isClosed()) {
                throw WorkflowException::closed();
            }

            if (! in_array($deliverable->status, [DeliverableStatus::INTAKE, DeliverableStatus::READY, DeliverableStatus::IN_DESIGN], true)) {
                throw WorkflowException::refused('Only work that has not reached review can be planned.');
            }

            if ($deliverable->isBlocked()) {
                throw WorkflowException::refused('A blocked deliverable cannot be planned.');
            }

            $overrideAllowed = $overrideWip && ($actor === null || Roles::isManager($actor));
            if (! $overrideAllowed && ! $this->wip->hasRoomToPlan($assignee, $day, $deliverable)) {
                throw WorkflowException::refused(':name already has :limit deliverables planned for that day.', [
                    'name' => $assignee->name,
                    'limit' => $this->wip->limitFor($assignee),
                ]);
            }

            if ($deliverable->status === DeliverableStatus::INTAKE) {
                return $this->workflow->transition($deliverable, DeliverableStatus::READY, TransitionReason::MANUAL, $actor, [
                    'assignee_id' => $assignee->getKey(),
                    'planned_for' => $day->toDateString(),
                ]);
            }

            $previous = ['assignee_id' => $deliverable->assignee_id, 'planned_for' => $deliverable->planned_for?->toDateString()];
            $deliverable->forceFill(['assignee_id' => $assignee->getKey(), 'planned_for' => $day->toDateString()])->save();

            $this->log->record($deliverable, 'deliverable_planned', $actor, null, $day->toDateString(), [
                'assignee_id' => $assignee->getKey(),
                'previous' => $previous,
            ]);
            $this->projection->project($deliverable);

            return $deliverable;
        });
    }
}
