<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Events\BlockResolved;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\ApprovalAdvancer;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a block. The deliverable never left its status, so it is back in flow at once. A block raised by an
 * incomplete client approval completes that approval, and the deliverable moves on when nothing else holds it.
 */
class ResolveBlock
{
    use AuthorizesActor;

    public function __construct(
        private DeliveryLog $log,
        private LegacyProjection $projection,
        private ApprovalAdvancer $advancer,
    ) {}

    public function __invoke(Block $block, ?string $note = null, ?User $actor = null): Block
    {
        $deliverable = $block->deliverable;
        $this->authorizeActor($actor, 'resolveBlock', $deliverable);

        return DB::transaction(function () use ($block, $deliverable, $note, $actor): Block {
            $block->refresh();

            if (! $block->isOpen()) {
                throw WorkflowException::refused('This block is already resolved.');
            }

            $block->update([
                'resolved_by' => $actor?->getKey(),
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->completeApproval($block);

            $this->log->record($deliverable, 'block_resolved', $actor, $block->reason->value, null, ['block_id' => $block->getKey(), 'note' => $note]);
            $this->projection->project($deliverable);
            $this->advancer->advanceIfApproved($deliverable, $actor);

            DB::afterCommit(fn () => BlockResolved::dispatch($deliverable, $block, $actor));

            return $block;
        });
    }

    private function completeApproval(Block $block): void
    {
        $column = match ($block->reason) {
            BlockReason::MISSING_MEASUREMENTS => 'measurements_confirmed',
            BlockReason::AWAITING_ESTIMATE_APPROVAL => 'estimate_approved',
            default => null,
        };

        if ($column === null) {
            return;
        }

        Approval::standing()
            ->where('deliverable_id', $block->deliverable_id)
            ->whereIn('source', [ApprovalSource::CLIENT->value, ApprovalSource::MANAGER->value])
            ->update([$column => true]);
    }
}
