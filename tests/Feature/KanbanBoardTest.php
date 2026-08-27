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
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value);

        $freshOrder = $order->fresh();

        $this->assertEquals(CoreStatus::EN_PRODUCCION, $freshOrder->core_status);
        $this->assertEquals(Substatus::ENVIADO_EN_ALTA, $freshOrder->substatus);
    }

    public function test_updating_substatus_to_enviado_en_alta_in_card_detail_sets_core_status_to_in_production(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Flyer Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
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
}
