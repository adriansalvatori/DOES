<?php

namespace Tests\Feature\Backfill;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyBackfillWorkOrdersTest extends TestCase
{
    use BuildsLegacyRows, RefreshDatabase;

    public function test_cards_with_the_same_normalized_wo_number_share_one_work_order(): void
    {
        $a = $this->legacyOrder(['wo_number' => 'WO 15741']);
        $b = $this->legacyOrder(['wo_number' => ' wo  15741 ']);
        $other = $this->legacyOrder(['wo_number' => 'WO 15742']);

        $report = $this->runBackfill();

        $this->assertSame($this->row('orders', $a)->work_order_id, $this->row('orders', $b)->work_order_id);
        $this->assertNotSame($this->row('orders', $a)->work_order_id, $this->row('orders', $other)->work_order_id);
        $this->assertSame(['WO 15741', 'WO 15742'], DB::table('work_orders')->orderBy('id')->pluck('number')->all());
        $this->assertSame(2, $report['work_orders']['created']);
        $this->assertSame(1, $report['work_orders']['multi_card_groups']);
    }

    public function test_cards_without_a_usable_number_each_get_their_own_work_order_with_a_null_number(): void
    {
        foreach ([null, '', '   ', 'WO 00000', 'wo 00000'] as $number) {
            $this->legacyOrder(['wo_number' => $number]);
        }

        $report = $this->runBackfill();

        $this->assertSame(5, DB::table('work_orders')->count());
        $this->assertSame(5, DB::table('work_orders')->whereNull('number')->count());
        $this->assertSame(5, $report['work_orders']['cards_without_number']);
        $this->assertSame(5, DB::table('orders')->distinct()->count('work_order_id'));
    }

    public function test_work_order_fields_take_the_most_recent_non_null_value(): void
    {
        $oldClient = DB::table('clients')->insertGetId(['name' => 'OLD CLIENT', 'created_at' => now(), 'updated_at' => now()]);
        $newClient = DB::table('clients')->insertGetId(['name' => 'NEW CLIENT', 'created_at' => now(), 'updated_at' => now()]);
        $this->legacyOrder(['wo_number' => 'WO 1', 'client_id' => $oldClient, 'estimate_invoice_number' => 'EST-1', 'delivery_note' => 'old note', 'updated_at' => '2026-10-01 10:00:00']);
        $this->legacyOrder(['wo_number' => 'WO 1', 'client_id' => $newClient, 'estimate_invoice_number' => 'EST-1', 'delivery_note' => null, 'installation_types' => json_encode(['INSTALACION']), 'updated_at' => '2026-10-05 10:00:00']);
        $this->legacyOrder(['wo_number' => 'WO 1', 'client_id' => null, 'current_due_date' => '2026-12-24', 'updated_at' => '2026-10-03 10:00:00']);

        $this->runBackfill();

        $workOrder = DB::table('work_orders')->where('number', 'WO 1')->first();
        $this->assertSame($newClient, $workOrder->client_id);
        $this->assertSame('EST-1', $workOrder->estimate_invoice_number);
        $this->assertSame('old note', $workOrder->notes);
        $this->assertSame('2026-12-24', $workOrder->promised_date);
        $this->assertSame(['INSTALACION'], json_decode($workOrder->installation_types, true));
        $this->assertSame('installation', $workOrder->fulfillment_method);
    }

    public function test_cards_that_disagree_on_the_estimate_invoice_are_reported_and_noted(): void
    {
        $a = $this->legacyOrder(['wo_number' => 'WO 2', 'estimate_invoice_number' => 'EST-1', 'updated_at' => '2026-10-01 10:00:00']);
        $b = $this->legacyOrder(['wo_number' => 'WO 2', 'estimate_invoice_number' => 'EST-2', 'updated_at' => '2026-10-02 10:00:00']);
        $this->legacyOrder(['wo_number' => 'WO 3', 'estimate_invoice_number' => 'EST-9']);
        $this->legacyOrder(['wo_number' => 'WO 3', 'estimate_invoice_number' => ' EST-9 ']);

        $report = $this->runBackfill();

        $this->assertCount(1, $report['work_orders']['estimate_invoice_disagreements']);
        $disagreement = $report['work_orders']['estimate_invoice_disagreements'][0];
        $this->assertSame('WO 2', $disagreement['work_order_number']);
        $this->assertSame(['EST-1' => [$a], 'EST-2' => [$b]], $disagreement['estimate_invoice_numbers']);
        $this->assertSame('EST-2', DB::table('work_orders')->where('number', 'WO 2')->value('estimate_invoice_number'));
        $event = DB::table('order_events')->where('event_type', 'IMPORT_NOTE')->first();
        $this->assertSame($b, $event->order_id);
        $this->assertSame('import', $event->actor);
        $this->assertSame(1, DB::table('order_events')->where('event_type', 'IMPORT_NOTE')->count());
    }

    public function test_work_order_is_in_backlog_only_when_every_open_card_is_outside_the_workspace(): void
    {
        $this->legacyOrder(['wo_number' => 'WO 10', 'in_workspace' => false]);
        $this->legacyOrder(['wo_number' => 'WO 11', 'in_workspace' => false, 'core_status' => 'ARCHIVED']);
        $this->legacyOrder(['wo_number' => 'WO 12', 'in_workspace' => true]);
        $this->legacyOrder(['wo_number' => 'WO 13', 'in_workspace' => false]);
        $this->legacyOrder(['wo_number' => 'WO 13', 'in_workspace' => true]);
        $this->legacyOrder(['wo_number' => 'WO 14', 'in_workspace' => false]);
        $this->legacyOrder(['wo_number' => 'WO 14', 'in_workspace' => true, 'core_status' => 'ARCHIVED']);

        $this->runBackfill();

        $backlog = fn (string $number) => (bool) DB::table('work_orders')->where('number', $number)->value('in_backlog');
        $this->assertTrue($backlog('WO 10'));
        $this->assertFalse($backlog('WO 11'));
        $this->assertFalse($backlog('WO 12'));
        $this->assertFalse($backlog('WO 13'));
        $this->assertTrue($backlog('WO 14'));
    }

    public function test_work_order_is_soft_deleted_only_when_every_card_is_trashed(): void
    {
        $this->legacyOrder(['wo_number' => 'WO 20', 'deleted_at' => '2026-10-10 10:00:00']);
        $this->legacyOrder(['wo_number' => 'WO 21', 'deleted_at' => '2026-10-10 10:00:00']);
        $this->legacyOrder(['wo_number' => 'WO 21']);

        $this->runBackfill();

        $this->assertNotNull(DB::table('work_orders')->where('number', 'WO 20')->value('deleted_at'));
        $this->assertNull(DB::table('work_orders')->where('number', 'WO 21')->value('deleted_at'));
    }
}
