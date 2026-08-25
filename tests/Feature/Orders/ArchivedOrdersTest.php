<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\ArchivedOrders;
use App\Models\Designer;
use App\Models\Order;
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
            ->call('moveOrder', $order->id, 'ARCHIVED');

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

        $response = $this->get('/archived');
        $response->assertStatus(200);
        $response->assertSee('Órdenes Archivadas &amp; Rendimiento', false);
        $response->assertSee('Closed Client');
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
            ->call('archiveOrder', $prodOrder->id);

        $fresh = $prodOrder->fresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $fresh->core_status);
        $this->assertNotNull($fresh->archived_at);

        Livewire::test(ArchivedOrders::class)
            ->call('restoreOrder', $prodOrder->id);

        $restored = $prodOrder->fresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $restored->core_status);
        $this->assertNull($restored->archived_at);
    }
}
