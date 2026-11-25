<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;

/**
 * The reviewer sends the work back to design. Any standing approval is reset.
 */
class RequestChangesByReviewer
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(Deliverable $deliverable, string $note, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'requestChangesByReviewer', $deliverable);

        if ($deliverable->refresh()->status !== DeliverableStatus::INTERNAL_REVIEW) {
            throw WorkflowException::refused('Only work in internal review can be sent back by the reviewer.');
        }

        return $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL, $actor, [
            'note' => $note,
            'reviewer_changes' => true,
        ]);
    }
}
