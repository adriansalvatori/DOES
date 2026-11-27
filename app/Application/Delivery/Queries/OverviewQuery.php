<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Data\OverviewRow;
use App\Application\Delivery\Data\PromiseHealth;
use App\Application\Delivery\Data\WorkOrderTab;
use App\Application\Delivery\Queries\Support\WorkOrderTabExpression;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gabriela's Excel-style screen: one row per work order, deliverables in an accordion, newest WO first. Admin and
 * coordinator only (the screen enforces it). The per-line note, date and checkmark columns still live on the
 * deliverable row.
 */
class OverviewQuery
{
    public const TABS = ['all', 'active', 'production', 'done', 'backlog'];

    /**
     * Newest WO number first, lines without a WO last (the order Gabriela's screen always had).
     */
    private const NEWEST_WORK_ORDER_FIRST = "CASE WHEN number IS NULL OR number = '' THEN 1 ELSE 0 END, CAST(REPLACE(UPPER(number), 'WO', '') AS INTEGER) DESC, id DESC";

    /**
     * Line fields that live on each deliverable; the line shows one value for all of them.
     */
    private const LINE_FIELDS = ['production_processed_at', 'email_date', 'production_note', 'delivery_note', 'review_status', 'overview_checked'];

    /**
     * @return LengthAwarePaginator<int, OverviewRow>
     */
    public function run(OverviewFilters $filters = new OverviewFilters, int $perPage = 50, ?CarbonInterface $now = null): LengthAwarePaginator
    {
        $now ??= now();

        $backlogTab = $filters->tab === 'backlog';

        return WorkOrder::query()
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->with(['client', 'clientLocation', 'contact', 'deliverables' => fn ($deliverables) => $deliverables
                ->when($backlogTab, fn (Builder $inner) => $inner->inBacklog(), fn (Builder $inner) => $inner->inWorkspace())
                ->with(['assignee', 'openBlock', 'openHold'])
                ->orderBy('id')])
            ->orderByRaw(self::NEWEST_WORK_ORDER_FIRST)
            ->paginate($perPage)
            ->through(fn (WorkOrder $workOrder): OverviewRow => $this->row($workOrder, $now));
    }

    private function row(WorkOrder $workOrder, CarbonInterface $now): OverviewRow
    {
        $deliverables = $workOrder->deliverables;
        $latest = fn (string $attribute): ?string => $deliverables
            ->map(fn (Deliverable $deliverable): ?string => $this->dateAttribute($deliverable, $attribute))
            ->filter()->sort()->last();
        $firstNote = fn (string $attribute): ?string => $deliverables
            ->map(fn (Deliverable $deliverable) => $deliverable->getAttribute($attribute))
            ->first(fn ($value): bool => filled($value));

        return new OverviewRow(
            workOrder: $workOrder,
            deliverables: $deliverables,
            processedAt: $latest('production_processed_at'),
            emailDate: $latest('email_date'),
            productionNote: $firstNote('production_note'),
            deliveryNote: $firstNote('delivery_note'),
            designers: $deliverables->pluck('assignee.name')->filter()->unique()->values()->all(),
            flags: $deliverables->flatMap(fn (Deliverable $deliverable) => $deliverable->flag_codes ?? [])
                ->unique(fn (DeliverableFlag $flag): string => $flag->value)->values()->all(),
            statusCounts: $deliverables->countBy(fn (Deliverable $deliverable): string => ($deliverable->status?->value) ?? 'intake')->all(),
            checked: $deliverables->isNotEmpty() && $deliverables->every(fn (Deliverable $deliverable): bool => (bool) $deliverable->getAttribute('overview_checked')),
            health: PromiseHealth::for(
                $workOrder->promised_date,
                $deliverables->contains(fn (Deliverable $deliverable): bool => ! $deliverable->status?->isClosed()),
                $now,
            ),
            reviewStatus: $firstNote('review_status'),
            varies: $this->varyingFields($deliverables),
        );
    }

    /**
     * The line fields where the deliverables shown do not all hold the same value.
     *
     * @param  Collection<int, Deliverable>  $deliverables
     * @return list<string>
     */
    private function varyingFields(Collection $deliverables): array
    {
        if ($deliverables->count() < 2) {
            return [];
        }

        return collect(self::LINE_FIELDS)
            ->filter(fn (string $field): bool => $deliverables
                ->map(fn (Deliverable $deliverable): string => $field === 'overview_checked'
                    ? (string) (int) (bool) $deliverable->getAttribute($field)
                    : (string) ($this->dateAttribute($deliverable, $field) ?? ''))
                ->unique()->count() > 1)
            ->values()->all();
    }

    /**
     * How many work orders each tab holds under the other filters.
     *
     * @return array<string, int>
     */
    public function tabCounts(OverviewFilters $filters): array
    {
        $workspace = WorkOrder::query()->tap(fn (Builder $query) => $this->applyFilters($query, $filters->onTab('all')));
        $byTab = $workspace->toBase()
            ->selectRaw('('.WorkOrderTabExpression::sql().') as tab, COUNT(*) as aggregate')
            ->groupBy('tab')
            ->pluck('aggregate', 'tab')
            ->map(fn ($count): int => (int) $count);

        return [
            'all' => (int) $byTab->sum(),
            'active' => $byTab->get('active', 0),
            'production' => $byTab->get(WorkOrderTab::PRODUCTION->value, 0) + $byTab->get(WorkOrderTab::FULFILLMENT->value, 0),
            'done' => $byTab->get('done', 0),
            'backlog' => WorkOrder::query()->tap(fn (Builder $query) => $this->applyFilters($query, $filters->onTab('backlog')))->count(),
        ];
    }

    /**
     * A fingerprint per work order that changes whenever it or one of its deliverables is saved, so an open
     * Overview can tell which lines someone else changed.
     *
     * @param  list<int>  $workOrderIds
     * @return array<int, string>
     */
    public function versions(array $workOrderIds): array
    {
        if ($workOrderIds === []) {
            return [];
        }

        $deliverables = DB::table('orders')
            ->whereIn('work_order_id', $workOrderIds)
            ->whereNull('deleted_at')
            ->groupBy('work_order_id')
            ->selectRaw('work_order_id, MAX(updated_at) as touched, COUNT(*) as total')
            ->get()
            ->keyBy('work_order_id');

        return DB::table('work_orders')
            ->whereIn('id', $workOrderIds)
            ->pluck('updated_at', 'id')
            ->mapWithKeys(fn ($touched, $id): array => [(int) $id => $touched.'|'.($deliverables[$id]->touched ?? '').'|'.($deliverables[$id]->total ?? 0)])
            ->all();
    }

    private function dateAttribute(Deliverable $deliverable, string $attribute): ?string
    {
        $value = $deliverable->getAttribute($attribute);

        return filled($value) ? substr((string) $value, 0, 10) : null;
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    private function applyFilters(Builder $query, OverviewFilters $filters): void
    {
        if ($filters->tab === 'backlog') {
            $query->inBacklog();
        } else {
            $query->inWorkspace();
        }

        match ($filters->tab) {
            'active' => WorkOrderTabExpression::whereTab($query, WorkOrderTab::ACTIVE),
            'production' => $query->whereRaw('('.WorkOrderTabExpression::sql().') IN (?, ?)', [WorkOrderTab::PRODUCTION->value, WorkOrderTab::FULFILLMENT->value]),
            'done' => WorkOrderTabExpression::whereTab($query, WorkOrderTab::DONE),
            default => null,
        };

        $query
            ->when($filters->urgent, fn (Builder $inner) => $inner->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->active()
                ->whereJsonContains('flag_codes', DeliverableFlag::URGENT->value)))
            ->when($filters->withDueDate, fn (Builder $inner) => $inner->whereNotNull('promised_date'))
            ->when($filters->review === 'NONE', fn (Builder $inner) => $inner->whereDoesntHave(
                'deliverables',
                fn (Builder $deliverables) => $deliverables->whereNotNull('review_status'),
            ))
            ->when(filled($filters->review) && $filters->review !== 'NONE', fn (Builder $inner) => $inner->whereHas(
                'deliverables',
                fn (Builder $deliverables) => $deliverables->where('review_status', $filters->review),
            ))
            ->when($filters->workOrderId !== null, fn (Builder $inner) => $inner->whereKey($filters->workOrderId))
            ->when(filled($filters->workOrderNumber), fn (Builder $inner) => $inner->where('number', 'like', '%'.trim((string) $filters->workOrderNumber).'%'))
            ->when($filters->clientId !== null, fn (Builder $inner) => $inner->where('client_id', $filters->clientId))
            ->when($filters->assigneeId !== null, fn (Builder $inner) => $inner->whereHas(
                'deliverables',
                fn (Builder $deliverables) => $deliverables->assignedTo($filters->assigneeId),
            ))
            ->when($filters->installation === 'NONE', fn (Builder $inner) => $inner->where(
                fn (Builder $none) => $none->whereNull('installation_types')->orWhere('installation_types', '[]'),
            ))
            ->when(filled($filters->installation) && $filters->installation !== 'NONE', fn (Builder $inner) => $inner->whereJsonContains('installation_types', $filters->installation))
            ->when(filled($filters->search), function (Builder $inner) use ($filters): void {
                $term = '%'.trim((string) $filters->search).'%';
                $inner->where(fn (Builder $any) => $any
                    ->where('number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('estimate_invoice_number', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('name', 'like', $term))
                    ->orWhereHas('deliverables', fn (Builder $deliverables) => $deliverables
                        ->where('company_name', 'like', $term)
                        ->orWhere('task_name', 'like', $term)));
            });
    }
}
