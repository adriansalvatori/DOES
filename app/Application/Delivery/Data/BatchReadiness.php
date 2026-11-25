<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Support\Collection;

/**
 * Whether a phase (or the whole work order when it has no phases) can be sent to the client.
 * Expects `approvals`, `openBlock` and `openHold` to be loaded on the deliverables.
 */
final readonly class BatchReadiness
{
    /**
     * @param  Collection<int, Deliverable>  $leftBehind  Deliverables that would not travel with the batch.
     */
    public function __construct(
        public bool $ready,
        public int $approved,
        public int $total,
        public Collection $leftBehind,
    ) {}

    /**
     * @param  Collection<int, Deliverable>  $scope  Every deliverable of the phase / work order.
     */
    public static function evaluate(Collection $scope): self
    {
        $eligible = $scope->filter(fn (Deliverable $deliverable): bool => $deliverable->status === DeliverableStatus::INTERNAL_REVIEW
            && ! self::isStopped($deliverable))->values();

        $approved = $eligible->filter(fn (Deliverable $deliverable): bool => self::hasReviewerApproval($deliverable))->count();

        $leftBehind = $scope->filter(function (Deliverable $deliverable): bool {
            $status = $deliverable->status ?? DeliverableStatus::INTAKE;

            if ($status === DeliverableStatus::DONE) {
                return false;
            }

            return $status->position() < DeliverableStatus::INTERNAL_REVIEW->position()
                || ($status === DeliverableStatus::INTERNAL_REVIEW && self::isStopped($deliverable));
        })->values();

        return new self(
            ready: $eligible->isNotEmpty() && $approved === $eligible->count(),
            approved: $approved,
            total: $eligible->count(),
            leftBehind: $leftBehind,
        );
    }

    public static function isStopped(Deliverable $deliverable): bool
    {
        return $deliverable->openBlock !== null || $deliverable->openHold !== null;
    }

    public static function hasReviewerApproval(Deliverable $deliverable): bool
    {
        return $deliverable->approvals->contains(
            fn ($approval): bool => $approval->source === ApprovalSource::REVIEWER && $approval->reset_at === null,
        );
    }
}
