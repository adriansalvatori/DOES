<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Models\OrderEvent;
use Livewire\Component;

class TrashBin extends Component
{
    public string $search = '';

    public function restoreOrder(int $orderId): void
    {
        $order = Order::withTrashed()->findOrFail($orderId);
        $order->restore();

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_RESTORED',
            'actor' => 'User',
            'previous_value' => 'TRASHED',
            'new_value' => $order->core_status?->value,
            'metadata' => ['comment' => 'Orden restaurada desde la papelera.'],
        ]);

        session()->flash('message', "Orden '{$order->company_name}' restaurada correctamente.");
    }

    public function forceDeleteOrder(int $orderId): void
    {
        $order = Order::withTrashed()->findOrFail($orderId);
        $company = $order->company_name;
        $order->forceDelete();

        session()->flash('message', "Orden '{$company}' eliminada permanentemente.");
    }

    public function render()
    {
        $query = Order::onlyTrashed();

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('task_name', 'like', "%{$this->search}%")
                    ->orWhere('wo_number', 'like', "%{$this->search}%");
            });
        }

        $trashedOrders = $query->latest('deleted_at')->get();

        return view('livewire.orders.trash-bin', [
            'trashedOrders' => $trashedOrders,
        ])->layout('components.layouts.app', ['title' => 'Papelera — Kudos Design Ops']);
    }
}
