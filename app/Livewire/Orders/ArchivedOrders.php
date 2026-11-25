<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Substatus as SubstatusModel;
use App\Services\AutomationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ArchivedOrders extends Component
{
    use WithPagination;

    private const ORDERS_PER_PAGE = 25;

    public string $search = '';

    public string $designerFilter = 'all';

    public string $timeFilter = 'all'; // all, this_month, this_week

    public string $substatusFilter = 'all'; // all, finalizada, cancelada, no_responsive

    public int $productionLimit = 25;

    public bool $showArchiveModal = false;

    public ?int $pendingArchiveOrderId = null;

    public string $archiveSubstatus = 'FINALIZADA !';

    public function updateSubstatus(int $orderId, string $substatusValue): void
    {
        $order = Order::findOrFail($orderId);
        $previousSub = $order->substatus;
        $subEnum = Substatus::tryFrom($substatusValue) ?? $substatusValue;

        $order->update(['substatus' => $subEnum]);

        $prevSubVal = $previousSub instanceof Substatus ? $previousSub->value : (string) $previousSub;
        $newSubVal = $subEnum instanceof Substatus ? $subEnum->value : (string) $subEnum;
        $subLabel = $subEnum instanceof Substatus ? $subEnum->label() : $substatusValue;

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'SUBSTATUS_CHANGED',
            'actor' => auth()->user()?->name ?? __('User'),
            'previous_value' => $prevSubVal ?: null,
            'new_value' => $newSubVal ?: null,
            'metadata' => ['description' => __('Substatus updated to: :status', ['status' => $subLabel])],
        ]);

        $this->dispatch('order-updated');
        session()->flash('message', __('Substatus of order \':company\' updated.', ['company' => $order->company_name]));
    }

    public function archiveOrder(int $orderId): void
    {
        $this->pendingArchiveOrderId = $orderId;
        $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();
        $this->showArchiveModal = true;
    }

    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
        $this->pendingArchiveOrderId = null;
        $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();
    }

    public function confirmArchive(): void
    {
        if (! $this->pendingArchiveOrderId) {
            return;
        }

        $order = Order::findOrFail($this->pendingArchiveOrderId);
        $previousStatus = $order->core_status;
        $subEnum = Substatus::tryFrom($this->archiveSubstatus) ?? $this->archiveSubstatus;

        $order->update([
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => $subEnum,
            'archived_at' => now(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, CoreStatus::ARCHIVED);

        $this->showArchiveModal = false;
        $this->pendingArchiveOrderId = null;
        $this->dispatch('order-updated');
        session()->flash('message', __('Order \':company\' archived and closed.', ['company' => $order->company_name]));
    }

    public function restoreOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $previousStatus = $order->core_status;

        $order->update([
            'core_status' => CoreStatus::EN_PRODUCCION,
            'archived_at' => null,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => auth()->user()?->name ?? __('User'),
            'previous_value' => $previousStatus->value,
            'new_value' => CoreStatus::EN_PRODUCCION->value,
            'metadata' => ['comment' => __('Order restored from Archive to In Production.')],
        ]);

        $this->dispatch('order-updated');
        session()->flash('message', __('Order \':company\' reopened and returned to In Production.', ['company' => $order->company_name]));
    }

    #[On('order-updated')]
    public function refreshView(): void
    {
        // Livewire listener to automatically re-render when orders are archived
    }

    public function showMoreProduction(): void
    {
        $this->productionLimit += 25;
    }

    /**
     * Resets every per-designer paginator when a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'designerFilter', 'timeFilter', 'substatusFilter'], true)) {
            $this->paginators = [];
        }
    }

    /**
     * Archived orders matching the page filters (without designer grouping).
     *
     * @return Builder<Order>
     */
    protected function filteredArchivedQuery(): Builder
    {
        $query = Order::archived();

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        if ($this->designerFilter !== 'all') {
            $query->where(function ($q) {
                $q->where('designer_id', $this->designerFilter)
                    ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->designerFilter));
            });
        }

        if ($this->timeFilter === 'this_month') {
            $query->where('archived_at', '>=', now()->startOfMonth());
        } elseif ($this->timeFilter === 'this_week') {
            $query->where('archived_at', '>=', now()->startOfWeek());
        }

        if ($this->substatusFilter !== 'all') {
            match ($this->substatusFilter) {
                'finalizada' => $query->where('substatus', Substatus::FINALIZADA->value),
                'cancelada' => $query->whereIn('substatus', [
                    Substatus::CANCELADA->value,
                    Substatus::CANCELADA_POR_CLIENTE->value,
                    Substatus::CANCELADA_POR_CAMILA->value,
                    Substatus::NO_REALIZADA_TRANSFERIDA->value,
                ]),
                'no_responsive' => $query->where('substatus', Substatus::CLIENTE_NO_RESPONSIVE->value),
                default => null,
            };
        }

        return $query;
    }

    /**
     * SQL expression equal to Order::days_to_close (whole days from start to archive, never negative).
     */
    protected function daysToCloseSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'MAX(0, CAST(julianday(date(archived_at)) - julianday(date(COALESCE(start_date, created_at))) AS INTEGER))';
        }

        return 'GREATEST(0, DATEDIFF(DATE(archived_at), DATE(COALESCE(start_date, created_at))))';
    }

    /**
     * @return array{count: int, avg_days: float, revisions: int}
     */
    protected function aggregateFor(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as total, AVG(CASE WHEN archived_at IS NOT NULL THEN '.$this->daysToCloseSql().' END) as avg_days, COALESCE(SUM(client_revision_count), 0) as revisions')
            ->reorder()
            ->first();

        return [
            'count' => (int) $row->total,
            'avg_days' => round((float) $row->avg_days, 1),
            'revisions' => (int) $row->revisions,
        ];
    }

    public function render()
    {
        $global = $this->aggregateFor($this->filteredArchivedQuery());
        $totalArchivedCount = $global['count'];

        $substatusCounts = Order::archived()
            ->selectRaw('substatus, COUNT(*) as total')
            ->groupBy('substatus')
            ->pluck('total', 'substatus');
        $countSubstatuses = fn (array $substatuses) => (int) collect($substatuses)->sum(fn (Substatus $sub) => $substatusCounts[$sub->value] ?? 0);
        $successfulCount = $countSubstatuses([Substatus::FINALIZADA]);
        $canceledCount = $countSubstatuses([
            Substatus::CANCELADA,
            Substatus::CANCELADA_POR_CLIENTE,
            Substatus::CANCELADA_POR_CAMILA,
            Substatus::NO_REALIZADA_TRANSFERIDA,
        ]);
        $nonResponsiveCount = $countSubstatuses([Substatus::CLIENTE_NO_RESPONSIVE]);

        $designers = Designer::where('active', true)->internal()->get();
        $designerStats = [];

        foreach ($designers as $des) {
            $designerQuery = fn () => $this->filteredArchivedQuery()->where(function ($q) use ($des) {
                $q->where('designer_id', $des->id)
                    ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $des->id));
            });

            $aggregate = $this->aggregateFor($designerQuery());

            $designerStats[] = [
                'designer' => $des,
                'count' => $aggregate['count'],
                'percentage' => $totalArchivedCount > 0 ? round(($aggregate['count'] / $totalArchivedCount) * 100, 1) : 0,
                'avg_days' => $aggregate['avg_days'],
                'total_revisions' => $aggregate['revisions'],
                'orders' => $aggregate['count'] > 0
                    ? $designerQuery()->with('clientLocation')->orderByDesc('archived_at')->paginate(self::ORDERS_PER_PAGE, ['*'], 'designer'.$des->id)
                    : collect(),
            ];
        }

        $inProductionOrders = Order::inWorkspace()
            ->where('core_status', CoreStatus::EN_PRODUCCION)
            ->with(['designer'])
            ->orderByDesc('updated_at')
            ->get();

        return view('livewire.orders.archived-orders', [
            'totalArchivedCount' => $totalArchivedCount,
            'successfulCount' => $successfulCount,
            'canceledCount' => $canceledCount,
            'nonResponsiveCount' => $nonResponsiveCount,
            'designerStats' => $designerStats,
            'inProductionOrders' => $inProductionOrders,
            'designers' => $designers,
            'globalAvgTurnaround' => $global['avg_days'],
            'archivedSubstatuses' => SubstatusModel::getArchivedSubstatuses(),
            'pendingArchiveOrder' => $this->pendingArchiveOrderId ? Order::find($this->pendingArchiveOrderId) : null,
        ])->layout('components.layouts.app', ['title' => __('Archived orders').' - '.config('app.name')]);
    }
}
