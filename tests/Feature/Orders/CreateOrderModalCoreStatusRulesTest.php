<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Orders\CreateOrderModal;
use App\Livewire\Overview\OverviewIndex;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderModalCoreStatusRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_enters_selected_designer_orders_received_status(): void
    {
        $user = User::factory()->create();

        $designer = Designer::create([
            'name' => 'Adrián',
            'slug' => 'adrian',
            'active' => true,
            'is_external' => false,
        ]);

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'CLIENTE PRUEBA')
            ->set('taskName', 'TAREA PRUEBA')
            ->call('toggleDesigner', $designer->id)
            ->call('save');

        $order = Order::where('company_name', 'CLIENTE PRUEBA')->first();
        $this->assertNotNull($order);
        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
    }

    public function test_order_with_substatus_urgente_enters_working_today(): void
    {
        $user = User::factory()->create();

        $designer = Designer::create([
            'name' => 'César',
            'slug' => 'cesar',
            'active' => true,
            'is_external' => false,
        ]);

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'CLIENTE URGENTE')
            ->set('taskName', 'TAREA URGENTE')
            ->call('toggleDesigner', $designer->id)
            ->call('selectSubstatus', 'URGENTE')
            ->call('save');

        $order = Order::where('company_name', 'CLIENTE URGENTE')->first();
        $this->assertNotNull($order);
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertTrue($order->isUrgente());
    }

    public function test_order_with_substatus_falta_algo_enters_bloqueada(): void
    {
        $user = User::factory()->create();

        $designer = Designer::create([
            'name' => 'Adrián',
            'slug' => 'adrian',
            'active' => true,
            'is_external' => false,
        ]);

        Substatus::create([
            'name' => 'FALTA ALGO',
            'is_global' => true,
            'bg_color' => '#b00e2f',
            'text_color' => '#ffffff',
            'border_color' => '#b00e2f',
        ]);

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'CLIENTE FALTA ALGO')
            ->set('taskName', 'TAREA FALTA ALGO')
            ->call('toggleDesigner', $designer->id)
            ->call('selectSubstatus', 'FALTA ALGO')
            ->call('save');

        $order = Order::where('company_name', 'CLIENTE FALTA ALGO')->first();
        $this->assertNotNull($order);
        $this->assertEquals(CoreStatus::ENTRANTE, $order->core_status);
    }

    public function test_removing_falta_algo_unblocks_order_back_to_designer_queue_or_working_today(): void
    {
        $user = User::factory()->create();

        $designer = Designer::create([
            'name' => 'Adrián',
            'slug' => 'adrian',
            'active' => true,
            'is_external' => false,
        ]);

        Substatus::create([
            'name' => 'FALTA ALGO',
            'is_global' => true,
            'bg_color' => '#b00e2f',
            'text_color' => '#ffffff',
            'border_color' => '#b00e2f',
        ]);

        $order = Order::create([
            'company_name' => 'TEST UNBLOCK',
            'task_name' => 'TASK UNBLOCK',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'flags' => ['FALTA ALGO'],
            'in_workspace' => true,
        ]);

        $this->assertEquals(CoreStatus::ENTRANTE, $order->core_status);

        // Toggle FALTA ALGO flag off in Overview
        Livewire::actingAs($user)
            ->test(OverviewIndex::class)
            ->call('toggleFlag', $order->id, 'FALTA ALGO');

        $order->refresh();
        $this->assertFalse($order->hasFaltaAlgo());
        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);

        // If it also has URGENTE, it unblocks into WORKING_TODAY
        $orderUrgent = Order::create([
            'company_name' => 'TEST UNBLOCK URGENT',
            'task_name' => 'TASK UNBLOCK URGENT',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'flags' => ['FALTA ALGO', 'URGENTE'],
            'in_workspace' => true,
        ]);

        Livewire::actingAs($user)
            ->test(OverviewIndex::class)
            ->call('toggleFlag', $orderUrgent->id, 'FALTA ALGO');

        $orderUrgent->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $orderUrgent->core_status);
    }

    public function test_corestatus_dropdown_is_hidden_from_create_modal_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->assertDontSee('Lista / Estado Kanban');
    }

    public function test_specified_substatuses_are_hidden_from_create_modal(): void
    {
        $user = User::factory()->create();

        Substatus::create([
            'name' => 'BLOQUEADA',
            'core_status' => CoreStatus::ENTRANTE,
            'is_global' => false,
        ]);

        Substatus::create([
            'name' => 'PROCESO DE PERMISO',
            'core_status' => CoreStatus::ENTRANTE,
            'is_global' => false,
        ]);

        Substatus::create([
            'name' => 'OVERDUE',
            'is_global' => true,
        ]);

        Substatus::create([
            'name' => 'ALMOST OVERDUE',
            'is_global' => true,
        ]);

        Substatus::create([
            'name' => 'URGENTE',
            'is_global' => true,
            'bg_color' => '#DC2626',
            'text_color' => '#FFFFFF',
            'border_color' => '#B91C1C',
        ]);

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->assertSee('URGENTE')
            ->assertDontSeeHtml('>BLOQUEADA</span>')
            ->assertDontSeeHtml('>PROCESO DE PERMISO</span>')
            ->assertDontSeeHtml('>OVERDUE</span>')
            ->assertDontSeeHtml('>ALMOST OVERDUE</span>')
            ->assertSeeHtml('background-color: #DC2626');
    }
}
