<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes an imported work order out of the backlog. Its deliverables keep their status (intake) and start showing up in the daily screens.
 */
class MoveWorkOrderOutOfBacklog
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    public function __invoke(WorkOrder $workOrder, ?User $actor = null): WorkOrder
    {
        $this->authorizeActor($actor, 'createWorkOrder', Deliverable::class);

        return DB::transaction(function () use ($workOrder, $actor): WorkOrder {
            $workOrder->refresh();

            if (! $workOrder->in_backlog) {
                throw WorkflowException::refused('This work order is not in the backlog.');
            }

            $workOrder->update(['in_backlog' => false]);

            foreach ($workOrder->deliverables()->get() as $deliverable) {
                $this->log->record($deliverable, 'moved_out_of_backlog', $actor, null, null, ['work_order_id' => $workOrder->getKey()]);
                $this->projection->project($deliverable);
            }

            return $workOrder;
        });
    }
}
