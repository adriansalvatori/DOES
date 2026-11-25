<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\ApprovedByReviewer;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The reviewer approves the work in internal review. A manager approving on their behalf is recorded as such.
 */
class ApproveByReviewer
{
    use AuthorizesActor;

    public function __construct(
        private DeliveryLog $log,
        private AlertScheduler $alerts,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(Deliverable $deliverable, ?string $note = null, ?User $actor = null): Approval
    {
        $this->authorizeActor($actor, 'approveByReviewer', $deliverable);

        return DB::transaction(function () use ($deliverable, $note, $actor): Approval {
            $deliverable->refresh();

            if ($deliverable->status !== DeliverableStatus::INTERNAL_REVIEW) {
                throw WorkflowException::refused('Only work in internal review can be approved by the reviewer.');
            }

            $source = Roles::isReviewer($actor) || $actor === null ? ApprovalSource::REVIEWER : ApprovalSource::MANAGER;

            Approval::standing()->where('deliverable_id', $deliverable->getKey())->whereIn('source', [ApprovalSource::REVIEWER->value, ApprovalSource::MANAGER->value])->update(['reset_at' => now()]);

            $approval = Approval::create([
                'deliverable_id' => $deliverable->getKey(),
                'source' => $source,
                'note' => $note,
                'approved_by' => $actor?->getKey(),
                'approved_at' => now(),
            ]);

            $this->alerts->resolve($deliverable, [AlertKind::REVIEWER_FOLLOW_UP]);
            $this->log->record($deliverable, 'approved_by_reviewer', $actor, null, $source->value, ['approval_id' => $approval->getKey(), 'note' => $note]);
            $this->projection->project($deliverable);

            DB::afterCommit(fn () => ApprovedByReviewer::dispatch($deliverable, $approval, $actor));

            return $approval;
        });
    }
}
