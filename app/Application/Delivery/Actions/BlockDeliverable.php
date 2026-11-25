<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Events\DeliverableBlocked;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\BlockOwnerResolver;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Raises a block: the deliverable stays where it is but cannot move forward until the block is resolved.
 * The owner defaults to the first active customer service user, or the customer service queue when there is none.
 */
class BlockDeliverable
{
    use AuthorizesActor;

    public function __construct(
        private BlockOwnerResolver $owners,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(
        Deliverable $deliverable,
        BlockReason $reason,
        ?string $note = null,
        ?User $actor = null,
        ?User $owner = null,
    ): Block {
        $this->authorizeActor($actor, 'block', $deliverable);

        return DB::transaction(function () use ($deliverable, $reason, $note, $actor, $owner): Block {
            $deliverable->refresh();

            if ($deliverable->status->isClosed()) {
                throw WorkflowException::closed();
            }

            if ($deliverable->blocks()->whereNull('resolved_at')->where('reason', $reason->value)->exists()) {
                throw WorkflowException::refused('This deliverable is already blocked for :reason.', ['reason' => $reason->label()]);
            }

            $block = Block::create([
                'deliverable_id' => $deliverable->getKey(),
                'reason' => $reason,
                'note' => $note,
                'owner_id' => ($owner ?? $this->owners->defaultOwner())?->getKey(),
                'raised_by' => $actor?->getKey(),
                'raised_at' => now(),
            ]);

            $this->log->record($deliverable, 'deliverable_blocked', $actor, null, $reason->value, ['block_id' => $block->getKey(), 'note' => $note, 'owner_id' => $block->owner_id]);
            $this->projection->project($deliverable);

            DB::afterCommit(fn () => DeliverableBlocked::dispatch($deliverable, $block, $actor));

            return $block;
        });
    }
}
