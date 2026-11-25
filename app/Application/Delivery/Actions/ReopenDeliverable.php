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
 * Reopens a closed deliverable into the status it was closed from (ready when that is unknown). Managers only.
 */
class ReopenDeliverable
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(Deliverable $deliverable, ?string $note = null, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'reopen', $deliverable);

        if (! $deliverable->refresh()->status->isClosed()) {
            throw WorkflowException::refused('Only a closed deliverable can be reopened.');
        }

        return $this->workflow->transition($deliverable, $this->statusBeforeClosing($deliverable), TransitionReason::MANUAL, $actor, ['note' => $note]);
    }

    private function statusBeforeClosing(Deliverable $deliverable): DeliverableStatus
    {
        $closing = $deliverable->events()
            ->where('event_type', 'deliverable_moved')
            ->where('new_value', DeliverableStatus::DONE->value)
            ->latest('id')
            ->first();

        $from = DeliverableStatus::tryFrom((string) $closing?->previous_value);

        return $from !== null && $from !== DeliverableStatus::DONE ? $from : DeliverableStatus::READY;
    }
}
