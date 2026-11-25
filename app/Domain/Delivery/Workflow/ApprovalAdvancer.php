<?php

namespace App\Domain\Delivery\Workflow;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;

/**
 * Moves a deliverable from client review to production files once a complete client approval stands and
 * nothing blocks it.
 */
class ApprovalAdvancer
{
    public function __construct(private DeliverableWorkflow $workflow) {}

    public function advanceIfApproved(Deliverable $deliverable, ?User $actor = null): bool
    {
        $deliverable->refresh();

        if ($deliverable->status !== DeliverableStatus::CLIENT_REVIEW || $deliverable->isBlocked() || $deliverable->isOnHold()) {
            return false;
        }

        $complete = Approval::standing()
            ->where('deliverable_id', $deliverable->getKey())
            ->whereIn('source', [ApprovalSource::CLIENT->value, ApprovalSource::MANAGER->value])
            ->where('measurements_confirmed', true)
            ->where('estimate_approved', true)
            ->exists();

        if (! $complete) {
            return false;
        }

        $this->workflow->transition(
            $deliverable,
            DeliverableStatus::PRODUCTION_FILES,
            TransitionReason::MANUAL,
            $actor,
            ['client_approval' => true],
        );

        return true;
    }
}
