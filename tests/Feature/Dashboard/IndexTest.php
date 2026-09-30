<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\User;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_center_renders_successfully(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Order::create([
            'company_name' => 'EMPRESA CONTROL TEST',
            'task_name' => 'DISENO LOGO',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertStatus(200)
            ->assertSee(__('Centro de Control Operativo'))
            ->assertSee('tour-dashboard-stats', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('EMPRESA CONTROL TEST');
    }

    public function test_designer_user_auto_selected_on_mount(): void
    {
        $user = User::factory()->create(['role' => 'designer']);
        $designer = Designer::create([
            'user_id' => $user->id,
            'name' => 'Adrian Reinoza',
            'slug' => 'adrian',
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSet('selectedDesigner', (string) $designer->id)
            ->assertSet('userRole', 'designer')
            ->assertSet('activeTab', 'today');
    }

    public function test_designer_pills_filter_primary_and_co_assigned_orders_and_subtasks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $designerA = Designer::create([
            'name' => 'Adrian',
            'slug' => 'adrian',
            'active' => true,
        ]);

        $designerB = Designer::create([
            'name' => 'Cesar',
            'slug' => 'cesar',
            'active' => true,
        ]);

        // Order 1: primary designer A
        $order1 = Order::create([
            'company_name' => 'EMPRESA A',
            'task_name' => 'TAREA 1',
            'in_workspace' => true,
            'designer_id' => $designerA->id,
        ]);

        // Order 2: co-assigned designer A, primary designer B
        $order2 = Order::create([
            'company_name' => 'EMPRESA B',
            'task_name' => 'TAREA 2',
            'in_workspace' => true,
            'designer_id' => $designerB->id,
        ]);
        $order2->designers()->attach($designerA->id);

        // Order 3: only designer B
        $order3 = Order::create([
            'company_name' => 'EMPRESA C',
            'task_name' => 'TAREA 3',
            'in_workspace' => true,
            'designer_id' => $designerB->id,
        ]);

        // Task for designer A
        $taskA = RelatedTask::create([
            'order_id' => $order1->id,
            'title' => 'SUBTAREA ADRIAN',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'assignee_id' => $designerA->id,
        ]);

        // Task for designer B
        $taskB = RelatedTask::create([
            'order_id' => $order3->id,
            'title' => 'SUBTAREA CESAR',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'assignee_id' => $designerB->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('selectDesigner', (string) $designerA->id)
            ->assertSet('selectedDesigner', (string) $designerA->id)
            ->assertViewHas('overdueOrders', fn ($orders) => $orders->isEmpty()) // none overdue
            ->assertViewHas('camilaFollowUpTasks', fn ($tasks) => $tasks->pluck('id')->contains($taskA->id) && ! $tasks->pluck('id')->contains($taskB->id));
    }

    public function test_mark_done_today_toggles_safely_and_does_not_revert_other_orders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $orderToday1 = Order::create([
            'company_name' => 'ORDEN UNO',
            'task_name' => 'BANNER UNO',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => false,
        ]);

        $orderToday2 = Order::create([
            'company_name' => 'ORDEN DOS',
            'task_name' => 'BANNER DOS',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('markDoneToday', $orderToday1->id);

        $this->assertTrue($orderToday1->fresh()->done_today);
        // Ensure orderToday2 was NOT kicked out of TO_DO_TODAY
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $orderToday2->fresh()->core_status);
        $this->assertFalse($orderToday2->fresh()->done_today);

        // Toggle back
        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('markDoneToday', $orderToday1->id);

        $this->assertFalse($orderToday1->fresh()->done_today);
    }

    public function test_mark_done_today_promotes_poner_en_alta_order_to_production(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $orderAlta = Order::create([
            'company_name' => 'ORDEN LISTA PRODUCCION',
            'task_name' => 'ROTULO EXTERIOR',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('markDoneToday', $orderAlta->id);

        $fresh = $orderAlta->fresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $fresh->core_status);
        $this->assertNull($fresh->substatus);
        $this->assertFalse($fresh->done_today);
    }

    public function test_resolve_modal_unblocks_order(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);

        $designer = Designer::create([
            'name' => 'Lead Designer',
            'slug' => 'lead',
            'is_lead' => true,
            'active' => true,
        ]);

        $blockedOrder = Order::create([
            'company_name' => 'EMPRESA BLOQUEADA',
            'task_name' => 'DISENO FACHADA',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'designer_id' => $designer->id,
            'customer_service_required' => true,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openResolveModal', $blockedOrder->id)
            ->assertSet('showResolveModal', true)
            ->assertSet('resolveOrderId', $blockedOrder->id)
            ->set('resolveComment', 'Cliente aprobo presupuesto por WhatsApp')
            ->call('unblockOrder')
            ->assertSet('showResolveModal', false)
            ->assertDispatched('order-updated');

        $fresh = $blockedOrder->fresh();
        $this->assertNull($fresh->substatus);
        $this->assertFalse($fresh->customer_service_required);
    }

    public function test_resolve_modal_keeps_order_blocked_with_comment(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);

        $blockedOrder = Order::create([
            'company_name' => 'EMPRESA BLOQUEADA NOTA',
            'task_name' => 'DISENO FACHADA 2',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'customer_service_required' => true,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openResolveModal', $blockedOrder->id)
            ->set('resolveComment', 'Llamamos al cliente, responde por la tarde')
            ->call('keepOrderBlocked')
            ->assertSet('showResolveModal', false)
            ->assertDispatched('order-updated');

        $fresh = $blockedOrder->fresh();
        $this->assertEquals(Substatus::BLOQUEADA, $fresh->substatus);
        $this->assertTrue($fresh->customer_service_required);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $blockedOrder->id,
            'event_type' => 'ORDER_BLOCK_REVIEWED',
        ]);
    }

    public function test_camila_modal_opens_and_closes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'company_name' => 'CAMILA CLIENT TEST',
            'task_name' => 'CAMILA TASK TEST',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
        ]);
        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisar arte con Camila',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openCamilaModal', $task->id)
            ->assertSet('showCamilaModal', true)
            ->assertSet('camilaTaskId', $task->id)
            ->call('closeCamilaModal')
            ->assertSet('showCamilaModal', false)
            ->assertSet('camilaTaskId', null);
    }

    public function test_camila_modal_request_changes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $designer = Designer::create([
            'name' => 'Arturo Designer',
            'slug' => 'arturo',
            'active' => true,
        ]);
        $order = Order::create([
            'company_name' => 'CAMILA REVISION TEST',
            'task_name' => 'REVISION ARTES',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'designer_id' => $designer->id,
            'internal_revision_count' => 0,
        ]);
        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisar arte con Camila',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'assignee_id' => $designer->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openCamilaModal', $task->id)
            ->set('camilaComment', 'Ajustar contraste en logo')
            ->call('camilaRequestChanges')
            ->assertSet('showCamilaModal', false)
            ->assertDispatched('order-updated');

        $this->assertEquals('done', $task->fresh()->status);
        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $fresh->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CAMILA, $fresh->substatus);
        $this->assertEquals(1, $fresh->internal_revision_count);
        $this->assertFalse($fresh->done_today);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CAMILA_CHANGES_REQUESTED',
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::SUBTASK->value,
            'title' => 'Ajustes Camila: Ajustar contraste en logo',
        ]);
    }

    public function test_camila_modal_pre_approve_creates_proof_subtask(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $designer = Designer::create([
            'name' => 'Arturo Designer',
            'slug' => 'arturo',
            'active' => true,
        ]);
        $order = Order::create([
            'company_name' => 'CAMILA PREAPPROVE TEST',
            'task_name' => 'DISENO CATALOGO',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'designer_id' => $designer->id,
        ]);
        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisar arte con Camila',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'assignee_id' => $designer->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openCamilaModal', $task->id)
            ->set('camilaComment', 'Todo perfecto para enviar')
            ->call('camilaPreApprove')
            ->assertSet('showCamilaModal', false)
            ->assertDispatched('order-updated');

        $this->assertEquals('done', $task->fresh()->status);
        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $fresh->core_status);
        $this->assertNull($fresh->substatus);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CAMILA_PRE_APPROVED',
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::SUBTASK->value,
            'title' => 'enviar proof al cliente (camila pre-approves)',
        ]);
    }

    public function test_camila_modal_just_add_comment(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'company_name' => 'CAMILA COMMENT TEST',
            'task_name' => 'DISENO TARJETA',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
        ]);
        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisar arte con Camila',
            'status' => 'todo',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('openCamilaModal', $task->id)
            ->set('camilaComment', 'Camila necesita conseguir mas info')
            ->call('camilaAddComment')
            ->assertSet('showCamilaModal', false)
            ->assertDispatched('order-updated');

        // Task remains todo
        $this->assertEquals('todo', $task->fresh()->status);
        // Order remains in ENVIADO_A_CAMILA
        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::ENVIADO_A_CAMILA, $fresh->core_status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CAMILA_NOTE',
        ]);
    }

    public function test_proof_subtask_auto_moves_to_enviado_al_cliente_at_end_of_day(): void
    {
        Designer::create([
            'name' => 'Lead Designer',
            'slug' => 'lead',
            'is_lead' => true,
            'active' => true,
        ]);

        $order = Order::create([
            'company_name' => 'CAMILA PROOF AUTO MOVE',
            'task_name' => 'DISENO ROTULO',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => true,
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'enviar proof al cliente (camila pre-approves)',
            'status' => 'done',
            'type' => RelatedTaskType::SUBTASK,
        ]);

        $automationEngine = app(AutomationEngine::class);
        $automationEngine->runDailyAutomations();

        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $fresh->core_status);
        $this->assertNotNull($fresh->last_sent_to_client_at);
        $this->assertTrue($fresh->done_today);
    }
}
