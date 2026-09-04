<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
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

    public function test_can_dismiss_subtask_from_modal(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON SUBTAREA',
            'task_name' => 'DISENO LOGO',
            'in_workspace' => true,
        ]);

        $subtask = $order->relatedTasks()->create([
            'title' => 'Subtarea a descartar por el usuario',
            'status' => 'todo',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('dismissTask', $subtask->id)
            ->assertDispatched('order-updated');

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $subtask->id,
        ]);
    }

    public function test_can_update_subtask_title_from_modal(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON SUBTAREA',
            'task_name' => 'DISENO BROCHURE',
            'in_workspace' => true,
        ]);

        $subtask = $order->relatedTasks()->create([
            'title' => 'Nombre Antiguo de Subtarea',
            'status' => 'todo',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskTitle', $subtask->id, 'Nuevo Nombre Editado')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'title' => 'Nuevo Nombre Editado',
        ]);
    }

    public function test_can_change_core_status_directly_from_dropdown(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CAMBIO ESTADO',
            'task_name' => 'DISENO AFICHE',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('changeCoreStatus', CoreStatus::EN_PRODUCCION->value)
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'core_status' => CoreStatus::EN_PRODUCCION->value,
        ]);
    }
}
