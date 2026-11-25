<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;

/**
 * Records that someone followed up on a block (called the client, sent a message). The block stays open.
 */
class LogBlockFollowUp
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    public function __invoke(Block $block, string $note, ?User $actor = null): Block
    {
        $deliverable = $block->deliverable;
        $this->authorizeActor($actor, 'resolveBlock', $deliverable);

        if (trim($note) === '') {
            throw WorkflowException::refused('Write what you did in the follow-up note.');
        }

        if (! $block->refresh()->isOpen()) {
            throw WorkflowException::refused('This block is already resolved.');
        }

        $this->log->record($deliverable, 'block_follow_up', $actor, $block->reason->value, null, ['block_id' => $block->getKey(), 'note' => trim($note)]);

        return $block;
    }
}
