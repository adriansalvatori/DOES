<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Read-only "Historial" tab of the order detail modal.
 *
 * Lives in its own lazy component so the (large) timeline markup is not re-sent on every modal action.
 */
class OrderTimeline extends Component
{
    public int $orderId;

    /**
     * Re-render whenever something changes on the order.
     */
    #[On('order-updated')]
    public function refreshTimeline(): void {}

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-3 animate-pulse" aria-hidden="true">
            <div class="h-4 w-24 rounded bg-sunken"></div>
            <div class="h-24 rounded-card bg-sunken"></div>
        </div>
        HTML;
    }

    public function render(): View
    {
        return view('livewire.orders.order-timeline', [
            'order' => Order::with(['events', 'dueDateHistories'])->find($this->orderId),
        ]);
    }
}
