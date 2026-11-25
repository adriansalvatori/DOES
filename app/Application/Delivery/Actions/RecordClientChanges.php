<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\ClientResponded;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The client asked for changes: the deliverable returns to design with a 2 business day due target and one more revision.
 */
class RecordClientChanges
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(Deliverable $deliverable, ?string $note = null, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'recordClientChanges', $deliverable);

        if ($deliverable->refresh()->status !== DeliverableStatus::CLIENT_REVIEW) {
            throw WorkflowException::refused('Only work waiting for the client can come back with client changes.');
        }

        return DB::transaction(function () use ($deliverable, $note, $actor): Deliverable {
            $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL, $actor, [
                'note' => $note,
                'client_changes' => true,
            ]);

            DB::afterCommit(fn () => ClientResponded::dispatch($deliverable, $note, $actor));

            return $deliverable;
        });
    }
}
