<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records the same client approval on every deliverable of a work order that is waiting for the client.
 * Deliverables in other statuses, or on hold, are left alone.
 */
class ApproveAllInClientReview
{
    public function __construct(private RecordClientApproval $recordClientApproval) {}

    /**
     * @return Collection<int, Approval>
     */
    public function __invoke(
        WorkOrder $workOrder,
        ApprovalSource $source,
        bool $measurementsConfirmed,
        bool $estimateApproved,
        ?string $note = null,
        ?string $evidencePath = null,
        ?User $actor = null,
    ): Collection {
        $waiting = $workOrder->deliverables()
            ->inStatus(DeliverableStatus::CLIENT_REVIEW)
            ->get()
            ->reject(fn ($deliverable): bool => $deliverable->isOnHold());

        if ($waiting->isEmpty()) {
            throw WorkflowException::refused('No deliverable of this work order is waiting for the client.');
        }

        return DB::transaction(fn (): Collection => $waiting->map(
            fn ($deliverable): Approval => ($this->recordClientApproval)($deliverable, $source, $measurementsConfirmed, $estimateApproved, $note, $evidencePath, $actor),
        )->values());
    }
}
