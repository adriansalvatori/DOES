<?php

namespace Tests\Feature\Domain;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Events\ApprovalReset;
use App\Domain\Delivery\Events\DeliverableMoved;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use Illuminate\Support\Facades\Event;

class DeliverableWorkflowTest extends DeliveryTestCase
{
    private DeliverableWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = app(DeliverableWorkflow::class);
    }

    public function test_a_forward_move_writes_the_status_one_event_and_the_legacy_columns(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);

        $this->workflow->transition($deliverable, DeliverableStatus::READY, TransitionReason::MANUAL, $this->manager());

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
        $events = $deliverable->events()->where('event_type', 'deliverable_moved')->get();
        $this->assertCount(1, $events);
        $this->assertSame('intake', $events->first()->previous_value);
        $this->assertSame('ready', $events->first()->new_value);
        $this->assertSame('manual', $events->first()->metadata['reason']);
    }

    public function test_skipping_a_step_is_refused_and_writes_nothing(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);

        try {
            $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL, $this->manager());
            $this->fail('The move should be refused.');
        } catch (WorkflowException $exception) {
            $this->assertStringContainsString('cannot move from', $exception->getMessage());
        }

        $this->assertSame(DeliverableStatus::INTAKE, $deliverable->fresh()->status);
        $this->assertSame(0, $deliverable->events()->count());
    }

    public function test_moving_to_the_current_status_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        $this->workflow->transition($this->deliverable(DeliverableStatus::READY), DeliverableStatus::READY, TransitionReason::MANUAL);
    }

    public function test_a_blocked_deliverable_cannot_move_forward(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('blocked');

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL);
    }

    public function test_a_blocked_deliverable_can_still_be_closed(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL, null, ['close_reason' => CloseReason::CANCELLED_BY_CLIENT]);

        $this->assertSame(DeliverableStatus::DONE, $deliverable->fresh()->status);
        $this->assertNotNull($block->fresh()->resolved_at);
    }

    public function test_a_blocked_deliverable_can_move_backward(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        Block::factory()->create(['deliverable_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL);

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_a_deliverable_on_hold_cannot_move_forward(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::PAUSED]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('on hold');

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL);
    }

    public function test_internal_review_only_leaves_through_a_batch(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $this->reviewerApproval($deliverable);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('batch');

        $this->workflow->transition($deliverable, DeliverableStatus::CLIENT_REVIEW, TransitionReason::MANUAL);
    }

    public function test_internal_review_needs_the_reviewer_approval_to_reach_the_client(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('reviewer');

        $this->workflow->transition($deliverable, DeliverableStatus::CLIENT_REVIEW, TransitionReason::MANUAL, null, ['batch' => true]);
    }

    public function test_a_batch_move_with_the_approval_reaches_the_client_and_schedules_follow_ups(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $this->reviewerApproval($deliverable);

        $this->workflow->transition($deliverable, DeliverableStatus::CLIENT_REVIEW, TransitionReason::MANUAL, null, ['batch' => true]);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->status);
        $this->assertSame('2026-09-30 09:00:00', $deliverable->last_sent_to_client_at->toDateTimeString());
        $this->assertSame(
            ['2026-10-05', '2026-10-08', '2026-10-13'],
            $deliverable->alerts()->open()->orderBy('due_on')->pluck('due_on')->map->toDateString()->all(),
        );
        $this->assertSame(
            [AlertKind::CLIENT_FOLLOW_UP_1, AlertKind::CLIENT_FOLLOW_UP_2, AlertKind::CLIENT_FOLLOW_UP_3],
            $deliverable->alerts()->open()->orderBy('due_on')->get()->map->kind->all(),
        );
    }

    public function test_client_review_cannot_reach_production_files_without_an_approval(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('client approval');

        $this->workflow->transition($deliverable, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL, null, ['client_approval' => true]);
    }

    public function test_an_incomplete_client_approval_does_not_open_production_files(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Approval::factory()->fromClient(true, false)->create(['deliverable_id' => $deliverable->id]);

        $this->expectException(WorkflowException::class);

        $this->workflow->transition($deliverable, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL, null, ['client_approval' => true]);
    }

    public function test_a_complete_client_approval_opens_production_files_with_one_gate_and_the_approval_sla(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Approval::factory()->fromClient()->create(['deliverable_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL, null, ['client_approval' => true]);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->status);
        $this->assertSame('2026-10-01', $deliverable->due_target->toDateString());
        $gates = $deliverable->tasks()->where('kind', TaskKind::GATE_PREPARE_PRODUCTION_FILES->value)->get();
        $this->assertCount(1, $gates);
        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $gates->first()->advances_to);
        $this->assertTrue($gates->first()->required);
    }

    public function test_an_urgent_approval_is_due_the_same_day(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['flag_codes' => ['urgent']]);
        Approval::factory()->fromClient()->create(['deliverable_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL, null, ['client_approval' => true]);

        $this->assertSame('2026-09-30', $deliverable->fresh()->due_target->toDateString());
    }

    public function test_production_files_needs_its_required_gates_done(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->create(['order_id' => $deliverable->id]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('required tasks');

        $this->workflow->transition($deliverable, DeliverableStatus::READY_FOR_PRODUCTION, TransitionReason::MANUAL, $this->designer());
    }

    public function test_production_files_moves_on_when_the_gates_are_done(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->done()->create(['order_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::READY_FOR_PRODUCTION, TransitionReason::MANUAL, $this->designer());

        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $deliverable->fresh()->status);
    }

    public function test_a_manager_may_move_production_files_forward_by_hand(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->create(['order_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::READY_FOR_PRODUCTION, TransitionReason::MANUAL, $this->manager());

        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $deliverable->fresh()->status);
    }

    public function test_fulfillment_needs_a_receipt_or_a_close_reason_to_finish(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::FULFILLMENT);

        try {
            $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL);
            $this->fail('A receipt is required.');
        } catch (WorkflowException $exception) {
            $this->assertStringContainsString('receipt', $exception->getMessage());
        }

        Receipt::factory()->create(['deliverable_id' => $deliverable->id]);
        $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::DONE, $deliverable->status);
        $this->assertSame(CloseReason::COMPLETED, $deliverable->close_reason);
        $this->assertNotNull($deliverable->closed_at);
    }

    public function test_an_earlier_status_cannot_jump_to_done_without_a_close_reason(): void
    {
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Close it with a reason');

        $this->workflow->transition($this->deliverable(DeliverableStatus::IN_PRODUCTION), DeliverableStatus::DONE, TransitionReason::MANUAL);
    }

    public function test_closing_resolves_open_blocks_holds_and_alerts(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id]);
        $hold = Hold::factory()->create(['deliverable_id' => $deliverable->id]);
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1]);

        $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL, null, ['close_reason' => CloseReason::CLIENT_UNRESPONSIVE]);

        $this->assertNotNull($block->fresh()->resolved_at);
        $this->assertNotNull($hold->fresh()->ended_at);
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertSame('client_unresponsive', $deliverable->events()->latest('id')->first()->metadata['close_reason']);
    }

    public function test_only_a_manager_may_unplan_or_reopen(): void
    {
        $inDesign = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $done = $this->deliverable(DeliverableStatus::FULFILLMENT, ['status' => DeliverableStatus::DONE, 'close_reason' => CloseReason::COMPLETED, 'closed_at' => now()]);
        $designer = $this->designer();

        foreach ([[$inDesign, DeliverableStatus::READY], [$done, DeliverableStatus::FULFILLMENT]] as [$deliverable, $to]) {
            try {
                $this->workflow->transition($deliverable, $to, TransitionReason::MANUAL, $designer);
                $this->fail('A designer cannot make this move.');
            } catch (WorkflowException $exception) {
                $this->assertStringContainsString('manager', $exception->getMessage());
            }
        }

        $manager = $this->manager();
        $this->workflow->transition($inDesign, DeliverableStatus::READY, TransitionReason::MANUAL, $manager);
        $this->workflow->transition($done, DeliverableStatus::FULFILLMENT, TransitionReason::MANUAL, $manager);

        $this->assertSame(DeliverableStatus::READY, $inDesign->fresh()->status);
        $done->refresh();
        $this->assertSame(DeliverableStatus::FULFILLMENT, $done->status);
        $this->assertNull($done->close_reason);
        $this->assertNull($done->closed_at);
    }

    public function test_the_reviewer_sending_work_back_counts_an_internal_revision_and_resets_approvals(): void
    {
        Event::fake([ApprovalReset::class, DeliverableMoved::class]);
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $approval = $this->reviewerApproval($deliverable);

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL);

        $this->assertSame(1, $deliverable->fresh()->internal_revision_count);
        $this->assertNotNull($approval->fresh()->reset_at);
        Event::assertDispatched(ApprovalReset::class, fn (ApprovalReset $event): bool => $event->approvalsReset === 1);
    }

    public function test_client_changes_return_to_design_with_two_business_days_and_a_revision(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $this->reviewerApproval($deliverable);
        $followUp = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_2]);

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL, null, ['client_changes' => true]);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->status);
        $this->assertSame('2026-10-02', $deliverable->due_target->toDateString());
        $this->assertSame(1, $deliverable->client_revision_count);
        $this->assertSame('2026-09-30', $deliverable->planned_for->toDateString());
        $this->assertSame(0, Approval::standing()->where('deliverable_id', $deliverable->id)->count());
        $this->assertNotNull($followUp->fresh()->resolved_at);
        $this->assertSame('2026-10-02', $deliverable->events()->latest('id')->first()->metadata['due_target_to']);
    }

    public function test_entering_internal_review_schedules_the_reviewer_follow_up_for_the_next_weekday(): void
    {
        $reviewer = $this->reviewer();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-10-05']);

        $this->workflow->transition($deliverable, DeliverableStatus::INTERNAL_REVIEW, TransitionReason::MANUAL);

        $alert = $deliverable->alerts()->where('kind', AlertKind::REVIEWER_FOLLOW_UP->value)->sole();
        $this->assertSame('2026-10-01', $alert->due_on->toDateString());
        $this->assertSame($reviewer->id, $alert->user_id);
        $this->assertSame($reviewer->id, $deliverable->fresh()->reviewer_id);
    }

    public function test_leaving_internal_review_resolves_the_reviewer_follow_up(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::REVIEWER_FOLLOW_UP]);

        $this->workflow->transition($deliverable, DeliverableStatus::IN_DESIGN, TransitionReason::MANUAL);

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_back_moves_in_production_files_and_the_queue_are_allowed(): void
    {
        $queued = $this->deliverable(DeliverableStatus::PRODUCTION_QUEUE);
        $ready = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);

        $this->workflow->transition($queued, DeliverableStatus::READY_FOR_PRODUCTION, TransitionReason::MANUAL);
        $this->workflow->transition($ready, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL);

        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $queued->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $ready->fresh()->status);
    }

    public function test_other_backward_moves_are_refused(): void
    {
        $this->expectException(WorkflowException::class);

        $this->workflow->transition($this->deliverable(DeliverableStatus::IN_PRODUCTION), DeliverableStatus::PRODUCTION_QUEUE, TransitionReason::MANUAL, $this->manager());
    }

    public function test_the_domain_event_is_dispatched_after_the_transaction_commits(): void
    {
        Event::fake([DeliverableMoved::class]);
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);
        $actor = $this->manager();

        $this->workflow->transition($deliverable, DeliverableStatus::READY, TransitionReason::MANUAL, $actor);

        Event::assertDispatched(DeliverableMoved::class, fn (DeliverableMoved $event): bool => $event->from === DeliverableStatus::INTAKE
            && $event->to === DeliverableStatus::READY
            && $event->reason === TransitionReason::MANUAL
            && $event->actor?->is($actor));
    }

    public function test_a_refused_move_dispatches_no_event(): void
    {
        Event::fake([DeliverableMoved::class]);

        try {
            $this->workflow->transition($this->deliverable(DeliverableStatus::INTAKE), DeliverableStatus::DONE, TransitionReason::MANUAL);
        } catch (WorkflowException) {
        }

        Event::assertNotDispatched(DeliverableMoved::class);
    }

    public function test_entering_production_files_again_reopens_the_same_gate_instead_of_adding_one(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);
        $gate = Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->done()->create(['order_id' => $deliverable->id]);

        $this->workflow->transition($deliverable, DeliverableStatus::PRODUCTION_FILES, TransitionReason::MANUAL);

        $this->assertSame(1, $deliverable->tasks()->count());
        $this->assertSame('todo', $gate->fresh()->status);
    }
}
