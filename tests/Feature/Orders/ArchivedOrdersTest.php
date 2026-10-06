<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\ArchivedOrders;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArchivedOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected Designer $designer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->designer = Designer::create(['name' => 'Euralíz', 'active' => true]);
        Designer::create(['name' => 'César', 'active' => true]);
        Designer::create(['name' => 'Adrián', 'active' => true]);
    }

    public function test_moving_order_to_archived_sets_status_and_archived_at_timestamp(): void
    {
        $order = Order::create([
            'company_name' => 'Acme Inc',
            'task_name' => 'Packaging Design',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
            'start_date' => now()->subDays(5)->toDateString(),
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, 'ARCHIVED')
            ->call('confirmArchive');

        $fresh = $order->fresh();

        $this->assertEquals(CoreStatus::ARCHIVED, $fresh->core_status);
        $this->assertNotNull($fresh->archived_at);
        $this->assertEquals(5, $fresh->days_to_close);
    }

    public function test_archived_orders_are_hidden_from_active_workspace_scope(): void
    {
        $activeOrder = Order::create([
            'company_name' => 'Active Corp',
            'task_name' => 'Flyer',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $archivedOrder = Order::create([
            'company_name' => 'Archived Corp',
            'task_name' => 'Banner',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::ARCHIVED,
            'in_workspace' => true,
            'archived_at' => now(),
        ]);

        $workspaceOrders = Order::inWorkspace()->get();

        $this->assertTrue($workspaceOrders->contains($activeOrder));
        $this->assertFalse($workspaceOrders->contains($archivedOrder));
    }

    public function test_archived_orders_page_renders_successfully_with_designer_performance_stats(): void
    {
        Order::create([
            'company_name' => 'Closed Client',
            'task_name' => 'Web Redesign',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::ARCHIVED,
            'in_workspace' => true,
            'start_date' => now()->subDays(3)->toDateString(),
            'archived_at' => now(),
            'client_revision_count' => 2,
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/archived');
        $response->assertStatus(200);
        $response->assertSee('Órdenes Archivadas &amp; Rendimiento', false);
        $response->assertSee('CLOSED CLIENT');
        $response->assertSee('Euralíz');
    }

    public function test_archived_orders_page_allows_archiving_and_restoring_orders(): void
    {
        $prodOrder = Order::create([
            'company_name' => 'Production Client',
            'task_name' => 'Print Collateral',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        Livewire::test(ArchivedOrders::class)
            ->call('archiveOrder', $prodOrder->id)
            ->call('confirmArchive');

        $fresh = $prodOrder->fresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $fresh->core_status);
        $this->assertNotNull($fresh->archived_at);

        Livewire::test(ArchivedOrders::class)
            ->call('restoreOrder', $prodOrder->id);

        $restored = $prodOrder->fresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $restored->core_status);
        $this->assertNull($restored->archived_at);
    }

    public function test_moving_order_to_archived_with_selected_substatus(): void
    {
        Substatus::create([
            'name' => 'DEBE',
            'core_status' => CoreStatus::ARCHIVED->value,
            'is_default' => false,
            'is_global' => false,
        ]);

        $order = Order::create([
            'company_name' => 'Pending Debt Client',
            'task_name' => 'Vehicle Wrap',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, 'ARCHIVED')
            ->assertSet('showArchiveModal', true)
            ->set('archiveSubstatus', 'DEBE')
            ->call('confirmArchive');

        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $fresh->core_status);
        $subVal = $fresh->substatus instanceof \App\Enums\Substatus ? $fresh->substatus->value : (string) $fresh->substatus;
        $this->assertEquals('DEBE', $subVal);
        $this->assertNotNull($fresh->archived_at);
    }

    public function test_order_detail_modal_archive_flow_and_cancel_reversion(): void
    {
        $order = Order::create([
            'company_name' => 'Detail Modal Client',
            'task_name' => 'Signage',
            'designer_id' => $this->designer->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => \App\Enums\Substatus::CAMBIOS_CLIENTE,
            'in_workspace' => true,
        ]);

        // 1. Changing to ARCHIVED opens archive modal
        $component = Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('changeCoreStatus', 'ARCHIVED')
            ->assertSet('showArchiveModal', true);

        // 2. Canceling closes modal and keeps original status
        $component->call('closeArchiveModal')
            ->assertSet('showArchiveModal', false);

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);

        // 3. Confirming archives with selected substatus
        $component->call('changeCoreStatus', 'ARCHIVED')
            ->set('archiveSubstatus', 'CLIENTE NO RESPONSIVE')
            ->call('confirmArchive');

        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $fresh->core_status);
        $subVal = $fresh->substatus instanceof \App\Enums\Substatus ? $fresh->substatus->value : (string) $fresh->substatus;
        $this->assertEquals('CLIENTE NO RESPONSIVE', $subVal);
        $this->assertNotNull($fresh->archived_at);
    }
}
