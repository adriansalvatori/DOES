<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_can_update_and_toggle_subtask_type(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON SUBTAREA TIPO',
            'task_name' => 'DISENO LOGO',
            'in_workspace' => true,
        ]);

        $subtask = $order->relatedTasks()->create([
            'title' => 'Subtarea Tipo',
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskType', $subtask->id, false)
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'is_work_task' => false,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskType', $subtask->id)
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'is_work_task' => true,
        ]);
    }

    public function test_can_update_and_clear_subtask_date(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON SUBTAREA FECHA',
            'task_name' => 'DISENO FLYER',
            'in_workspace' => true,
        ]);

        $subtask = $order->relatedTasks()->create([
            'title' => 'Subtarea Fecha',
            'status' => 'todo',
            'scheduled_date' => '2026-09-25',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskDate', $subtask->id, '2026-10-05')
            ->assertDispatched('order-updated');

        $this->assertEquals('2026-10-05', $subtask->fresh()->scheduled_date?->format('Y-m-d'));

        // Clear date
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskDate', $subtask->id, null)
            ->assertDispatched('order-updated');

        $this->assertNull($subtask->fresh()->scheduled_date);
    }

    public function test_can_update_and_clear_subtask_assignee(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA CON SUBTAREA ASIGNADO',
            'task_name' => 'DISENO CATÁLOGO',
            'in_workspace' => true,
        ]);

        $designer = Designer::create([
            'name' => 'Diseñador Prueba',
            'active' => true,
        ]);

        $subtask = $order->relatedTasks()->create([
            'title' => 'Subtarea Asignado',
            'status' => 'todo',
            'assignee_id' => null,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskAssignee', $subtask->id, $designer->id)
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'assignee_id' => $designer->id,
        ]);

        // Unassign
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateTaskAssignee', $subtask->id, null)
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'assignee_id' => null,
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

    public function test_approval_validation_requires_support(): void
    {
        $order = Order::create([
            'company_name' => 'CLIENTE PRUEBA APROBACION',
            'task_name' => 'Pendiente Aprobacion',
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->assertSet('showApprovalModal', true)
            ->set('approvalComment', '')
            ->set('approvalImage', null)
            ->call('submitApproval')
            ->assertHasErrors(['approvalSupport']);

        $this->assertFalse($order->fresh()->approved);
    }

    public function test_approval_with_text_by_camila(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true, 'is_lead' => true]);

        $order = Order::create([
            'company_name' => 'CLIENTE CAMILA APROBACION',
            'task_name' => 'Diseno Camila',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->assertSet('approvalType', 'camila')
            ->set('approvalComment', 'Visto bueno dado directamente por Camila vía chat interno.')
            ->call('submitApproval')
            ->assertHasNoErrors()
            ->assertDispatched('order-updated');

        $fresh = $order->fresh();
        $this->assertTrue($fresh->approved);
        $this->assertEquals('camila', $fresh->approval_type);
        $this->assertEquals('Aprobado por Camila', $fresh->approval_type_label);
        $this->assertEquals('Visto bueno dado directamente por Camila vía chat interno.', $fresh->approval_note);
        $this->assertNotNull($fresh->approved_at);

        $event = $order->events()->where('event_type', 'ORDER_APPROVED')->first();
        $this->assertNotNull($event);
        $this->assertEquals('camila', $event->metadata['approval_type']);
        $this->assertEquals('Visto bueno dado directamente por Camila vía chat interno.', $event->metadata['approval_note']);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'status' => 'todo',
        ]);
        $task = $order->relatedTasks()->where('title', 'Poner en alta')->first();
        $this->assertNotNull($task);
        $this->assertEquals(now()->addWeekdays(1)->toDateString(), $task->due_date?->toDateString());
    }

    public function test_approval_with_image_by_cliente(): void
    {
        Storage::fake('public');

        $designer = Designer::create(['name' => 'Euralíz', 'active' => true, 'is_lead' => true]);

        $order = Order::create([
            'company_name' => 'CLIENTE DIRECTO APROBACION',
            'task_name' => 'Diseno Cliente',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'designer_id' => $designer->id,
        ]);

        $file = UploadedFile::fake()->image('whatsapp_proof.png', 600, 400);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->assertSet('approvalType', 'cliente')
            ->set('approvalImage', $file)
            ->set('approvalComment', 'Cliente autorizó en captura adjunta.')
            ->call('submitApproval')
            ->assertHasNoErrors()
            ->assertDispatched('order-updated');

        $fresh = $order->fresh();
        $this->assertTrue($fresh->approved);
        $this->assertEquals('cliente', $fresh->approval_type);
        $this->assertEquals('Aprobado por Cliente', $fresh->approval_type_label);
        $this->assertNotNull($fresh->approval_image_path);
        Storage::disk('public')->assertExists($fresh->approval_image_path);

        $event = $order->events()->where('event_type', 'ORDER_APPROVED')->first();
        $this->assertNotNull($event);
        $this->assertEquals('cliente', $event->metadata['approval_type']);
        $this->assertNotNull($event->metadata['approval_image']);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'status' => 'todo',
        ]);
        $task = $order->relatedTasks()->where('title', 'Poner en alta')->first();
        $this->assertNotNull($task);
        $this->assertEquals(now()->addWeekdays(1)->toDateString(), $task->due_date?->toDateString());
    }

    public function test_approval_for_urgent_order_assigns_today_to_task_and_sla(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true, 'is_lead' => true]);

        $order = Order::create([
            'company_name' => 'CLIENTE URGENTE HOY',
            'task_name' => 'Pendón Urgente',
            'in_workspace' => true,
            'substatus' => Substatus::URGENTE,
            'designer_id' => $designer->id,
        ]);

        $this->assertTrue($order->isUrgente());

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->set('approvalType', 'cliente')
            ->set('approvalComment', 'Aprobado para hoy mismo')
            ->call('submitApproval')
            ->assertHasNoErrors()
            ->assertDispatched('order-updated');

        $fresh = $order->fresh();
        $this->assertTrue($fresh->approved);
        $this->assertTrue($fresh->isUrgente());
        $this->assertEquals(today()->toDateString(), $fresh->current_due_date?->toDateString());

        $task = $fresh->relatedTasks()->where('title', 'Poner en alta')->first();
        $this->assertNotNull($task);
        $this->assertEquals(today()->toDateString(), $task->due_date?->toDateString());
        $this->assertEquals(today()->toDateString(), $task->scheduled_date?->toDateString());
        $this->assertEquals('urgent', $task->priority);
    }
}
