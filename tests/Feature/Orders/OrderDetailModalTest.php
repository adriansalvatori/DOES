<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDetailModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_soft_delete_order_to_trashcan_from_flyout(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA PARA ELIMINAR',
            'task_name' => 'DISENO TARJETA',
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('deleteOrder')
            ->assertDispatched('order-updated');

        $this->assertSoftDeleted('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_can_clear_due_date_to_none_from_flyout(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON FECHA',
            'task_name' => 'DISENO BANNER',
            'current_due_date' => now()->addDays(3)->toDateString(),
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('clearDueDate')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'current_due_date' => null,
        ]);
    }
}
