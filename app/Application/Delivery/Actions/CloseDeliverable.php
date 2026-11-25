<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;

/**
 * Closes a deliverable from any status with a reason. Open blocks, holds and alerts are closed with it.
 */
class CloseDeliverable
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(Deliverable $deliverable, CloseReason $reason, ?string $note = null, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'close', $deliverable);

        return $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL, $actor, [
            'close_reason' => $reason,
            'note' => $note,
        ]);
    }
}
