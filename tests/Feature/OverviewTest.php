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
        $this->assertEquals('KUDOS', $fresh->installation_type);
        $this->assertTrue($fresh->hasFlag(Substatus::URGENTE));
        $this->assertTrue((bool) $fresh->overview_checked);
        $this->assertEquals('Revision de medidas requerida', $fresh->production_note);
        $this->assertEquals('Entregar por la tarde', $fresh->delivery_note);
        $this->assertEquals('2026-09-28', $fresh->email_date?->format('Y-m-d'));
    }

    public function test_overview_can_update_production_processed_at_and_delivery_due_date(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 66666',
            'task_name' => 'Custom Dates Test Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('quickUpdateField', $order->id, 'production_processed_at', '2026-10-05')
            ->call('quickUpdateField', $order->id, 'delivery_due_date', '2026-10-10');

        $fresh = $order->fresh();
        $this->assertEquals('2026-10-05', $fresh->production_processed_at?->format('Y-m-d'));
        $this->assertEquals('2026-10-10', $fresh->delivery_due_date?->format('Y-m-d'));
    }

    public function test_overview_renders_urgent_red_background_when_order_has_due_date(): void
    {
        $orderWithDueDate = Order::create([
            'wo_number' => 'WO 77777',
            'task_name' => 'Due Date Urgent Red Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'delivery_due_date' => '2026-10-15',
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSeeHtml('due-date-urgent')
            ->assertSeeHtml('bg-red-600');
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

    public function test_overview_can_toggle_global_flags(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 99999',
            'task_name' => 'Flag Test Order',
            'company_name' => 'Flag Client',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('toggleFlag', $order->id, 'URGENTE')
            ->assertSee('URG');

        $this->assertTrue($order->fresh()->hasFlag('URGENTE'));

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('toggleFlag', $order->id, 'URGENTE');

        $this->assertFalse($order->fresh()->hasFlag('URGENTE'));
    }

    public function test_overview_toggle_flag_handles_null_order_id_safely(): void
    {
        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('toggleFlag', null, 'URGENTE')
            ->assertOk();
    }

    public function test_overview_substatus_popover_renders_global_flags_first_without_emojis(): void
    {
        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertDontSee('🚩')
            ->assertSeeHtmlInOrder([
                __('Banderas / Flags Globales'),
                mb_strtoupper(__('Urgente')),
                mb_strtoupper(__('Ticket')),
                __('Clasificación de Proceso (1 Selección)'),
                __('Sin Subestatus'),
            ]);
    }

    public function test_overview_shows_assigned_designer_from_relation_when_designer_id_is_null(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 77112',
            'task_name' => 'Designer Relation Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => null,
        ]);

        $order->designers()->attach($this->designer->id);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSee('Adrián');
    }

    public function test_overview_can_update_designer(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 77113',
            'task_name' => 'Update Designer Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('updateDesigner', $order->id, $this->designer->id);

        $fresh = $order->fresh();
        $this->assertEquals($this->designer->id, $fresh->designer_id);
        $this->assertTrue($fresh->designers->contains('id', $this->designer->id));
    }

    public function test_overview_filters_production_and_archived_with_substatuses(): void
    {
        $prodOrder = Order::create([
            'wo_number' => 'WO 50001',
            'task_name' => 'Prod Test Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        $archivedFinalizada = Order::create([
            'wo_number' => 'WO 50002',
            'task_name' => 'Archived Finalizada Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => Substatus::FINALIZADA,
            'in_workspace' => false,
        ]);

        $archivedCancelada = Order::create([
            'wo_number' => 'WO 50003',
            'task_name' => 'Archived Cancelada Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => Substatus::CANCELADA,
            'in_workspace' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('setTab', 'production')
            ->assertSee('PROD TEST ORDER')
            ->assertDontSee('ARCHIVED FINALIZADA ORDER')
            ->call('setTab', 'archived', 'finalizada')
            ->assertSee('ARCHIVED FINALIZADA ORDER')
            ->assertDontSee('ARCHIVED CANCELADA ORDER')
            ->assertDontSee('PROD TEST ORDER')
            ->call('setArchivedSubstatus', 'cancelada')
            ->assertSee('ARCHIVED CANCELADA ORDER')
            ->assertDontSee('ARCHIVED FINALIZADA ORDER');
    }

    public function test_poll_orders_state_detects_changes_and_returns_reactive_state(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 77777',
            'task_name' => 'State Sync Order Test',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PAUSADO,
            'designer_id' => $this->designer->id,
            'in_workspace' => true,
        ]);

        $component = Livewire::actingAs($this->user)->test(OverviewIndex::class);

        // Initial poll with no last timestamp should return full state
        $res = $component->instance()->pollOrdersState([$order->id], null);
        $this->assertTrue($res['has_changes']);
        $this->assertArrayHasKey($order->id, $res['orders']);
        $this->assertSame(Substatus::PAUSADO->value, $res['orders'][$order->id]['substatus']);
        $this->assertSame($this->designer->id, $res['orders'][$order->id]['designer_id']);

        $currentTs = $res['timestamp'];

        // Second poll with same timestamp should return has_changes = false (zero work)
        $noChangeRes = $component->instance()->pollOrdersState([$order->id], $currentTs);
        $this->assertFalse($noChangeRes['has_changes']);

        // Update order in database (simulating another user's change)
        Order::where('id', $order->id)->update([
            'substatus' => Substatus::PONER_EN_ALTA->value,
            'updated_at' => now()->addMinutes(1),
        ]);

        // Next poll should detect the new timestamp and return the fresh state
        $updatedRes = $component->instance()->pollOrdersState([$order->id], $currentTs);
        $this->assertTrue($updatedRes['has_changes']);
        $this->assertSame(Substatus::PONER_EN_ALTA->value, $updatedRes['orders'][$order->id]['substatus']);
    }

    public function test_applied_filters_are_displayed_and_clearable(): void
    {
        Order::create([
            'wo_number' => 'WO 77777',
            'task_name' => 'Filter Test Task',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->set('filterWo', '77777')
            ->assertSee('77777')
            ->call('clearFilter', 'filterWo')
            ->assertSet('filterWo', '');
    }

    public function test_archived_substatus_filters_computes_counts_and_displays_cards(): void
    {
        Order::create([
            'wo_number' => 'WO 90001',
            'task_name' => 'Archived Test 1',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => Substatus::FINALIZADA,
            'in_workspace' => false,
        ]);

        Order::create([
            'wo_number' => 'WO 90002',
            'task_name' => 'Archived Test 2',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => Substatus::CANCELADA,
            'in_workspace' => false,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->call('setTab', 'archived');

        $filters = $component->instance()->archivedSubstatusFilters;
        $this->assertArrayHasKey('all', $filters);
        $this->assertGreaterThanOrEqual(2, $filters['all']['count']);

        $component->assertSee('Órdenes Archivadas');
    }

    public function test_grouped_process_substatuses_groups_by_core_status(): void
    {
        \App\Models\Substatus::firstOrCreate(
            ['name' => 'BLOQUEADA'],
            ['core_status' => CoreStatus::ENTRANTE, 'is_global' => false]
        );
        \App\Models\Substatus::firstOrCreate(
            ['name' => 'PAUSADO'],
            ['core_status' => CoreStatus::ON_HOLD, 'is_global' => false]
        );

        $component = Livewire::actingAs($this->user)->test(OverviewIndex::class);
        $groups = $component->instance()->groupedProcessSubstatuses;

        $this->assertNotEmpty($groups);
        $this->assertArrayHasKey('ENTRANTE', $groups);
        $this->assertArrayHasKey('ON HOLD', $groups);
        $this->assertNotEmpty($groups['ENTRANTE']['items']);
        $this->assertNotEmpty($groups['ENTRANTE']['title']);
    }

    public function test_overview_orders_list_is_sorted_by_wo_number_descending_by_default(): void
    {
        $orderLow = Order::create([
            'wo_number' => 'WO 10001',
            'task_name' => 'Low WO Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $orderHigh = Order::create([
            'wo_number' => 'WO 15000',
            'task_name' => 'High WO Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $orderMid = Order::create([
            'wo_number' => 'WO 12500',
            'task_name' => 'Mid WO Order',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSet('sortBy', 'wo_number')
            ->assertSet('sortDirection', 'desc')
            ->assertViewHas('orders', function ($orders) use ($orderHigh, $orderMid, $orderLow) {
                $ids = $orders->pluck('id')->toArray();
                $highIndex = array_search($orderHigh->id, $ids, true);
                $midIndex = array_search($orderMid->id, $ids, true);
                $lowIndex = array_search($orderLow->id, $ids, true);

                return $highIndex !== false && $midIndex !== false && $lowIndex !== false
                    && $highIndex < $midIndex && $midIndex < $lowIndex;
            });
    }

    public function test_overview_updates_view_on_order_updated_event(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 10001',
            'task_name' => 'Initial Task Name',
            'company_name' => 'Kudos Client Test',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(OverviewIndex::class)
            ->assertSee('WO 10001');

        $order->update(['wo_number' => 'WO 99999']);

        $test->dispatch('order-updated')
            ->assertSee('WO 99999');
    }
}
