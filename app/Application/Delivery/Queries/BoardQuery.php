<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\BoardColumn;
use App\Application\Delivery\Data\BoardFilters;
use App\Application\Delivery\Data\BoardResult;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BoardQuery
{
    /**
     * Blocked cards are never shown to designers. Each column returns at most its cap in cards plus its real total.
     *
     * @param  int|array<string, int>  $columnCap  One cap for every column, or caps by status code (25 when a status has none).
     */
    public function run(User $viewer, Area $area, BoardFilters $filters = new BoardFilters, int|array $columnCap = BoardLimits::DEFAULT_COLUMN_CAP): BoardResult
    {
        $statuses = DeliverableStatus::inArea($area);

        $totals = $this->scoped($viewer, $filters)
            ->inStatus($statuses)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $columns = [];
        foreach ($statuses as $status) {
            $total = (int) ($totals[$status->value] ?? 0);
            $cards = $total === 0
                ? collect()
                : $this->scoped($viewer, $filters)
                    ->inStatus($status)
                    ->with(['workOrder.client', 'workOrder.clientLocation', 'assignee', 'reviewer', 'openBlock', 'openHold', 'tasks' => fn ($tasks) => $tasks->orderBy('id')])
                    ->withCount(['tasks', 'tasks as tasks_done_count' => fn (Builder $tasks) => $tasks->where('status', 'done'), 'samples as active_samples_count' => fn (Builder $samples) => $samples->active()])
                    ->when(
                        $status === DeliverableStatus::DONE,
                        fn (Builder $query) => $query->orderByDesc('closed_at'),
                        fn (Builder $query) => $query->orderByRaw('due_target IS NULL')->orderBy('due_target'),
                    )
                    ->orderBy('id')
                    ->limit(is_array($columnCap) ? ($columnCap[$status->value] ?? BoardLimits::DEFAULT_COLUMN_CAP) : $columnCap)
                    ->get();

            $columns[] = new BoardColumn($status, $total, $cards);
        }

        return new BoardResult($area, $columns);
    }

    /**
     * @return Builder<Deliverable>
     */
    private function scoped(User $viewer, BoardFilters $filters): Builder
    {
        return Deliverable::query()
            ->inWorkspace()
            ->when($viewer->isDesigner(), fn (Builder $query) => $query->notBlocked())
            ->when($filters->assigneeId !== null, fn (Builder $query) => $query->assignedTo($filters->assigneeId))
            ->when($filters->clientId !== null, fn (Builder $query) => $query->whereHas(
                'workOrder',
                fn (Builder $workOrders) => $workOrders->where('client_id', $filters->clientId),
            ))
            ->when($filters->flags !== [], function (Builder $query) use ($filters): void {
                foreach ($filters->flags as $flag) {
                    $query->whereJsonContains('flag_codes', $flag->value);
                }
            })
            ->when(filled($filters->search), function (Builder $query) use ($filters): void {
                $term = '%'.trim((string) $filters->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('company_name', 'like', $term)
                    ->orWhere('task_name', 'like', $term)
                    ->orWhereHas('workOrder', fn (Builder $workOrders) => $workOrders
                        ->where('number', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('description', 'like', $term)));
            });
    }
}
