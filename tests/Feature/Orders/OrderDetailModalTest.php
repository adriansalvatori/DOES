<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Client;
use App\Models\Designer;
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

    public function test_can_open_client_detail_when_client_already_linked(): void
    {
        $client = Client::create([
            'name' => 'CLIENTE EXISTENTE',
        ]);

        $order = Order::create([
            'company_name' => 'CLIENTE EXISTENTE',
            'client_id' => $client->id,
            'task_name' => 'DISENO CATALOGO',
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openClientDetail')
            ->assertDispatched('open-client-flyout', clientId: $client->id);
    }

    public function test_can_open_client_detail_matching_client_from_company_name(): void
    {
        $order = Order::create([
            'company_name' => 'NUEVA TAQUERIA LA FLOR',
            'task_name' => 'DISENO MENU',
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openClientDetail')
            ->assertDispatched('open-client-flyout');

        $this->assertDatabaseHas('clients', [
            'name' => 'NUEVA TAQUERIA LA FLOR',
        ]);

        $order->refresh();
        $this->assertNotNull($order->client_id);
    }

    public function test_can_sort_client_other_active_orders_by_fields(): void
    {
        $client = Client::create(['name' => 'CLIENTE MULTI ORDEN']);

        $order1 = Order::create([
            'company_name' => 'CLIENTE MULTI ORDEN',
            'client_id' => $client->id,
            'task_name' => 'Task A',
            'wo_number' => 'WO 20000',
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        $order2 = Order::create([
            'company_name' => 'CLIENTE MULTI ORDEN',
            'client_id' => $client->id,
            'task_name' => 'Task B',
            'wo_number' => 'WO 10000',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $designer = Designer::create(['name' => 'César', 'active' => true]);
        $order2->update(['designer_id' => $designer->id]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order1->id)
            ->assertSet('activeOrdersSortField', 'wo')
            ->assertSet('activeOrdersSortDirection', 'asc')
            ->call('sortByActiveOrders', 'wo')
            ->assertSet('activeOrdersSortDirection', 'desc')
            ->call('sortByActiveOrders', 'designer')
            ->assertSet('activeOrdersSortField', 'designer')
            ->assertSet('activeOrdersSortDirection', 'asc')
            ->assertSee('César')
            ->assertSee('WO 10000');
    }
}
