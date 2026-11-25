<?php

namespace App\Livewire\Kanban;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Kanban\Concerns\BuildsKanbanOrdersQuery;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One Kanban column. Re-renders on its own so a change to one card never
 * re-sends the other columns.
 */
class Column extends Component
{
    use BuildsKanbanOrdersQuery;

    public const PAGE_SIZE = 20;

    public string $status;

    public string $search = '';

    public string $designerFilter = 'all';

    public string $substatusFilter = 'all';

    public string $companyFilter = 'all';

    public string $responsibleFilter = 'all';

    public bool $showStandaloneTaskCards = false;

    public int $pagesShown = 1;

    /**
     * Columns that can hold hundreds of cards render a page at a time.
     */
    public static function isCapped(CoreStatus $column): bool
    {
        return in_array($column, [CoreStatus::ON_HOLD, CoreStatus::EN_PRODUCCION], true);
    }

    public function showMore(): void
    {
        $this->pagesShown++;
    }

    /**
     * @param  array{search: string, designerFilter: string, substatusFilter: string, companyFilter: string, responsibleFilter: string, showStandaloneTaskCards: bool}  $filters
     */
    #[On('kanban-filters-changed')]
    public function applyFilters(array $filters): void
    {
        $this->fill($filters);
        $this->pagesShown = 1;
        unset($this->relatedTasks);
    }

    #[On('kanban-column-refresh.{status}')]
    public function refreshColumn(): void
    {
        // The event itself triggers the re-render of this column.
    }

    public function toggleDoneToday(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $newDoneToday = ! $order->done_today;
        $order->update(['done_today' => $newDoneToday]);
        if ($newDoneToday) {
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }

        $this->dispatch('order-updated');
    }

    #[Computed]
    public function column(): CoreStatus
    {
        return CoreStatus::from($this->status);
    }

    #[Computed]
    public function relatedTasks(): Collection
    {
        if (! $this->showStandaloneTaskCards) {
            return collect();
        }

        $tasksQuery = RelatedTask::whereHas('order', function ($q) {
            $q->inWorkspace()->whereNotIn('core_status', [CoreStatus::ARCHIVED->value, CoreStatus::ON_HOLD->value]);
            if ($this->designerFilter !== 'all') {
                $q->where('designer_id', $this->designerFilter);
            }
        })->with(['order', 'assignee']);

        if (! empty($this->search)) {
            $tasksQuery->search($this->search);
        }

        return $tasksQuery->get();
    }

    public function render()
    {
        $column = $this->column;
        $query = $this->ordersQuery()->where('core_status', $column->value);

        if (self::isCapped($column)) {
            $totalOrders = (clone $query)->toBase()->getCountForPagination();
            $orders = $query->limit(self::PAGE_SIZE * $this->pagesShown)->get();
        } else {
            $orders = $query->get();
            $totalOrders = $orders->count();
        }

        $columnTasks = match ($column) {
            CoreStatus::TO_DO_TODAY => $this->relatedTasks->filter(fn ($t) => $t->order !== null && ! $t->isDone() && $t->type !== RelatedTaskType::BLOCKED),
            CoreStatus::ENTRANTE => $this->relatedTasks->filter(fn ($t) => $t->order !== null && ! $t->isDone() && ($t->type === RelatedTaskType::BLOCKED || $t->type === RelatedTaskType::RESOLVER)),
            default => collect(),
        };

        return view('livewire.kanban.column', [
            'column' => $column,
            'columnOrders' => $orders,
            'columnTasks' => $columnTasks,
            'totalItemCount' => $totalOrders + $columnTasks->count(),
            'hiddenCount' => max(0, $totalOrders - $orders->count()),
            'pageSize' => self::PAGE_SIZE,
        ]);
    }
}
