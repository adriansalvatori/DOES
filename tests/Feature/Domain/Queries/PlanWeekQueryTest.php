<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Queries\PlanWeekQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanWeekQueryTest extends QueryTestCase
{
    public function test_ready_backlog_is_sorted_by_work_order_promised_date_and_excludes_planned_and_blocked(): void
    {
        $late = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => '2026-12-20']));
        $soon = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => '2026-12-01']));
        $undated = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => null]));
        $planned = $this->makeDeliverable(DeliverableStatus::READY, null, ['planned_for' => '2026-11-26']);
        $blocked = $this->makeDeliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $this->makeDeliverable(DeliverableStatus::INTAKE);

        $plan = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-25'));

        $this->assertSame([$soon->id, $late->id, $undated->id], $plan->backlog->modelKeys());
    }

    public function test_grid_has_monday_to_friday_per_designer_and_wip_ignores_blocked(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 2]);
        $inactive = User::factory()->designer()->create(['active' => false]);
        User::factory()->coordinator()->create();
        $one = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        $two = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        $blocked = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $nextWeek = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-12-01']);

        $plan = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-27'));

        $this->assertSame('2026-11-23', $plan->weekStart->toDateString());
        $this->assertSame(['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27'], array_map(fn ($day) => $day->toDateString(), $plan->days));
        $this->assertCount(1, $plan->rows);
        $row = $plan->rows[0];
        $this->assertSame(2, $row['limit']);
        $thursday = $row['cells']['2026-11-26'];
        $this->assertCount(3, $thursday['cards']);
        $this->assertSame(2, $thursday['used']);
        $this->assertSame(0, $row['cells']['2026-11-23']['used']);
        $this->assertNotContains($nextWeek->id, collect($row['cells'])->flatMap(fn ($cell) => $cell['cards']->modelKeys())->all());
        $this->assertNotContains($inactive->id, array_map(fn ($each) => $each['assignee']->id, $plan->rows));
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $designers): void {
            foreach (range(1, $designers) as $unused) {
                $designer = User::factory()->designer()->create();
                foreach (['2026-11-23', '2026-11-24', '2026-11-25'] as $day) {
                    $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $designer->id, 'planned_for' => $day]);
                }
                $this->makeDeliverable(DeliverableStatus::READY);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(5), fn () => app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-25')));
    }
}
