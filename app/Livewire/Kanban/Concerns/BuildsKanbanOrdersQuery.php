<?php

namespace App\Livewire\Kanban\Concerns;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filter-to-query mapping for the board and its column components.
 *
 * @property string $search
 * @property string $designerFilter
 * @property string $substatusFilter
 * @property string $companyFilter
 * @property string $responsibleFilter
 */
trait BuildsKanbanOrdersQuery
{
    /**
     * @return Builder<Order>
     */
    protected function ordersQuery(bool $withRelations = true): Builder
    {
        $query = Order::inWorkspace()->prioritizeUrgente();

        if ($withRelations) {
            $query->with(['designer', 'designers', 'relatedTasks', 'clientLocation', 'stages', 'elements', 'contact', 'client.contacts']);
        }

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        if ($this->designerFilter !== 'all') {
            $query->where(function ($q) {
                $q->where('designer_id', $this->designerFilter)
                    ->orWhereHas('designers', fn ($dq) => $dq->where('designers.id', $this->designerFilter));
            });
        }

        if ($this->substatusFilter !== 'all') {
            $query->where('substatus', $this->substatusFilter);
        }

        if ($this->companyFilter !== 'all') {
            $query->where('company_name', $this->companyFilter);
        }

        if ($this->responsibleFilter !== 'all') {
            $query->where('responsible_person', $this->responsibleFilter);
        }

        return $query;
    }
}
