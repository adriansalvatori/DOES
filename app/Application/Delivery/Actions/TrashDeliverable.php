<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soft deletes a deliverable. Its tasks go to the trash with it and its alerts are removed.
 */
class TrashDeliverable
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    public function __invoke(Deliverable $deliverable, ?User $actor = null): void
    {
        $this->authorizeActor($actor, 'trash', $deliverable);

        DB::transaction(function () use ($deliverable, $actor): void {
            $deliverable->tasks()->delete();
            Alert::query()->where('deliverable_id', $deliverable->getKey())->delete();

            $this->log->record($deliverable, 'deliverable_trashed', $actor, $deliverable->status->value);
            $deliverable->delete();
        });
    }
}
