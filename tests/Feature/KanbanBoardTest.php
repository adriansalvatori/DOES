<?php

namespace Tests\Feature;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Designer::create(['name' => 'Adrián', 'active' => true]);
        Designer::create(['name' => 'Euralíz', 'active' => true]);
        Designer::create(['name' => 'César', 'active' => true]);
    }

    public function test_moving_order_to_blocked_opens_block_modal_and_sets_status_to_entrante_upon_confirmation(): void
    {
        $adrian = Designer::where('name', 'Adrián')->first();

        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Logo Design',
            'designer_id' => $adrian->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, 'ENTRANTE')
            ->assertSet('showBlockModal', true)
            ->assertSet('pendingBlockOrderId', $order->id)
            ->set('blockReason', 'FALTAN MEDIDAS')
            ->set('blockComment', 'Cliente debe enviar ancho y alto')
            ->set('requireCustomerService', true)
            ->call('confirmBlock')
            ->assertSet('showBlockModal', false);

        $freshOrder = $order->fresh();

        $this->assertEquals(CoreStatus::ENTRANTE, $freshOrder->core_status);
        $this->assertEquals(Substatus::BLOQUEADA, $freshOrder->substatus);
        $this->assertEquals(BlockingReason::FALTAN_MEDIDAS, $freshOrder->blocking_reason);
        $this->assertTrue((bool) $freshOrder->customer_service_required);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::SOLICITAR_INFO->value,
            'status' => 'todo',
            'assignee_id' => $adrian->id,
        ]);
    }

    public function test_moving_order_to_in_production_sets_substatus_to_enviado_en_alta(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'approved' => true,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value);

        $freshOrder = $order->fresh();

        $this->assertEquals(CoreStatus::EN_PRODUCCION, $freshOrder->core_status);
        $this->assertEquals(Substatus::ENVIADO_EN_ALTA, $freshOrder->substatus);
    }

    public function test_unapproved_order_cannot_be_moved_to_production_on_board(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp Unapproved',
            'task_name' => 'Banner Design Unapproved',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'approved' => false,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_updating_substatus_to_enviado_en_alta_in_card_detail_sets_core_status_to_in_production(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Flyer Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
            'approved' => true,
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id, true)
            ->set('editSubstatus', Substatus::ENVIADO_EN_ALTA->value)
            ->assertSet('editCoreStatus', CoreStatus::EN_PRODUCCION->value)
            ->call('saveOrder', false);

        $freshOrder = $order->fresh();

        $this->assertEquals(CoreStatus::EN_PRODUCCION, $freshOrder->core_status);
        $this->assertEquals(Substatus::ENVIADO_EN_ALTA, $freshOrder->substatus);
    }

    public function test_can_delete_related_task_directly_from_kanban_board(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisar dimensiones',
            'type' => RelatedTaskType::SUBTASK->value,
            'status' => 'todo',
        ]);

        Livewire::test(Board::class)
            ->call('deleteTask', $task->id)
            ->assertDispatched('order-updated');

        $this->assertSoftDeleted('related_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_kanban_board_displays_order_location_next_to_company_name(): void
    {
        $order = Order::create([
            'company_name' => 'FUERZA LATINA',
            'location_name' => 'EL SOL',
            'task_name' => 'PROPUESTA DE SIGN',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->assertSee('FUERZA LATINA')
            ->assertSee('EL SOL');
    }

    public function test_toggling_task_complete_on_kanban_board_logs_subtask_completed_timeline_event(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Digitalización',
            'type' => RelatedTaskType::SUBTASK->value,
            'status' => 'todo',
        ]);

        Livewire::test(Board::class)
            ->call('toggleTaskComplete', $task->id)
            ->assertDispatched('order-updated');

        $this->assertEquals('done', $task->fresh()->status);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_COMPLETED',
            'new_value' => 'Digitalización',
        ]);

        // Toggle back to todo removes event
        Livewire::test(Board::class)
            ->call('toggleTaskComplete', $task->id)
            ->assertDispatched('order-updated');

        $this->assertEquals('todo', $task->fresh()->status);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_COMPLETED',
            'new_value' => 'Digitalización',
        ]);
    }

    public function test_moving_order_on_kanban_board_records_authenticated_user_as_actor_in_timeline(): void
    {
        $user = User::factory()->create(['name' => 'Adrián Reinoza']);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::TO_DO_TODAY->value);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => 'Adrián Reinoza',
            'previous_value' => CoreStatus::ENTRANTE->value,
            'new_value' => CoreStatus::TO_DO_TODAY->value,
        ]);

        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => 'User/Automation',
        ]);
    }

    public function test_changing_substatus_in_detail_modal_records_substatus_changed_event_with_user_actor(): void
    {
        $user = User::factory()->create(['name' => 'Adrián Reinoza']);
        $this->actingAs($user);

        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Logo Redesign',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id, true)
            ->set('editSubstatus', Substatus::CAMBIOS_CLIENTE->value)
            ->call('saveOrder', false);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBSTATUS_CHANGED',
            'actor' => 'Adrián Reinoza',
            'new_value' => Substatus::CAMBIOS_CLIENTE->value,
        ]);
    }
}
