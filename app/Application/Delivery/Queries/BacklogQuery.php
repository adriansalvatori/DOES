<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\BacklogFilters;
use App\Application\Delivery\Data\BacklogResult;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;

/**
 * Imported cards waiting to be pulled in. Only work orders flagged `in_backlog` appear here.
 */
class BacklogQuery
{
    public function run(BacklogFilters $filters = new BacklogFilters, int $perPage = 25): BacklogResult
    {
        $query = WorkOrder::query()
            ->where('in_backlog', true)
            ->with(['client', 'clientLocation', 'contact', 'deliverables' => fn ($deliverables) => $deliverables->with('assignee')->orderBy('id')])
            ->when(filled($filters->search), function (Builder $inner) use ($filters): void {
                $term = '%'.trim((string) $filters->search).'%';
                $inner->where(fn (Builder $any) => $any
                    ->where('number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('name', 'like', $term))
                    ->orWhereHas('contact', fn (Builder $contacts) => $contacts->where('name', 'like', $term))
                    ->orWhereHas('deliverables', fn (Builder $deliverables) => $deliverables
                        ->where('company_name', 'like', $term)
                        ->orWhere('task_name', 'like', $term)));
            })
            ->when($filters->status !== null, fn (Builder $inner) => $inner->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->inStatus($filters->status)))
            ->when($filters->assigneeId !== null, fn (Builder $inner) => $inner->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->assignedTo($filters->assigneeId)))
            ->when(filled($filters->company), fn (Builder $inner) => $inner->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->where('company_name', $filters->company)))
            ->when($filters->contactId !== null, fn (Builder $inner) => $inner->where('contact_id', $filters->contactId));

        match ($filters->sortBy) {
            'created_asc' => $query->orderBy('created_at')->orderBy('id'),
            'due_date_asc' => $query->orderByRaw('promised_date IS NULL')->orderBy('promised_date')->orderBy('id'),
            'company_asc' => $query->orderBy(
                Deliverable::query()->select('company_name')->whereColumn('orders.work_order_id', 'work_orders.id')->orderBy('id')->limit(1),
            )->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $backlogDeliverables = fn (): Builder => Deliverable::query()->whereHas('workOrder', fn (Builder $workOrders) => $workOrders->where('in_backlog', true));

        return new BacklogResult(
            workOrders: $query->paginate($perPage),
            totalInBacklog: WorkOrder::query()->where('in_backlog', true)->count(),
            companies: $backlogDeliverables()->whereNotNull('company_name')->where('company_name', '!=', '')
                ->distinct()->orderBy('company_name')->pluck('company_name'),
            contacts: Contact::query()->whereIn('id', WorkOrder::query()->where('in_backlog', true)->whereNotNull('contact_id')->select('contact_id'))
                ->orderBy('name')->get(),
        );
    }
}
