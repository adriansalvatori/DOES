<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TrashBin extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('No tiene permisos para acceder a la papelera.'));
        }
    }

    public function restoreOrder(int $orderId): void
    {
        $order = Order::withTrashed()->findOrFail($orderId);
        $order->restore();

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_RESTORED',
            'actor' => auth()->user()?->name ?? 'Usuario',
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
            $query->search($this->search);
        }

        $trashedOrders = $query->latest('deleted_at')->get();

        return view('livewire.orders.trash-bin', [
            'trashedOrders' => $trashedOrders,
        ])->layout('components.layouts.app', ['title' => __('Papelera — ').config('app.name')]);
    }
}
