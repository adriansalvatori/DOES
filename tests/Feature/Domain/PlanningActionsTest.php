<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\AssignDeliverable;
use App\Application\Delivery\Actions\PlanDeliverable;
use App\Application\Delivery\Actions\UnplanDeliverable;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Enums\CoreStatus;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class PlanningActionsTest extends DeliveryTestCase
{
    public function test_planning_intake_work_assigns_it_and_makes_it_ready(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);

        app(PlanDeliverable::class)($deliverable, $designer, Carbon::parse('2026-10-02'), $this->manager());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::READY, $deliverable->status);
        $this->assertSame($designer->id, $deliverable->assignee_id);
        $this->assertSame('2026-10-02', $deliverable->planned_for->toDateString());
        $row = $this->legacyRow($deliverable);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED->value, $row->core_status);
        $this->assertSame('2026-10-02', $row->scheduled_date);
    }

    public function test_replanning_ready_work_changes_the_person_and_day_and_logs_it(): void
    {
        $adrian = $this->designer('Adrián');
        $cesar = $this->designer('César', CoreStatus::CESAR_ORDERS_RECEIVED);
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id, 'planned_for' => '2026-10-01']);

        app(PlanDeliverable::class)($deliverable, $cesar, Carbon::parse('2026-10-05'), $this->manager());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::READY, $deliverable->status);
        $this->assertSame($cesar->id, $deliverable->assignee_id);
        $this->assertSame('2026-10-05', $deliverable->planned_for->toDateString());
        $event = $deliverable->events()->where('event_type', 'deliverable_planned')->sole();
        $this->assertSame($adrian->id, $event->metadata['previous']['assignee_id']);
    }

    public function test_a_blocked_deliverable_cannot_be_planned(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('blocked');

        app(PlanDeliverable::class)($deliverable, $this->designer(), Carbon::parse('2026-10-02'), $this->manager());
    }

    public function test_work_past_design_cannot_be_planned(): void
    {
        $this->expectException(WorkflowException::class);

        app(PlanDeliverable::class)($this->deliverable(DeliverableStatus::CLIENT_REVIEW), $this->designer(), Carbon::parse('2026-10-02'), $this->manager());
    }

    public function test_the_wip_limit_stops_planning_a_fifth_piece_for_the_day(): void
    {
        $designer = $this->designer();
        foreach (range(1, 4) as $unused) {
            $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-02']);
        }

        try {
            app(PlanDeliverable::class)($this->deliverable(DeliverableStatus::INTAKE), $designer, Carbon::parse('2026-10-02'), $this->manager());
            $this->fail('The WIP limit should stop the fifth piece.');
        } catch (WorkflowException $exception) {
            $this->assertStringContainsString('4', $exception->getMessage());
        }

        $other = $this->deliverable(DeliverableStatus::INTAKE);
        app(PlanDeliverable::class)($other, $designer, Carbon::parse('2026-10-05'), $this->manager());
        $this->assertSame(DeliverableStatus::READY, $other->fresh()->status);
    }

    public function test_blocked_pieces_do_not_count_against_the_wip_limit(): void
    {
        $designer = $this->designer();
        foreach (range(1, 4) as $index) {
            $piece = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-02']);
            if ($index <= 2) {
                Block::factory()->create(['deliverable_id' => $piece->id]);
            }
        }
        $fifth = $this->deliverable(DeliverableStatus::INTAKE);

        app(PlanDeliverable::class)($fifth, $designer, Carbon::parse('2026-10-02'), $this->manager());

        $this->assertSame(DeliverableStatus::READY, $fifth->fresh()->status);
    }

    public function test_a_custom_wip_limit_applies(): void
    {
        $designer = $this->designer();
        $designer->update(['wip_limit' => 1]);
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-02']);

        $this->expectException(WorkflowException::class);

        app(PlanDeliverable::class)($this->deliverable(DeliverableStatus::INTAKE), $designer, Carbon::parse('2026-10-02'), $this->manager());
    }

    public function test_a_manager_can_override_the_wip_limit_but_a_designer_cannot(): void
    {
        $designer = $this->designer();
        $designer->update(['wip_limit' => 1]);
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-02']);
        $mine = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id]);

        try {
            app(PlanDeliverable::class)($mine, $designer, Carbon::parse('2026-10-02'), $designer, true);
            $this->fail('A designer cannot override the limit.');
        } catch (WorkflowException) {
        }

        app(PlanDeliverable::class)($mine, $designer, Carbon::parse('2026-10-02'), $this->manager(), true);
        $this->assertSame('2026-10-02', $mine->fresh()->planned_for->toDateString());
    }

    public function test_a_designer_cannot_plan_work_that_is_not_theirs(): void
    {
        $this->expectException(AuthorizationException::class);

        app(PlanDeliverable::class)($this->deliverable(DeliverableStatus::INTAKE), $this->designer(), Carbon::parse('2026-10-02'), $this->designer('Other'));
    }

    public function test_unplanning_ready_work_clears_the_day_and_keeps_the_person(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-10-02']);

        app(UnplanDeliverable::class)($deliverable, $this->manager());

        $deliverable->refresh();
        $this->assertNull($deliverable->planned_for);
        $this->assertSame($designer->id, $deliverable->assignee_id);
        $this->assertNull($this->legacyRow($deliverable)->scheduled_date);
        $this->assertSame(1, $deliverable->events()->where('event_type', 'deliverable_unplanned')->count());
    }

    public function test_unplanning_work_in_design_returns_it_to_ready(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-30']);

        app(UnplanDeliverable::class)($deliverable, $this->manager());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::READY, $deliverable->status);
        $this->assertNull($deliverable->planned_for);
    }

    public function test_unplanning_work_that_is_not_planned_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(UnplanDeliverable::class)($this->deliverable(DeliverableStatus::READY), $this->manager());
    }

    public function test_designers_cannot_unplan(): void
    {
        $this->expectException(AuthorizationException::class);

        app(UnplanDeliverable::class)($this->deliverable(DeliverableStatus::IN_DESIGN), $this->designer());
    }

    public function test_assigning_changes_the_designer_but_keeps_the_planned_day(): void
    {
        $first = $this->designer('Ana');
        $second = $this->designer('Bea');
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $first->id, 'planned_for' => '2026-10-02']);

        $this->assertTrue(app(AssignDeliverable::class)($deliverable, $second, $this->manager()));
        $this->assertFalse(app(AssignDeliverable::class)($deliverable, $second, $this->manager()), 'Same designer again is a no-op.');

        $deliverable->refresh();
        $this->assertSame($second->id, $deliverable->assignee_id);
        $this->assertSame('2026-10-02', $deliverable->planned_for->toDateString());
        $event = $deliverable->events()->where('event_type', 'assignee_changed')->sole();
        $this->assertSame(['from_id' => $first->id, 'to_id' => $second->id], array_intersect_key($event->metadata, array_flip(['from_id', 'to_id'])));
    }

    public function test_assigning_closed_work_or_an_inactive_designer_is_refused(): void
    {
        $inactive = $this->designer('Old');
        $inactive->forceFill(['active' => false])->save();

        try {
            app(AssignDeliverable::class)($this->deliverable(DeliverableStatus::READY), $inactive, $this->manager());
            $this->fail('An inactive designer was accepted.');
        } catch (WorkflowException) {
        }

        $this->expectException(WorkflowException::class);
        app(AssignDeliverable::class)($this->deliverable(DeliverableStatus::DONE), $this->designer(), $this->manager());
    }

    public function test_designers_cannot_reassign_work(): void
    {
        $this->expectException(AuthorizationException::class);

        $designer = $this->designer();
        app(AssignDeliverable::class)($this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id]), null, $designer);
    }
}
