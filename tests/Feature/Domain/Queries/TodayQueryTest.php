<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Queries\TodayQuery;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

class TodayQueryTest extends QueryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00:00');
    }

    public function test_designer_sees_planned_carry_overs_and_overdue_without_blocked_or_waiting_on_client(): void
    {
        $designer = User::factory()->designer()->create(['wip_limit' => 3]);
        $today = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);
        $carry = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-24', 'carry_over_count' => 1]);
        $overdue = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $designer->id, 'due_target' => '2026-11-20']);
        $blocked = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $waiting = $this->makeDeliverable(DeliverableStatus::CLIENT_REVIEW, null, ['assignee_id' => $designer->id, 'due_target' => '2026-11-20']);
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['planned_for' => '2026-11-25']);

        $view = app(TodayQuery::class)->forDesigner($designer);

        $this->assertSame([$today->id], $view['plannedToday']->modelKeys());
        $this->assertSame([$carry->id], $view['carryOvers']->modelKeys());
        $this->assertSame([$overdue->id], $view['overdue']->modelKeys());
        $this->assertSame(['used' => 2, 'limit' => 3], $view['wip']);
        $this->assertNotContains($waiting->id, $view['overdue']->modelKeys());
    }

    public function test_waiting_on_client_appears_only_through_a_due_follow_up_alert(): void
    {
        $designer = User::factory()->designer()->create();
        $waiting = $this->makeDeliverable(DeliverableStatus::CLIENT_REVIEW, null, ['assignee_id' => $designer->id]);
        $future = Alert::factory()->create(['deliverable_id' => $waiting->id, 'user_id' => $designer->id, 'due_on' => '2026-11-27']);

        $this->assertCount(0, app(TodayQuery::class)->forDesigner($designer)['alerts']);

        $future->update(['due_on' => '2026-11-25']);
        $alerts = app(TodayQuery::class)->forDesigner($designer)['alerts'];

        $this->assertSame([$future->id], $alerts->modelKeys());
    }

    public function test_overdue_starts_at_four_pm_on_the_due_day(): void
    {
        $designer = User::factory()->designer()->create();
        $due = $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $designer->id, 'due_target' => '2026-11-25']);

        $this->assertCount(0, app(TodayQuery::class)->forDesigner($designer)['overdue']);

        Carbon::setTestNow('2026-11-25 16:00:00');
        $this->assertSame([$due->id], app(TodayQuery::class)->forDesigner($designer)['overdue']->modelKeys());
    }

    public function test_reviewer_sees_internal_review_grouped_with_batch_readiness_and_left_behind(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $workOrder = WorkOrder::factory()->create();
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id]);
        $a = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $phase->id]);
        $b = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $phase->id]);
        $heldBack = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $phase->id]);
        Hold::factory()->create(['deliverable_id' => $heldBack->id]);
        $inDesign = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $workOrder, ['phase_id' => $phase->id]);
        Approval::factory()->create(['deliverable_id' => $a->id, 'source' => ApprovalSource::REVIEWER]);

        $groups = app(TodayQuery::class)->forReviewer($reviewer);

        $this->assertCount(1, $groups);
        $this->assertFalse($groups[0]->readiness->ready);
        $this->assertSame([1, 2], [$groups[0]->readiness->approved, $groups[0]->readiness->total]);
        $this->assertEqualsCanonicalizing([$heldBack->id, $inDesign->id], $groups[0]->readiness->leftBehind->modelKeys());

        Approval::factory()->create(['deliverable_id' => $b->id, 'source' => ApprovalSource::REVIEWER]);
        $this->assertTrue(app(TodayQuery::class)->forReviewer($reviewer)[0]->readiness->ready);
    }

    public function test_reviewer_ignores_reset_approvals_and_items_of_another_reviewer(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $other = User::factory()->reviewer()->create();
        $mine = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW);
        Approval::factory()->reset()->create(['deliverable_id' => $mine->id, 'source' => ApprovalSource::REVIEWER]);
        $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, null, ['reviewer_id' => $other->id]);

        $groups = app(TodayQuery::class)->forReviewer($reviewer);

        $this->assertCount(1, $groups);
        $this->assertFalse($groups[0]->readiness->ready);
    }

    public function test_manager_sees_needs_attention(): void
    {
        $overdue = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['due_target' => '2026-11-20']);
        $urgent = $this->makeDeliverable(DeliverableStatus::READY, null, ['flag_codes' => [DeliverableFlag::URGENT->value], 'due_target' => '2026-12-10']);
        $blocked = $this->makeDeliverable(DeliverableStatus::INTAKE, null, ['due_target' => '2026-12-10']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $held = $this->makeDeliverable(DeliverableStatus::CLIENT_REVIEW, null, ['due_target' => '2026-12-10']);
        Hold::factory()->create(['deliverable_id' => $held->id]);
        $this->makeDeliverable(DeliverableStatus::DONE, null, ['due_target' => '2026-11-01']);
        $this->makeDeliverable(DeliverableStatus::READY, null, ['due_target' => '2026-11-01', 'in_backlog' => true]);

        $view = app(TodayQuery::class)->forManager();

        $this->assertSame([$overdue->id], $view['overdue']->modelKeys());
        $this->assertSame([$urgent->id], $view['urgent']->modelKeys());
        $this->assertSame([$blocked->id], $view['blocked']->modelKeys());
        $this->assertSame([$held->id], $view['onHold']->modelKeys());
        $this->assertEqualsCanonicalizing([$urgent->id], $view['unassigned']->modelKeys());
        $this->assertSame(1, $view['counts']['overdue']);
    }

    public function test_customer_service_sees_open_blocks_and_holds_and_for_user_picks_the_variants(): void
    {
        $cs = User::factory()->customerService()->create();
        $blocked = $this->makeDeliverable(DeliverableStatus::INTAKE);
        Block::factory()->create(['deliverable_id' => $blocked->id, 'owner_id' => $cs->id]);

        $groups = app(TodayQuery::class)->forCustomerService($cs)['groups'];
        $this->assertCount(1, $groups);

        $this->assertSame(['customer_service'], array_keys(app(TodayQuery::class)->forUser($cs)));
        $admin = User::factory()->admin()->reviewer()->create();
        $this->assertSame(['manager', 'reviewer'], array_keys(app(TodayQuery::class)->forUser($admin)));
        $this->assertSame(['designer'], array_keys(app(TodayQuery::class)->forUser(User::factory()->designer()->create())));
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $designer = User::factory()->designer()->create();
        $reviewer = User::factory()->reviewer()->create();
        $seed = function (int $times) use ($designer): void {
            foreach (range(1, $times) as $unused) {
                $workOrder = WorkOrder::factory()->create();
                $card = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $workOrder, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);
                $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder);
                Alert::factory()->create(['deliverable_id' => $card->id, 'user_id' => $designer->id]);
                Hold::factory()->create(['deliverable_id' => $card->id]);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(6), function () use ($designer, $reviewer): void {
            app(TodayQuery::class)->forDesigner($designer);
            app(TodayQuery::class)->forReviewer($reviewer);
            app(TodayQuery::class)->forManager();
            app(TodayQuery::class)->forCustomerService(User::factory()->make(['id' => 1]));
        });
    }

    public function test_designer_today_and_wip_leave_out_backlog_work_orders(): void
    {
        $designer = User::factory()->designer()->create(['wip_limit' => 3]);
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25', 'in_backlog' => true]);
        $this->makeDeliverable(DeliverableStatus::READY, null, ['assignee_id' => $designer->id, 'due_target' => '2026-11-20', 'in_backlog' => true]);
        $shown = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);

        $view = app(TodayQuery::class)->forDesigner($designer);

        $this->assertSame([$shown->id], $view['plannedToday']->modelKeys());
        $this->assertCount(0, $view['overdue']);
        $this->assertSame(['used' => 1, 'limit' => 3], $view['wip']);
    }
}
