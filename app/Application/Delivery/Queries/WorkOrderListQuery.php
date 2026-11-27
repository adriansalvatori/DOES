<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\PromiseHealth;
use App\Application\Delivery\Data\WorkOrderListRow;
use App\Application\Delivery\Data\WorkOrderTab;
use App\Application\Delivery\Queries\Support\WorkOrderTabExpression;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WorkOrderListQuery
{
    /**
     * @return LengthAwarePaginator<int, WorkOrderListRow>
     */
    public function run(
        WorkOrderTab $tab = WorkOrderTab::ACTIVE,
        ?string $search = null,
        ?int $clientId = null,
        int $perPage = 25,
        ?CarbonInterface $now = null,
    ): LengthAwarePaginator {
        $now ??= now();

        return $this->base($search, $clientId)
            ->tap(fn (Builder $query) => WorkOrderTabExpression::whereTab($query, $tab))
            ->with(['client', 'clientLocation'])
            ->withCount([
                'phases as phases_count',
                'deliverables as deliverables_count',
                'deliverables as open_count' => fn (Builder $deliverables) => $deliverables->active(),
                'deliverables as approved_count' => fn (Builder $deliverables) => $deliverables->whereHas(
                    'approvals',
                    fn (Builder $approvals) => $approvals->standing()->where('source', ApprovalSource::CLIENT->value),
                ),
                'deliverables as received_count' => fn (Builder $deliverables) => $deliverables->whereHas('receipts'),
            ])
            ->orderByRaw('promised_date IS NULL')
            ->orderBy('promised_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (WorkOrder $workOrder): WorkOrderListRow => new WorkOrderListRow(
                $workOrder,
                $tab,
                (int) $workOrder->deliverables_count,
                (int) $workOrder->open_count,
                (int) $workOrder->approved_count,
                (int) $workOrder->received_count,
                PromiseHealth::for($workOrder->promised_date, $workOrder->open_count > 0, $now),
            ));
    }

    /**
     * Number of work orders per tab for the current filters.
     *
     * @return array<string, int>
     */
    public function tabCounts(?string $search = null, ?int $clientId = null): array
    {
        $counts = $this->base($search, $clientId)
            ->toBase()
            ->select(DB::raw('('.WorkOrderTabExpression::sql().') as tab'), DB::raw('COUNT(*) as total'))
            ->groupBy('tab')
            ->pluck('total', 'tab');

        $result = [];
        foreach (WorkOrderTab::cases() as $tab) {
            $result[$tab->value] = (int) ($counts[$tab->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function base(?string $search, ?int $clientId): Builder
    {
        return WorkOrder::query()
            ->inWorkspace()
            ->when($clientId !== null, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.trim((string) $search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('name', 'like', $term))
                    ->orWhereHas('deliverables', fn (Builder $deliverables) => $deliverables
                        ->where('company_name', 'like', $term)
                        ->orWhere('task_name', 'like', $term)));
            });
    }
}
