<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Livewire\Overview\OverviewIndex;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Designer $designer;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $this->client = Client::create(['name' => 'Kudos Client Test']);
    }

    public function test_overview_page_can_be_rendered(): void
    {
        $this->actingAs($this->user)
            ->get('/overview')
            ->assertStatus(200)
            ->assertSee('Overview Operativo');
    }

    public function test_overview_separates_workspace_and_backlog_orders(): void
    {
        $workspaceOrder = Order::create([
            'wo_number' => 'WO 11111',
            'task_name' => 'Workspace Order Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $backlogOrder = Order::create([
            'wo_number' => 'WO 22222',
            'task_name' => 'Backlog Order Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSee('WORKSPACE ORDER TEST')
            ->assertSee('BACKLOG ORDER TEST')
            ->assertViewHas('orders', function ($orders) use ($workspaceOrder, $backlogOrder) {
                return $orders->contains($workspaceOrder) && $orders->contains($backlogOrder);
            });
    }

    public function test_overview_tab_switching_filters_unified_orders(): void
    {
        $workspaceOrder = Order::create([
            'wo_number' => 'WO 11111',
            'task_name' => 'Workspace Order Tab Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $backlogOrder = Order::create([
            'wo_number' => 'WO 22222',
            'task_name' => 'Backlog Order Tab Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->set('activeTab', 'all')
            ->assertSee('WORKSPACE ORDER TAB TEST')
            ->assertSee('BACKLOG ORDER TAB TEST')
            ->set('activeTab', 'workspace')
            ->assertSee('WORKSPACE ORDER TAB TEST')
            ->assertDontSee('BACKLOG ORDER TAB TEST')
            ->set('activeTab', 'backlog')
            ->assertSee('BACKLOG ORDER TAB TEST')
            ->assertDontSee('WORKSPACE ORDER TAB TEST');
    }

    public function test_overview_can_toggle_section_visibility(): void
    {
        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSet('showWorkspace', true)
            ->assertSet('showBacklog', true)
            ->call('toggleSection', 'workspace')
            ->assertSet('showWorkspace', false)
            ->call('toggleSection', 'backlog')
            ->assertSet('showBacklog', false);
    }

    public function test_overview_can_update_wo_number_inline(): void
    {
        $order = Order::create([
            'wo_number' => null,
            'task_name' => 'Missing WO Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('startEdit', $order->id, 'wo_number')
            ->set('editingValue', '99999')
            ->call('saveEdit');

        $this->assertEquals('WO 99999', $order->fresh()->wo_number);
    }

    public function test_overview_can_update_review_status_installation_notes_and_checkbox(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 55555',
            'task_name' => 'New Fields Test Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('updateReviewStatus', $order->id, 'CS')
            ->call('updateInstallationType', $order->id, 'Kudos')
            ->call('updateSubstatus', $order->id, 'URGENTE')
            ->call('toggleOverviewChecked', $order->id)
            ->call('startEdit', $order->id, 'production_note')
            ->set('editingValue', 'Revision de medidas requerida')
            ->call('saveEdit')
            ->call('startEdit', $order->id, 'delivery_note')
            ->set('editingValue', 'Entregar por la tarde')
            ->call('saveEdit')
            ->call('startEdit', $order->id, 'email_date')
            ->set('editingValue', '2026-09-28')
            ->call('saveEdit');

        $fresh = $order->fresh();
        $this->assertEquals('CS', $fresh->review_status);
        $this->assertEquals('Kudos', $fresh->installation_type);
        $this->assertTrue($fresh->hasFlag(Substatus::URGENTE));
        $this->assertTrue((bool) $fresh->overview_checked);
        $this->assertEquals('Revision de medidas requerida', $fresh->production_note);
        $this->assertEquals('Entregar por la tarde', $fresh->delivery_note);
        $this->assertEquals('2026-09-28', $fresh->email_date?->format('Y-m-d'));
    }

    public function test_overview_can_move_order_between_backlog_and_workspace(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 44444',
            'task_name' => 'Move Order Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('moveToWorkspace', $order->id);

        $this->assertTrue((bool) $order->fresh()->in_workspace);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('moveToBacklog', $order->id);

        $this->assertFalse((bool) $order->fresh()->in_workspace);
    }

    public function test_overview_can_filter_orders_by_search_and_wo(): void
    {
        $orderA = Order::create([
            'wo_number' => 'WO 77777',
            'task_name' => 'Unique Alpha Task',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $orderB = Order::create([
            'wo_number' => 'WO 88888',
            'task_name' => 'Unique Beta Task',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->set('search', 'Alpha')
            ->assertSee('UNIQUE ALPHA TASK')
            ->assertDontSee('UNIQUE BETA TASK')
            ->set('search', '')
            ->set('filterWo', '88888')
            ->assertSee('UNIQUE BETA TASK')
            ->assertDontSee('UNIQUE ALPHA TASK');
    }

    public function test_overview_file_caching_and_unpaginated_display(): void
    {
        Order::create([
            'wo_number' => 'WO 33333',
            'task_name' => 'Cached Order Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSet('perPage', 0)
            ->assertSee('CACHED ORDER TEST')
            ->assertSee('Sin paginación • File Cached')
            ->call('refreshCache')
            ->assertDispatched('toast');
    }

    public function test_overview_loads_records_in_chunks_progressively(): void
    {
        // Create 150 orders to test chunking
        for ($i = 1; $i <= 150; $i++) {
            Order::create([
                'wo_number' => sprintf('WO %05d', $i + 10000),
                'task_name' => "Chunk Task {$i}",
                'company_name' => 'Kudos Client Test',
                'core_status' => CoreStatus::TO_DO_TODAY,
                'in_workspace' => true,
            ]);
        }

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSet('loadedCount', 100)
            ->assertViewHas('hasMore', true)
            ->call('loadNextChunk')
            ->assertSet('loadedCount', 200)
            ->assertViewHas('hasMore', false);
    }
}
