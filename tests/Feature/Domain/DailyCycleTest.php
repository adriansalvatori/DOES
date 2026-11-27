<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\RunDailyCycle;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\DeliverableDueToday;
use App\Domain\Delivery\Events\DeliverableOverdue;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DailyCycleTest extends DeliveryTestCase
{
    private function cycle(bool $notify = false): array
    {
        return app(RunDailyCycle::class)($notify);
    }

    public function test_ready_work_planned_for_today_or_earlier_starts_in_design(): void
    {
        $designer = $this->designer();
        $today = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        $late = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-28']);
        $future = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-01']);

        $summary = $this->cycle();

        $this->assertSame(2, $summary['started']);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $today->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $late->fresh()->status);
        $this->assertSame('2026-09-30', $late->fresh()->planned_for->toDateString());
        $this->assertSame(DeliverableStatus::READY, $future->fresh()->status);
        $this->assertSame('TO DO TODAY', $this->legacyRow($today)->core_status);
    }

    public function test_the_start_is_logged_with_the_real_previous_status_and_the_daily_cycle_reason(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $this->designer()->id, 'planned_for' => '2026-09-30']);

        $this->cycle();

        $move = $deliverable->events()->where('event_type', 'deliverable_moved')->sole();
        $this->assertSame('ready', $move->previous_value);
        $this->assertSame('in_design', $move->new_value);
        $this->assertSame('daily_cycle', $move->metadata['reason']);
        $this->assertSame('system', $move->actor);
    }

    public function test_blocked_and_held_work_does_not_start(): void
    {
        $designer = $this->designer();
        $blocked = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $held = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        Hold::factory()->create(['deliverable_id' => $held->id]);

        $summary = $this->cycle();

        $this->assertSame(0, $summary['started']);
        $this->assertSame(DeliverableStatus::READY, $blocked->fresh()->status);
        $this->assertSame(DeliverableStatus::READY, $held->fresh()->status);
    }

    public function test_work_without_an_assignee_does_not_start(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['planned_for' => '2026-09-30']);

        $this->cycle();

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
    }

    public function test_the_wip_limit_caps_how_many_start_and_blocked_work_does_not_count(): void
    {
        $designer = $this->designer();
        $designer->update(['wip_limit' => 2]);
        $blockedInDesign = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        Block::factory()->create(['deliverable_id' => $blockedInDesign->id]);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        $first = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-29']);
        $second = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);

        $summary = $this->cycle();

        $this->assertSame(1, $summary['started']);
        $this->assertSame(1, $summary['skipped_by_wip']);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $first->fresh()->status);
        $this->assertSame(DeliverableStatus::READY, $second->fresh()->status);
    }

    public function test_the_limit_is_per_person(): void
    {
        $adrian = $this->designer('Adrián');
        $cesar = $this->designer('César');
        $adrian->update(['wip_limit' => 1]);
        $one = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id, 'planned_for' => '2026-09-30']);
        $two = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id, 'planned_for' => '2026-09-30']);
        $other = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $cesar->id, 'planned_for' => '2026-09-30']);

        $this->cycle();

        $this->assertSame(DeliverableStatus::IN_DESIGN, $one->fresh()->status);
        $this->assertSame(DeliverableStatus::READY, $two->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $other->fresh()->status);
    }

    public function test_unfinished_work_of_an_earlier_day_is_carried_over_without_changing_status(): void
    {
        $carried = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-29', 'carry_over_count' => 1]);
        $current = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-30']);
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-28']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $summary = $this->cycle();

        $this->assertSame(1, $summary['carried_over']);
        $carried->refresh();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $carried->status);
        $this->assertSame('2026-09-30', $carried->planned_for->toDateString());
        $this->assertSame(2, $carried->carry_over_count);
        $this->assertSame(0, $current->fresh()->carry_over_count);
        $this->assertSame('2026-09-28', $blocked->fresh()->planned_for->toDateString());
        $this->assertSame('2026-09-30', $this->legacyRow($carried)->scheduled_date);
        $this->assertSame(1, $this->eventCount($carried, 'carried_over'));
    }

    public function test_running_the_cycle_twice_does_nothing_more(): void
    {
        $designer = $this->designer();
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        $carried = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-29']);

        $this->cycle();
        $second = $this->cycle();

        $this->assertSame(0, $second['carried_over']);
        $this->assertSame(0, $second['started']);
        $this->assertSame(1, $carried->fresh()->carry_over_count);
    }

    public function test_follow_ups_are_opened_when_their_business_day_arrives(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-25 10:00:00', 'assignee_id' => $this->designer()->id]);

        $summary = $this->cycle();

        $this->assertSame(1, $summary['follow_ups_opened']);
        $alert = $deliverable->alerts()->sole();
        $this->assertSame(AlertKind::CLIENT_FOLLOW_UP_1, $alert->kind);
        $this->assertSame('2026-09-30', $alert->due_on->toDateString());
    }

    public function test_follow_up_three_fires_on_the_ninth_business_day_and_the_client_is_not_held_yet(): void
    {
        $this->freezeAt('2026-10-13 07:00:00');
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);

        $this->cycle();

        $this->assertEqualsCanonicalizing(
            [AlertKind::CLIENT_FOLLOW_UP_1, AlertKind::CLIENT_FOLLOW_UP_2, AlertKind::CLIENT_FOLLOW_UP_3],
            $deliverable->alerts()->get()->map->kind->all(),
        );
        $this->assertFalse($deliverable->fresh()->isOnHold());
    }

    public function test_follow_ups_are_not_duplicated_by_a_second_run(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-25 10:00:00']);

        $this->cycle();
        $this->cycle();

        $this->assertSame(1, $deliverable->alerts()->count());
    }

    public function test_after_the_ninth_business_day_the_client_goes_on_hold_with_a_customer_service_block(): void
    {
        $service = $this->customerService();
        $this->freezeAt('2026-10-14 07:00:00');
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);

        $summary = $this->cycle();

        $this->assertSame(1, $summary['put_on_hold']);
        $hold = $deliverable->holds()->sole();
        $this->assertSame(HoldReason::NO_RESPONSE, $hold->reason);
        $block = $deliverable->blocks()->open()->sole();
        $this->assertSame(BlockReason::CUSTOMER_SERVICE_REQUIRED, $block->reason);
        $this->assertSame($service->id, $block->owner_id);
        $row = $this->legacyRow($deliverable);
        $this->assertSame('ENTRANTE', $row->core_status);
        $this->assertSame(1, (int) $row->customer_service_required);
    }

    public function test_the_hold_goes_to_the_queue_when_there_is_no_customer_service_user(): void
    {
        $this->freezeAt('2026-10-14 07:00:00');
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);

        $this->cycle();

        $this->assertNull($deliverable->blocks()->open()->sole()->owner_id);
    }

    public function test_work_already_on_hold_is_not_followed_up_again(): void
    {
        $this->freezeAt('2026-10-14 07:00:00');
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);
        Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::PAUSED]);

        $summary = $this->cycle();

        $this->assertSame(0, $summary['put_on_hold']);
        $this->assertSame(0, $deliverable->alerts()->count());
    }

    public function test_delay_notice_opens_for_work_due_today_only_from_14_30(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-30', 'assignee_id' => $this->designer()->id]);

        $this->freezeAt('2026-09-30 14:29:00');
        $this->assertSame(0, $this->cycle()['delay_notices']);

        $this->freezeAt('2026-09-30 14:30:00');
        $this->assertSame(1, $this->cycle()['delay_notices']);
        $this->assertSame(0, $this->cycle()['delay_notices']);
        $this->assertSame(1, $deliverable->alerts()->where('kind', AlertKind::DELAY_NOTICE->value)->count());
    }

    public function test_delay_notice_opens_at_any_time_for_work_that_is_already_late(): void
    {
        $late = $this->deliverable(DeliverableStatus::READY, ['due_target' => '2026-09-29']);
        $onTime = $this->deliverable(DeliverableStatus::READY, ['due_target' => '2026-10-02']);
        $inClientReview = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['due_target' => '2026-09-01']);
        $onHold = $this->deliverable(DeliverableStatus::READY, ['due_target' => '2026-09-29']);
        Hold::factory()->create(['deliverable_id' => $onHold->id]);

        $this->cycle();

        $this->assertSame(1, Alert::where('deliverable_id', $late->id)->where('kind', AlertKind::DELAY_NOTICE->value)->count());
        $this->assertSame(0, Alert::where('deliverable_id', $onTime->id)->count());
        $this->assertSame(0, Alert::where('deliverable_id', $inClientReview->id)->where('kind', AlertKind::DELAY_NOTICE->value)->count());
        $this->assertSame(0, Alert::where('deliverable_id', $onHold->id)->count());
    }

    public function test_the_morning_run_announces_work_due_today_and_overdue_work(): void
    {
        Event::fake([DeliverableDueToday::class, DeliverableOverdue::class]);
        $dueToday = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-30']);
        $overdue = $this->deliverable(DeliverableStatus::READY, ['due_target' => '2026-09-28']);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-10-02']);
        $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['due_target' => '2026-09-28']);

        $summary = $this->cycle(notify: true);

        $this->assertSame(1, $summary['due_today']);
        $this->assertSame(1, $summary['overdue']);
        Event::assertDispatched(DeliverableDueToday::class, fn (DeliverableDueToday $event): bool => $event->deliverable->is($dueToday));
        Event::assertDispatched(DeliverableOverdue::class, fn (DeliverableOverdue $event): bool => $event->deliverable->is($overdue));
    }

    public function test_the_second_run_of_the_day_can_skip_the_announcements(): void
    {
        Event::fake([DeliverableDueToday::class, DeliverableOverdue::class]);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-30']);

        $this->cycle(notify: false);

        Event::assertNothingDispatched();
    }

    public function test_the_command_runs_the_cycle_and_prints_the_summary(): void
    {
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $this->designer()->id, 'planned_for' => '2026-09-30']);

        $this->artisan('deliverables:daily-cycle', ['--no-notify' => true])
            ->expectsTable(['Step', 'Count'], [
                ['carried over', 0],
                ['started', 1],
                ['skipped by wip', 0],
                ['follow ups opened', 0],
                ['put on hold', 0],
                ['archived on hold', 0],
                ['delay notices', 0],
                ['due today', 0],
                ['overdue', 0],
            ])
            ->assertSuccessful();
    }

    public function test_work_on_hold_with_no_activity_for_more_than_30_days_is_archived(): void
    {
        $this->freezeAt('2026-11-26 07:00:00');
        $idle = $this->deliverable(DeliverableStatus::READY, ['created_at' => '2026-09-01 10:00:00']);
        Hold::factory()->create(['deliverable_id' => $idle->id, 'started_at' => '2026-09-02 10:00:00']);
        $this->event($idle->id, 'AutomationEngine', '2026-11-20 10:00:00');
        $touched = $this->deliverable(DeliverableStatus::READY, ['created_at' => '2026-09-01 10:00:00']);
        Hold::factory()->create(['deliverable_id' => $touched->id, 'started_at' => '2026-09-02 10:00:00']);
        $this->event($touched->id, 'Euralíz Bravo', '2026-11-10 10:00:00');

        $summary = $this->cycle();

        $this->assertSame(1, $summary['archived_on_hold']);
        $this->assertSame(DeliverableStatus::DONE, $idle->fresh()->status);
        $this->assertSame(CloseReason::INACTIVE_ON_HOLD, $idle->fresh()->close_reason);
        $this->assertFalse($idle->fresh()->isOnHold(), 'Automation entries are not activity, so the idle card is archived.');
        $this->assertTrue($touched->fresh()->isOnHold());
        $this->assertSame(DeliverableStatus::READY, $touched->fresh()->status);
        $this->assertSame(0, $this->cycle()['archived_on_hold']);
    }

    private function event(int $deliverableId, string $actor, string $at): void
    {
        DB::table('order_events')->insert(['order_id' => $deliverableId, 'event_type' => 'NOTE', 'actor' => $actor, 'created_at' => $at, 'updated_at' => $at]);
    }
}
