<?php

namespace Tests\Feature\Backfill;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_every_open_card_keeps_its_own_backlog_flag(): void
    {
        $alone = $this->legacyOrder(['wo_number' => 'WO 10', 'in_workspace' => false]);
        $archived = $this->legacyOrder(['wo_number' => 'WO 11', 'in_workspace' => false, 'core_status' => 'ARCHIVED']);
        $live = $this->legacyOrder(['wo_number' => 'WO 12', 'in_workspace' => true]);
        $laterPhase = $this->legacyOrder(['wo_number' => 'WO 13', 'in_workspace' => false]);
        $currentPhase = $this->legacyOrder(['wo_number' => 'WO 13', 'in_workspace' => true]);

        $this->runBackfill();

        $inBacklog = fn (int $id): bool => (bool) DB::table('orders')->where('id', $id)->value('in_backlog');
        $this->assertTrue($inBacklog($alone));
        $this->assertFalse($inBacklog($archived));
        $this->assertFalse($inBacklog($live));
        $this->assertTrue($inBacklog($laterPhase), 'A later phase stays in the backlog while an earlier one is worked on.');
        $this->assertFalse($inBacklog($currentPhase));
        $this->assertSame(
            DB::table('orders')->where('id', $laterPhase)->value('work_order_id'),
            DB::table('orders')->where('id', $currentPhase)->value('work_order_id'),
        );
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

    public function test_held_cards_lose_their_due_date(): void
    {
        $held = $this->legacyOrder(['core_status' => 'ON HOLD', 'substatus' => 'PAUSADO', 'current_due_date' => '2026-07-10']);

        $this->runBackfill();

        $this->assertNull(DB::table('orders')->where('id', $held)->value('due_target'));
    }

    public function test_open_work_is_due_and_promised_at_least_3_days_after_its_last_activity(): void
    {
        Carbon::setTestNow('2026-11-26 09:00:00');
        $event = fn (int $id, string $actor, string $at) => DB::table('order_events')->insert(['order_id' => $id, 'event_type' => 'NOTE', 'actor' => $actor, 'created_at' => $at, 'updated_at' => $at]);

        $stale = $this->legacyOrder(['wo_number' => 'WO 500', 'core_status' => 'EURALIZ ORDERS RECEIVED', 'current_due_date' => '2026-07-10', 'delivery_due_date' => '2026-07-15', 'created_at' => '2026-07-01 10:00:00']);
        $event($stale, 'Euralíz Bravo', '2026-10-20 15:00:00');
        $event($stale, 'SlaEngine', '2026-11-25 15:00:00');
        $recent = $this->legacyOrder(['wo_number' => 'WO 500', 'core_status' => 'EURALIZ ORDERS RECEIVED', 'current_due_date' => '2026-11-10']);
        $event($recent, 'César Guzmán', '2026-11-20 09:00:00');
        $ahead = $this->legacyOrder(['wo_number' => 'WO 501', 'core_status' => 'EURALIZ ORDERS RECEIVED', 'current_due_date' => '2026-12-15', 'delivery_due_date' => '2026-12-31']);
        $event($ahead, 'César Guzmán', '2026-11-20 09:00:00');
        $untouched = $this->legacyOrder(['wo_number' => 'WO 502', 'core_status' => 'ENTRANTE', 'current_due_date' => '2026-07-10', 'created_at' => '2026-08-21 04:54:00']);
        $inBacklog = $this->legacyOrder(['wo_number' => 'WO 503', 'core_status' => 'EURALIZ ORDERS RECEIVED', 'current_due_date' => '2026-07-10', 'in_workspace' => false]);
        $done = $this->legacyOrder(['wo_number' => 'WO 504', 'core_status' => 'ARCHIVED', 'current_due_date' => '2026-07-10']);

        $report = $this->runBackfill();

        $due = fn (int $id): ?string => ($value = DB::table('orders')->where('id', $id)->value('due_target')) === null ? null : substr($value, 0, 10);
        $promised = fn (string $number): ?string => substr((string) DB::table('work_orders')->where('number', $number)->value('promised_date'), 0, 10);
        $this->assertSame('2026-10-23', $due($stale), 'A person acted on Oct 20; the later SLA entry does not count.');
        $this->assertSame('2026-11-23', $due($recent));
        $this->assertSame('2026-12-15', $due($ahead), 'A due date already later than that is kept.');
        $this->assertSame('2026-08-24', $due($untouched), 'With no activity the creation is the last activity.');
        $this->assertSame('2026-07-10', $due($inBacklog));
        $this->assertSame('2026-07-10', $due($done));
        $this->assertSame('2026-11-23', $promised('WO 500'), 'The work order follows the latest activity of its open work.');
        $this->assertSame('2026-12-31', $promised('WO 501'));
        $this->assertSame('2026-08-24', $promised('WO 502'));
        $this->assertSame(3, $report['due_dates_rebased']);
        $this->assertSame(2, $report['promised_dates_rebased']);
    }
}
