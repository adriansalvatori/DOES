<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Livewire\Orders\ArchivedOrders;
use App\Livewire\Overview\OverviewIndex;
use Livewire\Livewire;

class EntryArchivedOverviewTest extends EntryTestCase
{
    public function test_archived_update_substatus_to_another_archived_value_keeps_order_archived_and_logs(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()]);

        Livewire::test(ArchivedOrders::class)->call('updateSubstatus', $order->id, Substatus::CANCELADA->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $order->core_status);
        $this->assertSame(Substatus::CANCELADA, $order->substatus);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBSTATUS_CHANGED',
            'previous_value' => 'FINALIZADA !',
            'new_value' => 'CANCELADA',
        ]);
    }

    public function test_archived_update_substatus_to_active_value_implicitly_moves_order_through_observer(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()]);

        Livewire::test(ArchivedOrders::class)->call('updateSubstatus', $order->id, Substatus::PAUSADO->value);

        $order->refresh();
        $this->assertSame(Substatus::PAUSADO, $order->substatus);
        $this->assertSame(CoreStatus::ON_HOLD, $order->core_status);
        $this->assertNull($order->archived_at);
    }

    public function test_restore_order_returns_it_to_production_and_logs_status_change(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()]);

        Livewire::test(ArchivedOrders::class)->call('restoreOrder', $order->id);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertNull($order->archived_at);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'previous_value' => 'ARCHIVED',
            'new_value' => 'EN PRODUCCIÓN',
        ]);
    }

    public function test_archived_confirm_archive_flow(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::EN_PRODUCCION]);

        Livewire::test(ArchivedOrders::class)
            ->call('archiveOrder', $order->id)
            ->assertSet('showArchiveModal', true)
            ->set('archiveSubstatus', Substatus::FINALIZADA->value)
            ->call('confirmArchive')
            ->assertSet('showArchiveModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $order->core_status);
        $this->assertSame(Substatus::FINALIZADA, $order->substatus);
        $this->assertNotNull($order->archived_at);
    }

    public function test_overview_update_substatus_with_archived_value_archives_the_order(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::EN_PRODUCCION]);

        Livewire::test(OverviewIndex::class)->call('updateSubstatus', $order->id, Substatus::FINALIZADA->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $order->core_status);
        $this->assertSame(Substatus::FINALIZADA, $order->substatus);
        $this->assertNotNull($order->archived_at);
        $this->assertContains('SUBSTATUS_CHANGED', $this->eventTypes($order));
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_overview_update_substatus_with_regular_value_only_changes_substatus(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY]);

        Livewire::test(OverviewIndex::class)->call('updateSubstatus', $order->id, Substatus::CAMBIOS_CLIENTE->value);

        $order->refresh();
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);
        $this->assertContains('SUBSTATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_overview_update_substatus_with_global_value_toggles_a_flag_instead(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        Livewire::test(OverviewIndex::class)->call('updateSubstatus', $order->id, Substatus::URGENTE->value);

        $order->refresh();
        $this->assertTrue($order->hasFlag(Substatus::URGENTE));
        $this->assertNull($order->substatus);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertContains('FLAG_TOGGLED', $this->eventTypes($order));
    }

    public function test_overview_toggle_flag_urgente_pulls_queue_order_into_working_today_and_off_again(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $component = Livewire::test(OverviewIndex::class)->call('toggleFlag', $order->id, 'URGENTE');

        $order->refresh();
        $this->assertTrue($order->hasFlag('URGENTE'));
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);

        $component->call('toggleFlag', $order->id, 'URGENTE');
        $this->assertFalse($order->fresh()->hasFlag('URGENTE'));
    }

    public function test_overview_toggle_flag_falta_algo_blocks_order_and_clearing_it_releases_it(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $component = Livewire::test(OverviewIndex::class)->call('toggleFlag', $order->id, 'FALTA ALGO');

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);

        $component->call('toggleFlag', $order->id, 'FALTA ALGO');
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
    }

    public function test_overview_update_designer_assigns_and_clears(): void
    {
        $order = $this->makeOrder();
        $component = Livewire::test(OverviewIndex::class)->call('updateDesigner', $order->id, $this->cesar->id);

        $order->refresh();
        $this->assertSame($this->cesar->id, $order->designer_id);
        $this->assertSame([$this->cesar->id], $order->designers()->pluck('designers.id')->all());
        $this->assertContains('DESIGNER_ASSIGNED', $this->eventTypes($order));

        $component->call('updateDesigner', $order->id, null);
        $order->refresh();
        $this->assertNull($order->designer_id);
        $this->assertCount(0, $order->designers);
    }

    public function test_overview_move_to_workspace_and_back_to_backlog(): void
    {
        $order = $this->makeOrder(['in_workspace' => false]);
        $component = Livewire::test(OverviewIndex::class)->call('moveToWorkspace', $order->id);

        $this->assertTrue($order->fresh()->in_workspace);
        $this->assertContains('MOVED_TO_WORKSPACE', $this->eventTypes($order));

        $component->call('moveToBacklog', $order->id);
        $this->assertFalse($order->fresh()->in_workspace);
        $this->assertContains('MOVED_TO_BACKLOG', $this->eventTypes($order));
    }

    public function test_overview_save_edit_normalizes_wo_number(): void
    {
        $order = $this->makeOrder(['wo_number' => 'WO 1']);

        Livewire::test(OverviewIndex::class)
            ->call('startEdit', $order->id, 'wo_number')
            ->assertSet('editingValue', 'WO 1')
            ->set('editingValue', '4321')
            ->call('saveEdit')
            ->assertSet('editingOrderId', null);

        $this->assertSame('WO 4321', $order->fresh()->wo_number);
        $this->assertContains('ORDER_UPDATED', $this->eventTypes($order));
    }

    public function test_overview_save_edit_updates_estimate_invoice_number(): void
    {
        $order = $this->makeOrder();

        Livewire::test(OverviewIndex::class)
            ->call('startEdit', $order->id, 'estimate_invoice_number')
            ->set('editingValue', '  EST-77 ')
            ->call('saveEdit');

        $this->assertSame('EST-77', $order->fresh()->estimate_invoice_number);
    }

    public function test_overview_save_edit_without_field_does_nothing(): void
    {
        $order = $this->makeOrder(['wo_number' => 'WO 9']);

        Livewire::test(OverviewIndex::class)->call('saveEdit');

        $this->assertSame('WO 9', $order->fresh()->wo_number);
    }
}
