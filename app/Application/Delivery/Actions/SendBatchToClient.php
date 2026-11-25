<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\PhaseSentToClient;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends a batch of approved work from internal review to the client together. The batch is a phase, a work order
 * without phases, or one deliverable that was left behind earlier. Blocked and on-hold deliverables stay behind.
 */
class SendBatchToClient
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    /**
     * @return Collection<int, Deliverable> The deliverables that moved.
     */
    public function __invoke(Phase|WorkOrder|Deliverable $batch, ?User $actor = null): Collection
    {
        [$workOrder, $phase, $candidates] = $this->resolve($batch);

        $inReview = $candidates->filter(fn (Deliverable $each): bool => $each->status === DeliverableStatus::INTERNAL_REVIEW);

        $ready = $batch instanceof Deliverable
            ? $inReview
            : $inReview->reject(fn (Deliverable $each): bool => $each->isBlocked() || $each->isOnHold());

        if ($ready->isEmpty()) {
            throw WorkflowException::refused('Nothing in this batch is ready to send.');
        }

        foreach ($ready as $each) {
            $this->authorizeActor($actor, 'sendBatchToClient', $each);
        }

        $unapproved = $ready->first(fn (Deliverable $each): bool => ! Approval::standing()
            ->where('deliverable_id', $each->getKey())
            ->whereIn('source', [ApprovalSource::REVIEWER->value, ApprovalSource::MANAGER->value])
            ->exists());

        if ($unapproved !== null) {
            throw WorkflowException::refused('The reviewer must approve :name before the batch goes to the client.', ['name' => $unapproved->task_name]);
        }

        return DB::transaction(function () use ($ready, $workOrder, $phase, $actor): Collection {
            $moved = $ready->map(fn (Deliverable $each): Deliverable => $this->workflow->transition(
                $each,
                DeliverableStatus::CLIENT_REVIEW,
                TransitionReason::MANUAL,
                $actor,
                ['batch' => true],
            ));

            $ids = $moved->map(fn (Deliverable $each): int => $each->getKey())->values()->all();
            DB::afterCommit(fn () => PhaseSentToClient::dispatch($workOrder, $phase, $ids, $actor));

            return $moved->values();
        });
    }

    /**
     * @return array{0: WorkOrder, 1: Phase|null, 2: Collection<int, Deliverable>}
     */
    private function resolve(Phase|WorkOrder|Deliverable $batch): array
    {
        if ($batch instanceof Deliverable) {
            return [$batch->workOrder, $batch->phase, collect([$batch->refresh()])];
        }

        if ($batch instanceof Phase) {
            return [$batch->workOrder, $batch, $batch->deliverables()->get()];
        }

        if ($batch->phases()->exists()) {
            throw WorkflowException::refused('This work order has phases. Choose the phase to send.');
        }

        return [$batch, null, $batch->deliverables()->get()];
    }
}
