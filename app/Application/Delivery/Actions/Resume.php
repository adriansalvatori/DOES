<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\DeliverableResumed;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Scheduling\SlaPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ends the open hold. The due target restarts at 2 business days; a proof waiting for the client restarts its follow-ups.
 */
class Resume
{
    use AuthorizesActor;

    public function __construct(
        private SlaPolicy $sla,
        private AlertScheduler $alerts,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(Deliverable $deliverable, ?User $actor = null): Hold
    {
        $this->authorizeActor($actor, 'resume', $deliverable);

        return DB::transaction(function () use ($deliverable, $actor): Hold {
            $deliverable->refresh();
            $hold = $deliverable->openHold;

            if ($hold === null) {
                throw WorkflowException::refused('This deliverable is not on hold.');
            }

            $hold->update(['ended_at' => now()]);

            $changes = ['due_target' => $this->sla->resumeDueTarget()->toDateString()];
            if ($deliverable->status === DeliverableStatus::CLIENT_REVIEW) {
                $changes['last_sent_to_client_at'] = now();
            }
            $deliverable->forceFill($changes)->save();

            if ($deliverable->status === DeliverableStatus::CLIENT_REVIEW) {
                $this->alerts->scheduleClientFollowUps($deliverable, now());
            }

            $this->log->record($deliverable, 'deliverable_resumed', $actor, $hold->reason->value, null, [
                'hold_id' => $hold->getKey(),
                'due_target' => $changes['due_target'],
            ]);
            $this->projection->project($deliverable);

            DB::afterCommit(fn () => DeliverableResumed::dispatch($deliverable, $hold, $actor));

            return $hold;
        });
    }
}
