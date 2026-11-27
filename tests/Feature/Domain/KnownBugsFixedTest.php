<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\AddDeliverable;
use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Actions\ApproveByReviewer;
use App\Application\Delivery\Actions\BlockDeliverable;
use App\Application\Delivery\Actions\CompleteTask;
use App\Application\Delivery\Actions\CreateWorkOrder;
use App\Application\Delivery\Actions\CreateWorkOrderData;
use App\Application\Delivery\Actions\DuplicateDeliverable;
use App\Application\Delivery\Actions\MoveDeliverable;
use App\Application\Delivery\Actions\PutOnHold;
use App\Application\Delivery\Actions\RecordClientApproval;
use App\Application\Delivery\Actions\RecordClientChanges;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\RunDailyCycle;
use App\Application\Delivery\Actions\UnplanDeliverable;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Domain\Scheduling\SlaPolicy;
use App\Domain\Scheduling\SlaState;
use Carbon\Carbon;

/**
 * The defects documented by tests/Feature/Characterization/*KnownBugs* behave correctly in the new layer.
 * Each test names the characterization test it replaces.
 */
class KnownBugsFixedTest extends DeliveryTestCase
{
    public function test_follow_up_three_is_created_on_the_ninth_business_day(): void
    {
        // was: AutomationKnownBugsTest::test_follow_up_three_is_created_on_the_ninth_weekday
        $this->freezeAt('2026-10-13 07:00:00');
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);

        app(RunDailyCycle::class)(false);

        $this->assertSame(1, $deliverable->alerts()->where('kind', AlertKind::CLIENT_FOLLOW_UP_3->value)->count());
        $this->assertFalse($deliverable->fresh()->isOnHold());
    }

    public function test_the_start_event_records_the_real_previous_status(): void
    {
        // was: AutomationKnownBugsTest::test_scheduled_promotion_event_records_the_real_previous_status
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $this->designer()->id, 'planned_for' => '2026-09-30']);

        app(RunDailyCycle::class)(false);

        $this->assertSame('ready', $deliverable->events()->where('event_type', 'deliverable_moved')->sole()->previous_value);
    }

    public function test_the_daily_cycle_never_sends_unapproved_work_to_production(): void
    {
        // was: AutomationKnownBugsTest::test_daily_automation_does_not_send_an_unapproved_order_to_production
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->done()->create(['order_id' => $deliverable->id]);

        app(RunDailyCycle::class)(false);

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_marking_work_done_cannot_reach_production_without_the_client_approval(): void
    {
        // was: AutomationKnownBugsTest::test_hoy_mark_done_does_not_send_an_unapproved_order_to_production
        //      and EntryKnownBugsTest::test_hoy_mark_done_today_does_not_reach_production_without_approval
        $designer = $this->designer();
        $inDesign = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id]);
        $inClientReview = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['assignee_id' => $designer->id]);
        $move = app(MoveDeliverable::class);

        foreach ([[$inDesign, DeliverableStatus::READY_FOR_PRODUCTION], [$inDesign, DeliverableStatus::PRODUCTION_FILES], [$inClientReview, DeliverableStatus::PRODUCTION_FILES]] as [$deliverable, $target]) {
            try {
                $move($deliverable, $target, $designer);
                $this->fail("Moving to {$target->value} should be refused.");
            } catch (WorkflowException) {
            }
        }

        $this->assertSame(DeliverableStatus::IN_DESIGN, $inDesign->fresh()->status);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $inClientReview->fresh()->status);
    }

    public function test_an_approval_that_reaches_production_files_logs_a_status_change(): void
    {
        // was: AutomationKnownBugsTest::test_approval_with_a_production_target_logs_a_core_status_changed_event
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        app(RecordClientApproval::class)($deliverable, ApprovalSource::CLIENT, true, true, null, null, $this->manager());

        $move = $deliverable->events()->where('event_type', 'deliverable_moved')->sole();
        $this->assertSame(['client_review', 'production_files'], [$move->previous_value, $move->new_value]);
    }

    public function test_approval_does_not_touch_tasks_whose_title_merely_contains_alta(): void
    {
        // was: AutomationKnownBugsTest::test_approval_to_production_does_not_close_unrelated_tasks_whose_title_contains_alta_inside_falta
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $falta = Task::factory()->create(['order_id' => $deliverable->id, 'title' => 'Falta logo del cliente']);

        app(RecordClientApproval::class)($deliverable, ApprovalSource::CLIENT, true, true, null, null, $this->manager());

        $this->assertSame('todo', $falta->fresh()->status);
        $this->assertSame(1, $deliverable->tasks()->where('kind', TaskKind::GATE_PREPARE_PRODUCTION_FILES->value)->count());
    }

    public function test_completing_a_falta_task_never_moves_an_approved_deliverable(): void
    {
        // was: AutomationKnownBugsTest::test_completed_falta_task_does_not_auto_return_an_approved_order_to_production
        //      and SupportKnownBugsTest::test_falta_logo_is_not_detected_as_poner_en_alta
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $this->reviewerApproval($deliverable);
        $falta = app(AddTask::class)($deliverable, 'Falta logo del cliente', actor: $this->manager());

        app(CompleteTask::class)($falta, $this->manager());

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertSame(0, $deliverable->events()->where('event_type', 'deliverable_moved')->count());
    }

    public function test_overdue_is_computed_without_any_job_or_sync(): void
    {
        // was: AutomationKnownBugsTest::test_overdue_flags_are_refreshed_by_the_daily_automations
        //      and SupportKnownBugsTest::test_overdue_flags_are_refreshed_without_a_trello_sync
        $late = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-29']);
        $today = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-30']);
        $sla = app(SlaPolicy::class);

        $this->assertSame(SlaState::OVERDUE, $sla->stateFor($late));
        $this->assertSame(SlaState::ALMOST_OVERDUE, $sla->stateFor($today));
        $this->freezeAt('2026-09-30 16:00:00');
        $this->assertSame(SlaState::OVERDUE, $sla->stateFor($today));
        $this->assertArrayNotHasKey('overdue', $late->getAttributes());
    }

    public function test_entering_internal_review_when_already_due_asks_the_reviewer_for_a_same_day_call(): void
    {
        // was: AutomationKnownBugsTest::test_entering_camila_when_due_today_creates_a_call_task
        $this->reviewer();
        $overdue = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-28']);
        $comfortable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-10-05']);
        $workflow = app(DeliverableWorkflow::class);

        $workflow->transition($overdue, DeliverableStatus::INTERNAL_REVIEW, TransitionReason::MANUAL);
        $workflow->transition($comfortable, DeliverableStatus::INTERNAL_REVIEW, TransitionReason::MANUAL);

        $this->assertSame('2026-09-30', $overdue->alerts()->where('kind', AlertKind::REVIEWER_FOLLOW_UP->value)->sole()->due_on->toDateString());
        $this->assertSame('2026-10-01', $comfortable->alerts()->where('kind', AlertKind::REVIEWER_FOLLOW_UP->value)->sole()->due_on->toDateString());
    }

    public function test_a_client_reply_leaves_the_work_in_design_with_the_client_changes_substatus(): void
    {
        // was: AutomationKnownBugsTest::test_client_reply_leaves_the_order_in_the_requested_work_status
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        app(RecordClientChanges::class)($deliverable, null, $this->customerService());

        $row = $this->legacyRow($deliverable);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertSame('TO DO TODAY', $row->core_status);
        $this->assertSame('CAMBIOS CLIENTE', $row->substatus);
    }

    public function test_a_hold_keeps_the_no_response_substatus(): void
    {
        // was: AutomationKnownBugsTest::test_hold_keeps_the_no_response_substatus
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        app(PutOnHold::class)($deliverable, HoldReason::NO_RESPONSE, null, $this->customerService());

        $row = $this->legacyRow($deliverable);
        $this->assertSame('ON HOLD', $row->core_status);
        $this->assertSame('NO RESPUESTA', $row->substatus);
    }

    public function test_an_approval_missing_only_the_estimate_completes_without_recursing(): void
    {
        // was: AutomationKnownBugsTest::test_approval_missing_only_the_estimate_from_working_today_completes_without_recursing
        //      and EntryKnownBugsTest::test_approval_without_estimate_completes_and_waits_for_estimate
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $approval = app(RecordClientApproval::class)($deliverable, ApprovalSource::CLIENT, true, false, null, null, $this->manager());

        $this->assertNotNull($approval->approved_at);
        $this->assertSame(BlockReason::AWAITING_ESTIMATE_APPROVAL, $deliverable->blocks()->open()->sole()->reason);
        $this->assertSame('FALTA APROBACIÓN DE ESTIMADO', $this->legacyRow($deliverable)->substatus);
        $this->assertSame(1, (int) $this->legacyRow($deliverable)->approved);
    }

    public function test_approving_a_blocked_deliverable_creates_a_single_production_files_gate(): void
    {
        // was: AutomationKnownBugsTest::test_approving_a_blocked_order_creates_a_single_poner_en_alta_task
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $block = app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, null, $this->manager());

        app(RecordClientApproval::class)($deliverable, ApprovalSource::CLIENT, true, true, null, null, $this->manager());
        $this->assertSame(0, $deliverable->tasks()->count());

        app(ResolveBlock::class)($block, null, $this->manager());

        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
        $this->assertSame(1, $deliverable->tasks()->where('kind', TaskKind::GATE_PREPARE_PRODUCTION_FILES->value)->count());
    }

    public function test_a_task_added_by_hand_survives_status_changes(): void
    {
        // was: EntryKnownBugsTest::test_modal_created_subtask_survives_the_order_changing_status
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $task = app(AddTask::class)($deliverable, 'Call the landlord', actor: $this->manager());
        $this->reviewerApproval($deliverable);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::INTERNAL_REVIEW, $this->manager());
        app(ApproveByReviewer::class)($deliverable, null, $this->reviewer());
        app(MoveDeliverable::class)($deliverable, DeliverableStatus::IN_DESIGN, $this->manager());

        $this->assertNotSoftDeleted($task);
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_the_due_date_history_records_the_previous_date(): void
    {
        // was: EntryKnownBugsTest::test_save_order_due_date_history_records_the_previous_date
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['due_target' => '2026-09-18']);

        app(RecordClientChanges::class)($deliverable, null, $this->customerService());

        $metadata = $deliverable->events()->where('event_type', 'deliverable_moved')->sole()->metadata;
        $this->assertSame('2026-09-18', $metadata['due_target_from']);
        $this->assertSame('2026-10-02', $metadata['due_target_to']);
    }

    public function test_a_duplicate_runs_the_creation_rules(): void
    {
        // was: EntryKnownBugsTest::test_duplicate_current_order_runs_created_automations
        $source = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-01']);

        $copy = app(DuplicateDeliverable::class)($source, $this->manager());

        $this->assertSame('2026-10-05', $copy->due_target->toDateString());
        $this->assertSame(1, $this->eventCount($copy, 'deliverable_created'));
    }

    public function test_the_promised_date_the_user_chose_is_never_overwritten_by_the_sla(): void
    {
        // was: EntryKnownBugsTest::test_create_order_keeps_the_due_date_the_user_chose
        $workOrder = app(CreateWorkOrder::class)(
            new CreateWorkOrderData(deliverables: [['name' => 'A']], promisedDate: Carbon::parse('2026-11-20')),
            $this->manager(),
        );

        $this->assertSame('2026-11-20', $workOrder->fresh()->promised_date->toDateString());
        $this->assertSame('2026-10-05', $workOrder->deliverables->first()->due_target->toDateString());
    }

    public function test_unplanning_goes_through_the_workflow_and_leaves_history(): void
    {
        // was: EntryKnownBugsTest::test_planner_unschedule_order_goes_through_status_automations
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['planned_for' => '2026-09-30']);

        app(UnplanDeliverable::class)($deliverable, $this->manager());

        $move = $deliverable->events()->where('event_type', 'deliverable_moved')->sole();
        $this->assertSame(['in_design', 'ready'], [$move->previous_value, $move->new_value]);
    }

    public function test_each_task_completion_is_logged_and_evaluated_once(): void
    {
        // was: EntryKnownBugsTest::test_planner_bulk_complete_logs_each_completion_once
        //      and SupportKnownBugsTest::test_auto_done_evaluation_runs_once_per_task_update
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $gate = Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->create(['order_id' => $deliverable->id]);
        $work = Task::factory()->create(['order_id' => $deliverable->id]);

        app(CompleteTask::class)($work, $this->manager());
        app(CompleteTask::class)($gate, $this->manager());

        $this->assertSame(2, $this->eventCount($deliverable, 'task_completed'));
        $this->assertSame(1, $this->eventCount($deliverable, 'deliverable_moved'));
    }

    public function test_work_in_the_backlog_stays_there_when_it_gets_a_welcome_alert(): void
    {
        // was: SupportKnownBugsTest::test_new_trello_cards_stay_in_the_backlog_even_when_a_welcome_task_is_created
        $workOrder = WorkOrder::factory()->create();
        Deliverable::factory()->inBacklog()->for($workOrder, 'workOrder')->create();

        $deliverable = app(AddDeliverable::class)($workOrder, 'Imported card', actor: $this->manager());
        app(AlertScheduler::class)->scheduleWelcomeEmail($workOrder, null);

        $this->assertSame(0, (int) $this->legacyRow($deliverable)->in_workspace);
    }

    public function test_a_blocked_deliverable_is_planned_nowhere_and_started_by_nothing(): void
    {
        // blocked work never enters the day lists (replaces the blocked-work rules of AutomationDailyCycleTest)
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);
        Block::factory()->create(['deliverable_id' => $deliverable->id]);

        app(RunDailyCycle::class)(false);

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
    }

    public function test_duplicating_a_deliverable_whose_work_order_is_in_the_trash_is_refused_with_a_message(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $deliverable->workOrder->delete();

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Its work order is in the trash.');

        app(DuplicateDeliverable::class)($deliverable->fresh(), $this->manager());
    }
}
