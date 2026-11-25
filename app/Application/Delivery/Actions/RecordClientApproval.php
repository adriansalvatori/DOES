<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\ApprovedByClient;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\BlockOwnerResolver;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\ApprovalAdvancer;
use App\Domain\Scheduling\SlaPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records the client's answer to a proof. With measurements and estimate confirmed the deliverable moves to production
 * files. Missing measurements or a missing estimate approval raise a block owned by customer service instead; the
 * rest of the work order is not affected.
 */
class RecordClientApproval
{
    use AuthorizesActor;

    public function __construct(
        private DeliveryLog $log,
        private LegacyProjection $projection,
        private ApprovalAdvancer $advancer,
        private BlockOwnerResolver $owners,
        private SlaPolicy $sla,
    ) {}

    public function __invoke(
        Deliverable $deliverable,
        ApprovalSource $source,
        bool $measurementsConfirmed,
        bool $estimateApproved,
        ?string $note = null,
        ?string $evidencePath = null,
        ?User $actor = null,
    ): Approval {
        $this->authorizeActor($actor, 'recordClientApproval', $deliverable);

        if ($source === ApprovalSource::REVIEWER) {
            throw WorkflowException::refused('The reviewer approves through the review step, not as the client.');
        }

        return DB::transaction(function () use ($deliverable, $source, $measurementsConfirmed, $estimateApproved, $note, $evidencePath, $actor): Approval {
            $deliverable->refresh();

            if ($deliverable->status !== DeliverableStatus::CLIENT_REVIEW) {
                throw WorkflowException::refused('Only work waiting for the client can be approved by the client.');
            }

            Approval::standing()
                ->where('deliverable_id', $deliverable->getKey())
                ->whereIn('source', [ApprovalSource::CLIENT->value, ApprovalSource::MANAGER->value])
                ->update(['reset_at' => now()]);

            $approval = Approval::create([
                'deliverable_id' => $deliverable->getKey(),
                'source' => $source,
                'measurements_confirmed' => $measurementsConfirmed,
                'estimate_approved' => $estimateApproved,
                'note' => $note,
                'evidence_path' => $evidencePath,
                'approved_by' => $actor?->getKey(),
                'approved_at' => now(),
            ]);

            $this->syncBlock($deliverable, BlockReason::MISSING_MEASUREMENTS, $measurementsConfirmed, $actor);
            $this->syncBlock($deliverable, BlockReason::AWAITING_ESTIMATE_APPROVAL, $estimateApproved, $actor);

            $deliverable->forceFill(['due_target' => $this->sla->approvalDueTarget($deliverable->isUrgent())->toDateString()])->save();

            $this->log->record($deliverable, 'client_approval_recorded', $actor, null, $source->value, [
                'approval_id' => $approval->getKey(),
                'measurements_confirmed' => $measurementsConfirmed,
                'estimate_approved' => $estimateApproved,
                'note' => $note,
                'evidence_path' => $evidencePath,
            ]);

            $moved = $this->advancer->advanceIfApproved($deliverable, $actor);
            if (! $moved) {
                $this->projection->project($deliverable);
            }

            DB::afterCommit(fn () => ApprovedByClient::dispatch($deliverable, $approval, $actor));

            return $approval;
        });
    }

    /**
     * Opens the block for a missing item, or closes it when the item is now confirmed.
     */
    private function syncBlock(Deliverable $deliverable, BlockReason $reason, bool $confirmed, ?User $actor): void
    {
        $open = $deliverable->blocks()->whereNull('resolved_at')->where('reason', $reason->value)->first();

        if ($confirmed) {
            $open?->update([
                'resolved_by' => $actor?->getKey(),
                'resolved_at' => now(),
                'resolution_note' => __('Confirmed with the client approval.'),
            ]);

            return;
        }

        if ($open === null) {
            Block::create([
                'deliverable_id' => $deliverable->getKey(),
                'reason' => $reason,
                'owner_id' => $this->owners->defaultOwner()?->getKey(),
                'raised_by' => $actor?->getKey(),
                'raised_at' => now(),
            ]);
        }
    }
}
