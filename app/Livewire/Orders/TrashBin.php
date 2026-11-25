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
            abort(403, __('You don\'t have permission to access the trash.'));
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

        session()->flash('message', __('Order \':company\' restored.', ['company' => $order->company_name]));
    }

    public function forceDeleteOrder(int $orderId): void
    {
        $user = Auth::user();
        if ($user && ! $user->canDeleteOrders()) {
            abort(403, __('You don\'t have permission to delete orders.'));
        }

        $order = Order::withTrashed()->findOrFail($orderId);
        $company = $order->company_name ?? __('Order');
        $order->forceDelete();

        session()->flash('message', __('Order \':company\' permanently deleted from the app and Trello.', ['company' => $company]));
    }

    public function emptyTrash(): void
    {
        $user = Auth::user();
        if ($user && ! $user->canDeleteOrders()) {
            abort(403, __('You don\'t have permission to empty the trash.'));
        }

        $trashedOrders = Order::onlyTrashed()->get();
        $count = $trashedOrders->count();

        foreach ($trashedOrders as $order) {
            $order->forceDelete();
        }

        session()->flash('message', trans_choice('{1} :count order permanently deleted from the trash and Trello.|[0,*] :count orders permanently deleted from the trash and Trello.', $count));
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
        ])->layout('components.layouts.app', ['title' => __('Trash — ').config('app.name')]);
    }
}
