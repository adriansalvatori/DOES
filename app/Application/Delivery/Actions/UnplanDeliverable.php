<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes a deliverable off its day. Work already in design returns to the ready backlog (managers only).
 */
class UnplanDeliverable
{
    use AuthorizesActor;

    public function __construct(
        private DeliverableWorkflow $workflow,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(Deliverable $deliverable, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'unplan', $deliverable);

        return DB::transaction(function () use ($deliverable, $actor): Deliverable {
            $deliverable->refresh();

            if ($deliverable->status === DeliverableStatus::IN_DESIGN) {
                return $this->workflow->transition($deliverable, DeliverableStatus::READY, TransitionReason::MANUAL, $actor, ['planned_for' => null]);
            }

            if ($deliverable->status !== DeliverableStatus::READY || $deliverable->planned_for === null) {
                throw WorkflowException::refused('This deliverable is not planned.');
            }

            $previous = $deliverable->planned_for->toDateString();
            $deliverable->forceFill(['planned_for' => null])->save();

            $this->log->record($deliverable, 'deliverable_unplanned', $actor, $previous, null);
            $this->projection->project($deliverable);

            return $deliverable;
        });
    }
}
