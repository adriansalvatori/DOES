<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;

/**
 * Copies a deliverable into the same work order and phase. The copy goes through the creation rules (intake, new due
 * target, audit entry); approvals, blocks and tasks are not copied.
 */
class DuplicateDeliverable
{
    use AuthorizesActor;

    public function __construct(private AddDeliverable $addDeliverable, private DeliveryLog $log) {}

    public function __invoke(Deliverable $source, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'duplicate', $source);

        $copy = ($this->addDeliverable)(
            workOrder: $source->workOrder,
            name: $source->task_name,
            size: $source->size,
            material: $source->material,
            quantity: $source->quantity,
            phase: $source->phase,
            flags: $source->flag_codes?->all() ?? [],
            assigneeId: $source->assignee_id,
            actor: $actor,
        );

        $this->log->record($copy, 'deliverable_duplicated', $actor, null, (string) $source->getKey(), ['source_id' => $source->getKey()]);

        return $copy;
    }
}
