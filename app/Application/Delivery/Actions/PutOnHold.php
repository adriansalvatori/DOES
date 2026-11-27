<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\DeliverablePutOnHold;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pauses a deliverable. It keeps its status but cannot move forward, drops out of the daily lists and loses its due
 * date, so it never counts as overdue. Resuming sets a new one.
 */
class PutOnHold
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    public function __invoke(Deliverable $deliverable, HoldReason $reason, ?string $note = null, ?User $actor = null): Hold
    {
        $this->authorizeActor($actor, 'putOnHold', $deliverable);

        return DB::transaction(function () use ($deliverable, $reason, $note, $actor): Hold {
            $deliverable->refresh();

            if ($deliverable->status->isClosed()) {
                throw WorkflowException::closed();
            }

            if ($deliverable->isOnHold()) {
                throw WorkflowException::refused('This deliverable is already on hold.');
            }

            $hold = Hold::create([
                'deliverable_id' => $deliverable->getKey(),
                'reason' => $reason,
                'note' => $note,
                'started_at' => now(),
            ]);

            $previousDueTarget = $deliverable->due_target?->toDateString();
            $deliverable->forceFill(['due_target' => null])->save();

            $this->log->record($deliverable, 'deliverable_on_hold', $actor, null, $reason->value, [
                'hold_id' => $hold->getKey(),
                'note' => $note,
                'due_target' => $previousDueTarget,
            ]);
            $this->projection->project($deliverable);

            DB::afterCommit(fn () => DeliverablePutOnHold::dispatch($deliverable, $hold, $actor));

            return $hold;
        });
    }
}
