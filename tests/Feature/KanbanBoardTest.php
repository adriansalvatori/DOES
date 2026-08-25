<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
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

    public function test_moving_order_to_blocked_creates_subtask_and_places_order_in_designer_column_with_blocked_tag(): void
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
            ->call('moveOrder', $order->id, 'ENTRANTE');

        $freshOrder = $order->fresh();

        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $freshOrder->core_status);
        $this->assertEquals(Substatus::BLOQUEADA, $freshOrder->substatus);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Bloqueado: Acme Corp - Logo Design',
            'type' => RelatedTaskType::BLOCKED->value,
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
}
