<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\AutomationEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class ArchivedOrders extends Component
{
    public string $search = '';

    public string $designerFilter = 'all';

    public string $timeFilter = 'all'; // all, this_month, this_week

    public function archiveOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $previousStatus = $order->core_status;

        $order->update([
            'core_status' => CoreStatus::ARCHIVED,
            'archived_at' => now(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, CoreStatus::ARCHIVED);

        $this->dispatch('order-updated');
        session()->flash('message', "Orden '{$order->company_name}' archivada y cerrada exitosamente.");
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
            'actor' => 'User',
            'previous_value' => $previousStatus->value,
            'new_value' => CoreStatus::EN_PRODUCCION->value,
            'metadata' => ['comment' => 'Orden restaurada de Archivo a En Producción.'],
        ]);

        $this->dispatch('order-updated');
        session()->flash('message', "Orden '{$order->company_name}' reabierta y devuelta a En Producción.");
    }

    #[On('order-updated')]
    public function refreshView(): void
    {
        // Livewire listener to automatically re-render when orders are archived
    }

    public function render()
    {
        $query = Order::archived()->with(['designer', 'designers'])->orderByDesc('archived_at');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('task_name', 'like', "%{$this->search}%")
                    ->orWhere('wo_number', 'like', "%{$this->search}%")
                    ->orWhere('responsible_person', 'like', "%{$this->search}%");
            });
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

        $allArchived = $query->get();
        $totalArchivedCount = $allArchived->count();

        // Group archived orders by designer
        $designers = Designer::where('active', true)->get();
        $designerStats = [];

        foreach ($designers as $des) {
            $desOrders = $allArchived->filter(function ($o) use ($des) {
                return $o->designer_id == $des->id || $o->designers->contains('id', $des->id);
            });

            $desCount = $desOrders->count();
            $percentage = $totalArchivedCount > 0 ? round(($desCount / $totalArchivedCount) * 100, 1) : 0;
            $avgDays = $desCount > 0 ? round($desOrders->avg('days_to_close'), 1) : 0;
            $totalRevisions = (int) $desOrders->sum('client_revision_count');

            $designerStats[] = [
                'designer' => $des,
                'count' => $desCount,
                'percentage' => $percentage,
                'avg_days' => $avgDays,
                'total_revisions' => $totalRevisions,
                'orders' => $desOrders,
            ];
        }

        // Unassigned archived orders
        $unassignedOrders = $allArchived->filter(function ($o) {
            return is_null($o->designer_id) && $o->designers->isEmpty();
        });

        // Orders currently in production for right-side column
        $inProductionOrders = Order::inWorkspace()
            ->where('core_status', CoreStatus::EN_PRODUCCION)
            ->with(['designer', 'designers'])
            ->orderByDesc('updated_at')
            ->get();

        // Global Turnaround Average
        $globalAvgTurnaround = $totalArchivedCount > 0 ? round($allArchived->avg('days_to_close'), 1) : 0;

        return view('livewire.orders.archived-orders', [
            'archivedOrders' => $allArchived,
            'totalArchivedCount' => $totalArchivedCount,
            'designerStats' => $designerStats,
            'unassignedOrders' => $unassignedOrders,
            'inProductionOrders' => $inProductionOrders,
            'designers' => $designers,
            'globalAvgTurnaround' => $globalAvgTurnaround,
        ])->layout('components.layouts.app', ['title' => 'Órdenes Archivadas & Rendimiento - Kudos Design Ops']);
    }
}
