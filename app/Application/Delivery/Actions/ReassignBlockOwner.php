<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;

/**
 * Hands an open block to another person (null sends it back to the customer service queue).
 */
class ReassignBlockOwner
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    public function __invoke(Block $block, ?User $newOwner, ?User $actor = null): Block
    {
        $deliverable = $block->deliverable;
        $this->authorizeActor($actor, 'resolveBlock', $deliverable);

        if (! $block->refresh()->isOpen()) {
            throw WorkflowException::refused('This block is already resolved.');
        }

        $previousId = $block->owner_id;
        if ($previousId === $newOwner?->getKey()) {
            return $block;
        }

        $block->update(['owner_id' => $newOwner?->getKey()]);
        $this->log->record($deliverable, 'block_reassigned', $actor, $previousId !== null ? (string) $previousId : null, $newOwner !== null ? (string) $newOwner->getKey() : null, ['block_id' => $block->getKey()]);

        return $block;
    }
}
