<?php

namespace Tests\Feature\Backfill;

use App\Infrastructure\Backfill\LegacyBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClosedAtBackfillTest extends TestCase
{
    use BuildsLegacyRows, RefreshDatabase;

    private function archivedOrder(array $attributes = []): int
    {
        return $this->legacyOrder($attributes + [
            'core_status' => 'ARCHIVED',
            'substatus' => 'FINALIZADA !',
            'archived_at' => '2026-10-06 21:02:41',
            'updated_at' => '2026-10-09 08:00:00',
        ]);
    }

    private function event(int $orderId, string $type, ?string $new, string $at): void
    {
        DB::table('order_events')->insert([
            'order_id' => $orderId,
            'event_type' => $type,
            'actor' => 'system',
            'new_value' => $new,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_closed_at_comes_from_the_archive_event(): void
    {
        $id = $this->archivedOrder();
        $this->event($id, 'CORE_STATUS_CHANGED', 'ARCHIVED', '2026-09-23 10:00:00');
        $this->event($id, 'ORDER_UPDATED', null, '2026-09-30 10:00:00');

        $this->runBackfill();

        $this->assertSame('2026-09-23 10:00:00', $this->row('orders', $id)->closed_at);
    }

    public function test_without_an_archive_event_closed_at_uses_the_latest_activity_not_the_migration_date(): void
    {
        $id = $this->archivedOrder(['production_processed_at' => '2026-09-10']);
        $this->event($id, 'SUBTASK_COMPLETED', null, '2026-09-12 08:30:00');

        $this->runBackfill();

        $this->assertSame('2026-09-12 08:30:00', $this->row('orders', $id)->closed_at);
    }

    public function test_with_no_history_closed_at_falls_back_to_archived_at_then_updated_at(): void
    {
        $archived = $this->archivedOrder();
        $untouched = $this->archivedOrder(['archived_at' => null]);

        $this->runBackfill();

        $this->assertSame('2026-10-06 21:02:41', $this->row('orders', $archived)->closed_at);
        $this->assertSame('2026-10-09 08:00:00', $this->row('orders', $untouched)->closed_at);
    }

    public function test_the_correction_fixes_a_wrong_closed_at_is_idempotent_and_spares_workflow_closes(): void
    {
        $wrong = $this->archivedOrder();
        $closedHere = $this->archivedOrder();
        $this->event($wrong, 'CORE_STATUS_CHANGED', 'ARCHIVED', '2026-09-23 10:00:00');
        $this->runBackfill();

        DB::table('orders')->where('id', $wrong)->update(['closed_at' => '2026-11-25 01:00:00']);
        DB::table('orders')->where('id', $closedHere)->update(['closed_at' => '2026-11-25 02:00:00']);
        $this->event($closedHere, 'deliverable_moved', 'done', '2026-11-25 02:00:00');

        $backfill = app(LegacyBackfill::class);
        $this->assertSame(1, $backfill->correctClosedAt());
        $this->assertSame(0, $backfill->correctClosedAt());

        $this->assertSame('2026-09-23 10:00:00', $this->row('orders', $wrong)->closed_at);
        $this->assertSame('2026-11-25 02:00:00', $this->row('orders', $closedHere)->closed_at);
    }
}
