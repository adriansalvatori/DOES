<?php

namespace Tests\Feature\Screens\Ops;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Livewire\Analytics\AnalyticsPage;
use App\Livewire\Analytics\Support\AnalyticsMetrics;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class AnalyticsPageTest extends DeliveryTestCase
{
    private function move(Deliverable $deliverable, DeliverableStatus $to, string $at): void
    {
        DB::table('order_events')->insert([
            'order_id' => $deliverable->id,
            'event_type' => 'deliverable_moved',
            'actor' => 'test',
            'new_value' => $to->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_designers_get_403_everyone_else_can_open_it(): void
    {
        $this->actingAs(User::factory()->designer()->create())->get(route('analytics'))->assertForbidden();

        foreach ([$this->manager(), User::factory()->admin()->create(), $this->customerService(), User::factory()->sales()->create()] as $user) {
            $this->actingAs($user)->get(route('analytics'))->assertOk();
        }
    }

    public function test_cycle_time_per_column_averages_finished_stays(): void
    {
        $a = $this->deliverable(DeliverableStatus::DONE);
        $this->move($a, DeliverableStatus::IN_DESIGN, '2026-09-20 09:00:00');
        $this->move($a, DeliverableStatus::INTERNAL_REVIEW, '2026-09-22 09:00:00');
        $this->move($a, DeliverableStatus::DONE, '2026-09-23 09:00:00');
        $b = $this->deliverable(DeliverableStatus::DONE);
        $this->move($b, DeliverableStatus::IN_DESIGN, '2026-09-10 09:00:00');
        $this->move($b, DeliverableStatus::INTERNAL_REVIEW, '2026-09-14 09:00:00');

        $cycle = collect((new AnalyticsMetrics)->cycleTimePerColumn())->keyBy(fn ($row) => $row['status']->value);

        $this->assertSame(3.0, $cycle['in_design']['days']);
        $this->assertSame(2, $cycle['in_design']['stays']);
        $this->assertSame(1.0, $cycle['internal_review']['days']);
        $this->assertSame(0, $cycle['ready']['stays']);
    }

    public function test_cycle_time_also_reads_legacy_status_history_before_the_first_native_move(): void
    {
        $legacyEvent = fn (Deliverable $deliverable, string $to, string $at) => DB::table('order_events')->insert([
            'order_id' => $deliverable->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => 'User/Automation',
            'new_value' => $to,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $a = $this->deliverable(DeliverableStatus::DONE);
        $legacyEvent($a, 'TO DO TODAY', '2026-09-20 09:00:00');
        $legacyEvent($a, 'ENVIADO AL CLIENTE', '2026-09-22 09:00:00');
        $legacyEvent($a, 'ON HOLD', '2026-09-23 09:00:00');
        $legacyEvent($a, 'ARCHIVED', '2026-09-26 09:00:00');
        $this->move($a, DeliverableStatus::READY, '2026-10-01 09:00:00');
        $legacyEvent($a, 'ENTRANTE', '2026-10-02 09:00:00');

        $cycle = collect((new AnalyticsMetrics)->cycleTimePerColumn())->keyBy(fn ($row) => $row['status']->value);

        $this->assertSame(2.0, $cycle['in_design']['days']);
        $this->assertSame(1.0, $cycle['client_review']['days']);
        $this->assertSame(0, $cycle['intake']['stays']);
        $this->assertSame(0, $cycle['ready']['stays']);
    }

    public function test_client_wait_averages_client_review_and_counts_what_waits_now(): void
    {
        $a = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $this->move($a, DeliverableStatus::CLIENT_REVIEW, '2026-09-20 09:00:00');
        $this->move($a, DeliverableStatus::IN_DESIGN, '2026-09-24 09:00:00');
        $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-27 09:00:00']);

        $wait = (new AnalyticsMetrics)->clientWait();

        $this->assertSame(4.0, $wait['average_days']);
        $this->assertSame(1, $wait['waiting_now']);
        $this->assertSame(3, $wait['longest_waiting_days']);
    }

    public function test_weekly_throughput_counts_closed_deliverables_per_week_including_empty_weeks(): void
    {
        $this->deliverable(DeliverableStatus::DONE, ['closed_at' => '2026-09-29 10:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['closed_at' => '2026-09-28 10:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['closed_at' => '2026-09-16 10:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['closed_at' => '2026-05-01 10:00:00']);

        $weeks = (new AnalyticsMetrics)->weeklyThroughput();

        $this->assertCount(8, $weeks);
        $this->assertSame(['week_start' => '2026-09-28', 'count' => 2], $weeks[7]);
        $this->assertSame(1, $weeks[5]['count']);
        $this->assertSame(0, $weeks[6]['count']);
        $this->assertSame(3, array_sum(array_column($weeks, 'count')));
    }

    public function test_wip_per_designer_ignores_blocked_cards_and_uses_each_limit(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 2]);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $ana->id]);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $ana->id]);
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $ana->id]);
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $ana->id]);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $row = collect((new AnalyticsMetrics)->wipPerDesigner())->first(fn ($each) => $each['user']->id === $ana->id);

        $this->assertSame(2, $row['wip']);
        $this->assertSame(2, $row['limit']);
    }

    public function test_closed_kpis_cover_total_reasons_average_days_and_top_designer(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana']);
        $ben = User::factory()->designer()->create(['name' => 'Ben']);
        $this->deliverable(DeliverableStatus::DONE, ['assignee_id' => $ana->id, 'close_reason' => CloseReason::COMPLETED->value, 'created_at' => '2026-09-20 09:00:00', 'closed_at' => '2026-09-24 09:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['assignee_id' => $ana->id, 'close_reason' => CloseReason::COMPLETED->value, 'created_at' => '2026-09-22 09:00:00', 'closed_at' => '2026-09-24 09:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['assignee_id' => $ben->id, 'close_reason' => CloseReason::CANCELLED_BY_CLIENT->value, 'created_at' => '2026-09-23 09:00:00', 'closed_at' => '2026-09-24 09:00:00']);
        $this->deliverable(DeliverableStatus::READY);

        $kpis = (new AnalyticsMetrics)->closedKpis();

        $this->assertSame(3, $kpis['total']);
        $this->assertSame(2.3, $kpis['average_days']);
        $this->assertSame(['name' => 'Ana', 'count' => 2], $kpis['top_designer']);
        $this->assertSame(CloseReason::COMPLETED, $kpis['reasons'][0]['reason']);
        $this->assertSame(2, $kpis['reasons'][0]['count']);
    }

    public function test_renders_in_both_locales_with_empty_and_filled_data(): void
    {
        $manager = $this->manager();
        Livewire::actingAs($manager)->test(AnalyticsPage::class)->assertOk()->assertSee('Nothing closed yet');

        $a = $this->deliverable(DeliverableStatus::DONE, ['close_reason' => CloseReason::COMPLETED->value, 'closed_at' => '2026-09-29 10:00:00']);
        $this->move($a, DeliverableStatus::IN_DESIGN, '2026-09-20 09:00:00');
        $this->move($a, DeliverableStatus::DONE, '2026-09-29 09:00:00');

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            Livewire::actingAs($manager)->test(AnalyticsPage::class)
                ->assertSee(__('Cycle time per column'))->assertSee(__('Weekly throughput'))->assertSee(__('WIP vs limit per designer'))
                ->assertSee(DeliverableStatus::IN_DESIGN->label())->assertDontSee('enums.');
        }
    }

    public function test_the_page_runs_a_fixed_number_of_queries(): void
    {
        $manager = $this->manager();
        $count = function () use ($manager): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($manager)->test(AnalyticsPage::class);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };
        $seed = function (): void {
            $a = $this->deliverable(DeliverableStatus::DONE, ['assignee_id' => User::factory()->designer()->create()->id, 'close_reason' => CloseReason::COMPLETED->value, 'closed_at' => '2026-09-29 10:00:00']);
            $this->move($a, DeliverableStatus::IN_DESIGN, '2026-09-20 09:00:00');
            $this->move($a, DeliverableStatus::DONE, '2026-09-29 09:00:00');
        };
        $seed();
        $count();
        $small = $count();
        foreach (range(1, 6) as $unused) {
            $seed();
        }

        $this->assertSame($small, $count());
    }
}
