<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\BatchReadiness;
use App\Application\Delivery\Data\PhaseGroup;
use App\Application\Delivery\Data\PromiseHealth;
use App\Application\Delivery\Data\WorkOrderPage;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkOrderPageQuery
{
    public function run(WorkOrder|int $workOrder, ?CarbonInterface $now = null): WorkOrderPage
    {
        $now ??= now();
        $id = $workOrder instanceof WorkOrder ? $workOrder->getKey() : $workOrder;

        $workOrder = WorkOrder::query()
            ->with(['client', 'clientLocation', 'contact', 'phases'])
            ->findOrFail($id);

        /** @var Collection<int, Deliverable> $deliverables */
        $deliverables = $workOrder->deliverables()
            ->with(['assignee', 'reviewer', 'approvals', 'openBlock.owner', 'openHold', 'receipts'])
            ->withCount(['tasks', 'tasks as tasks_done_count' => fn (Builder $tasks) => $tasks->where('status', 'done')])
            ->get()
            ->sortBy(fn (Deliverable $deliverable): array => [$deliverable->status?->position() ?? 0, $deliverable->id])
            ->values();

        $groups = [];
        if ($workOrder->phases->isEmpty()) {
            $groups[] = new PhaseGroup(null, $deliverables, BatchReadiness::evaluate($deliverables));
        } else {
            foreach ($workOrder->phases as $phase) {
                $scope = $deliverables->where('phase_id', $phase->getKey())->values();
                $groups[] = new PhaseGroup($phase, $scope, BatchReadiness::evaluate($scope));
            }
            $loose = $deliverables->whereNull('phase_id')->values();
            if ($loose->isNotEmpty()) {
                $groups[] = new PhaseGroup(null, $loose, BatchReadiness::evaluate($loose));
            }
        }

        $alerts = Alert::query()->open()
            ->where(fn (Builder $query) => $query
                ->where('work_order_id', $workOrder->getKey())
                ->orWhereIn('deliverable_id', $deliverables->modelKeys()))
            ->with('recipient')
            ->orderBy('due_on')
            ->get();

        $approved = $deliverables->filter(fn (Deliverable $deliverable): bool => $deliverable->approvals->contains(
            fn ($approval): bool => $approval->source === ApprovalSource::CLIENT && $approval->reset_at === null,
        ))->count();

        $received = $deliverables->filter(fn (Deliverable $deliverable): bool => $deliverable->receipts->isNotEmpty())->count();

        return new WorkOrderPage(
            workOrder: $workOrder,
            groups: $groups,
            alerts: $alerts,
            leftBehind: $deliverables->whereIn('id', collect($groups)->flatMap(fn (PhaseGroup $group) => $group->readiness->leftBehind->modelKeys())->all())->values(),
            deliverablesCount: $deliverables->count(),
            approvedCount: $approved,
            receivedCount: $received,
            health: PromiseHealth::for(
                $workOrder->promised_date,
                $deliverables->contains(fn (Deliverable $deliverable): bool => ! $deliverable->status?->isClosed()),
                $now,
            ),
        );
    }
}
