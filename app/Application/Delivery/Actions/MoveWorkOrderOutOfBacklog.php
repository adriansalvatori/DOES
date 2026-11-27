<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes the deliverables of a work order that still wait in the backlog out of it. They keep their status and start
 * showing up in the daily screens, and their tasks get inferred Fibonacci points; deliverables already in the workspace
 * are left alone.
 */
class MoveWorkOrderOutOfBacklog
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection, private PointsEstimator $points) {}

    public function __invoke(WorkOrder $workOrder, ?User $actor = null): WorkOrder
    {
        $this->authorizeActor($actor, 'createWorkOrder', Deliverable::class);

        return DB::transaction(function () use ($workOrder, $actor): WorkOrder {
            $workOrder->refresh();

            $waiting = $workOrder->deliverables()->inBacklog()->get();

            if ($waiting->isEmpty()) {
                throw WorkflowException::refused('This work order is not in the backlog.');
            }

            foreach ($waiting as $deliverable) {
                $deliverable->forceFill(['in_backlog' => false])->save();
                $this->points->fillTasks([$deliverable->getKey()]);
                $this->log->record($deliverable, 'moved_out_of_backlog', $actor, null, null, ['work_order_id' => $workOrder->getKey()]);
                $this->projection->project($deliverable);
            }

            return $workOrder;
        });
    }
}
