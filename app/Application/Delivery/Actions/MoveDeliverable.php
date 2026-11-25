<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A manual move between statuses (drag and drop, "move to" menu). The workflow decides if it is allowed.
 */
class MoveDeliverable
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(
        Deliverable $deliverable,
        DeliverableStatus $to,
        ?User $actor = null,
        ?string $note = null,
        ?int $assigneeId = null,
        ?CarbonInterface $plannedFor = null,
    ): Deliverable {
        $this->authorizeActor($actor, 'move', $deliverable);

        $context = array_filter([
            'note' => $note,
            'assignee_id' => $assigneeId,
            'planned_for' => $plannedFor?->toDateString(),
        ], fn (mixed $value): bool => $value !== null);

        return $this->workflow->transition($deliverable, $to, TransitionReason::MANUAL, $actor, $context);
    }
}
