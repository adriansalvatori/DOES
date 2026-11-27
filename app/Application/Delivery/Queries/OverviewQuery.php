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

/**
 * Gabriela's Excel-style screen: one row per work order, deliverables in an accordion. Admin and coordinator only
 * (the screen enforces it). The per-line note, date and checkmark columns still live on the deliverable row.
 */
class OverviewQuery
{
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
            ->orderByRaw('promised_date IS NULL')
            ->orderBy('promised_date')
            ->orderByDesc('id')
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
        );
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
            'production' => WorkOrderTabExpression::whereTab($query, WorkOrderTab::PRODUCTION),
            'done' => WorkOrderTabExpression::whereTab($query, WorkOrderTab::DONE),
            'urgent' => $query->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->active()
                ->whereJsonContains('flag_codes', DeliverableFlag::URGENT->value)),
            'with_due_date' => $query->whereNotNull('promised_date'),
            default => null,
        };

        $query
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
