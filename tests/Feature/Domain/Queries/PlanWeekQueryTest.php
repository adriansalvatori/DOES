<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Data\PlanWeek;
use App\Application\Delivery\Queries\PlanWeekQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanWeekQueryTest extends QueryTestCase
{
    private function task(Deliverable $deliverable, ?string $day, array $attributes = []): Task
    {
        return Task::factory()->create(['order_id' => $deliverable->id, 'scheduled_date' => $day, ...$attributes]);
    }

    /**
     * @return list<int>
     */
    private function taskIds(PlanWeek $plan, ?int $personId, string $column): array
    {
        $row = collect($plan->rows)->first(fn (array $row): bool => $row['assignee']?->id === $personId);

        return $row['cells'][$column]['tasks']->modelKeys();
    }

    public function test_backlog_holds_unplanned_ready_work_and_work_with_tasks_without_a_day_by_promised_date(): void
    {
        $late = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => '2026-12-20']));
        $soon = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => '2026-12-01']));
        $undated = $this->makeDeliverable(DeliverableStatus::READY, WorkOrder::factory()->create(['promised_date' => null]));
        $inDesign = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, WorkOrder::factory()->create(['promised_date' => '2026-12-10']), ['planned_for' => '2026-11-24']);
        $this->task($inDesign, null);
        $planned = $this->makeDeliverable(DeliverableStatus::READY, null, ['planned_for' => '2026-11-26']);
        $this->task($planned, '2026-11-26');
        $blocked = $this->makeDeliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $this->makeDeliverable(DeliverableStatus::INTAKE);

        $plan = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-25'));

        $this->assertSame([$soon->id, $inDesign->id, $late->id, $undated->id], $plan->backlog->modelKeys());
        $this->assertSame(1, $plan->backlog->firstWhere('id', $inDesign->id)->unscheduled_tasks_count);
        $this->assertSame([$blocked->id], $plan->blockedBacklog->modelKeys());
    }

    public function test_tasks_sit_on_their_persons_day_with_a_next_week_column_and_done_work_stays(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 2]);
        $bea = User::factory()->designer()->create(['name' => 'Bea']);
        User::factory()->designer()->create(['active' => false]);
        $piece = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id]);
        $inherited = $this->task($piece, '2026-11-24', ['points' => 3]);
        $handedToBea = $this->task($piece, '2026-11-24', ['assignee_user_id' => $bea->id]);
        $done = $this->task($piece, '2026-11-24', ['status' => 'done', 'points' => 2]);
        $nextWeek = $this->task($piece, '2026-12-02');
        $farAway = $this->task($piece, '2026-12-09');

        $plan = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-27'), today: Carbon::parse('2026-12-20'));

        $this->assertSame(['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', PlanWeek::NEXT_WEEK], $plan->columns());
        $this->assertSame('2026-11-30', $plan->nextWeek->toDateString());
        $this->assertSame(['Ana', 'Bea'], array_map(fn (array $row): string => $row['assignee']->name, $plan->rows));
        $this->assertSame([$inherited->id, $done->id], $this->taskIds($plan, $ana->id, '2026-11-24'));
        $this->assertSame([$handedToBea->id], $this->taskIds($plan, $bea->id, '2026-11-24'));
        $this->assertSame([$nextWeek->id], $this->taskIds($plan, $ana->id, PlanWeek::NEXT_WEEK));
        $this->assertNotContains($farAway->id, collect($plan->rows)->flatMap(fn ($row) => collect($row['cells'])->flatMap(fn ($cell) => $cell['tasks']->modelKeys()))->all());

        $tuesday = $plan->rows[0]['cells']['2026-11-24'];
        $this->assertSame(5, $tuesday['points']);
        $this->assertSame(1, $tuesday['open']);
        $this->assertSame(1, $tuesday['used'], 'One open deliverable counts once, done work does not count.');
        $this->assertSame([], $plan->carriedTaskIds, 'Carry-over only applies while looking at the current week.');
    }

    public function test_unfinished_tasks_from_earlier_weeks_show_on_today_and_planned_work_without_tasks_keeps_a_card(): void
    {
        $ana = User::factory()->designer()->create(['wip_limit' => 3]);
        $piece = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id]);
        $stale = $this->task($piece, '2026-11-10');
        $this->task($piece, '2026-11-11', ['status' => 'done']);
        $closed = $this->makeDeliverable(DeliverableStatus::DONE, null, ['assignee_id' => $ana->id]);
        $this->task($closed, '2026-11-12');
        $bare = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        $blocked = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $plan = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-23'), today: Carbon::parse('2026-11-25'));

        $this->assertSame([$stale->id], $plan->carriedTaskIds);
        $this->assertSame([$stale->id], $this->taskIds($plan, $ana->id, '2026-11-25'));
        $thursday = $plan->rows[0]['cells']['2026-11-26'];
        $this->assertSame([$bare->id, $blocked->id], $thursday['deliverables']->modelKeys());
        $this->assertSame(1, $thursday['used'], 'Blocked work is listed but not counted.');
    }

    public function test_unassigned_work_gets_its_own_row_and_priority_sort_puts_urgent_work_first(): void
    {
        $ana = User::factory()->designer()->create();
        $low = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id, 'priority' => Priority::LOW]);
        $high = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id, 'priority' => Priority::HIGHEST]);
        $first = $this->task($low, '2026-11-24', ['sort_order' => 0]);
        $second = $this->task($high, '2026-11-24', ['sort_order' => 1]);
        $orphan = $this->task($this->makeDeliverable(DeliverableStatus::IN_DESIGN), '2026-11-24');

        $manual = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-24'));
        $byPriority = app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-24'), PlanWeekQuery::SORT_PRIORITY);

        $this->assertSame([$first->id, $second->id], $this->taskIds($manual, $ana->id, '2026-11-24'));
        $this->assertSame([$second->id, $first->id], $this->taskIds($byPriority, $ana->id, '2026-11-24'));
        $this->assertNull(collect($manual->rows)->last()['assignee']);
        $this->assertSame([$orphan->id], $this->taskIds($manual, null, '2026-11-24'));
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $designers): void {
            foreach (range(1, $designers) as $unused) {
                $designer = User::factory()->designer()->create();
                foreach (['2026-11-23', '2026-11-24', '2026-11-25'] as $day) {
                    $piece = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id]);
                    Task::factory()->count(2)->create(['order_id' => $piece->id, 'scheduled_date' => $day]);
                    $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $designer->id, 'planned_for' => $day]);
                }
                $this->makeDeliverable(DeliverableStatus::READY);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(5), fn () => app(PlanWeekQuery::class)->run(Carbon::parse('2026-11-25')));
    }
}
