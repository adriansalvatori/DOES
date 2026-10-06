<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Substatus as SubstatusModel;
use App\Services\AutomationEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class ArchivedOrders extends Component
{
    public string $search = '';

    public string $designerFilter = 'all';

    public string $timeFilter = 'all'; // all, this_month, this_week

    public string $substatusFilter = 'all'; // all, finalizada, cancelada, no_responsive

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
            'actor' => auth()->user()?->name ?? 'Usuario',
            'previous_value' => $prevSubVal ?: null,
            'new_value' => $newSubVal ?: null,
            'metadata' => ['description' => __('Subestatus actualizado a: :status', ['status' => $subLabel])],
        ]);

        $this->dispatch('order-updated');
        session()->flash('message', "Subestatus de la orden '{$order->company_name}' actualizado.");
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
            'actor' => auth()->user()?->name ?? 'Usuario',
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

        $allArchived = $query->get();
        $totalArchivedCount = $allArchived->count();

        // Calculate substatus breakdown counts across all archived orders
        $unfilteredArchived = Order::archived()->get();
        $successfulCount = $unfilteredArchived->filter(fn ($o) => $o->substatus === Substatus::FINALIZADA || ($o->substatus?->value ?? '') === Substatus::FINALIZADA->value)->count();
        $canceledCount = $unfilteredArchived->filter(fn ($o) => in_array($o->substatus, [
            Substatus::CANCELADA,
            Substatus::CANCELADA_POR_CLIENTE,
            Substatus::CANCELADA_POR_CAMILA,
            Substatus::NO_REALIZADA_TRANSFERIDA,
        ], true) || in_array($o->substatus?->value ?? '', [
            Substatus::CANCELADA->value,
            Substatus::CANCELADA_POR_CLIENTE->value,
            Substatus::CANCELADA_POR_CAMILA->value,
            Substatus::NO_REALIZADA_TRANSFERIDA->value,
        ], true))->count();
        $nonResponsiveCount = $unfilteredArchived->filter(fn ($o) => $o->substatus === Substatus::CLIENTE_NO_RESPONSIVE || ($o->substatus?->value ?? '') === Substatus::CLIENTE_NO_RESPONSIVE->value)->count();

        // Group archived orders by designer
        $designers = Designer::where('active', true)->internal()->get();
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
            'successfulCount' => $successfulCount,
            'canceledCount' => $canceledCount,
            'nonResponsiveCount' => $nonResponsiveCount,
            'designerStats' => $designerStats,
            'unassignedOrders' => $unassignedOrders,
            'inProductionOrders' => $inProductionOrders,
            'designers' => $designers,
            'globalAvgTurnaround' => $globalAvgTurnaround,
            'archivedSubstatuses' => SubstatusModel::getArchivedSubstatuses(),
            'pendingArchiveOrder' => $this->pendingArchiveOrderId ? Order::find($this->pendingArchiveOrderId) : null,
        ])->layout('components.layouts.app', ['title' => __('Órdenes Archivadas & Rendimiento - ').config('app.name')]);
    }
}
