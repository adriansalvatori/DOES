<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Actions\CompleteTask;
use App\Application\Delivery\Actions\ReopenTask;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;

class TaskActionsTest extends DeliveryTestCase
{
    private function gate(Deliverable $deliverable, DeliverableStatus $advancesTo, string $title = 'Gate'): Task
    {
        return Task::factory()->gate(TaskKind::GATE_CUSTOM, $advancesTo)->create(['order_id' => $deliverable->id, 'title' => $title]);
    }

    public function test_add_task_creates_a_plain_work_task(): void
    {
        $task = app(AddTask::class)($this->deliverable(DeliverableStatus::IN_DESIGN), '  Order the vinyl ', actor: $this->designer());

        $this->assertSame('Order the vinyl', $task->title);
        $this->assertSame(TaskKind::WORK, $task->kind);
        $this->assertFalse($task->required);
        $this->assertNull($task->advances_to);
        $this->assertSame('todo', $task->status);
    }

    public function test_add_task_creates_a_required_gate_that_names_its_target(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);

        $task = app(AddTask::class)($deliverable, 'Export the cut files', TaskKind::GATE_CUSTOM, DeliverableStatus::READY_FOR_PRODUCTION, actor: $this->manager());

        $this->assertTrue($task->required);
        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $task->advances_to);
        $this->assertSame(1, $deliverable->events()->where('event_type', 'task_added')->count());
    }

    public function test_a_gate_without_a_target_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(AddTask::class)($this->deliverable(), 'Gate', TaskKind::GATE_CUSTOM, null, null, $this->manager());
    }

    public function test_completing_a_work_task_never_moves_the_deliverable(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $task = Task::factory()->create(['order_id' => $deliverable->id, 'title' => 'Poner en alta']);

        app(CompleteTask::class)($task, $this->manager());

        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
    }

    public function test_completing_the_last_required_gate_advances_the_deliverable(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $task = $this->gate($deliverable, DeliverableStatus::READY_FOR_PRODUCTION);

        app(CompleteTask::class)($task, $this->designer());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $deliverable->status);
        $move = $deliverable->events()->where('event_type', 'deliverable_moved')->sole();
        $this->assertSame('task_completed', $move->metadata['reason']);
        $this->assertSame($task->id, $move->metadata['task_id']);
        $this->assertSame('EN PRODUCCIÓN', $this->legacyRow($deliverable)->core_status);
    }

    public function test_the_deliverable_waits_until_every_required_gate_is_done(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $first = $this->gate($deliverable, DeliverableStatus::INTERNAL_REVIEW, 'First');
        $second = $this->gate($deliverable, DeliverableStatus::INTERNAL_REVIEW, 'Second');

        app(CompleteTask::class)($first, $this->manager());
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);

        app(CompleteTask::class)($second, $this->manager());
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $deliverable->fresh()->status);
    }

    public function test_a_gate_for_a_later_status_does_not_advance_the_deliverable(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $task = $this->gate($deliverable, DeliverableStatus::PRODUCTION_FILES);

        app(CompleteTask::class)($task, $this->manager());

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_gates_never_skip_the_review_and_approval_exit_rules(): void
    {
        $inReview = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $forProof = $this->gate($inReview, DeliverableStatus::CLIENT_REVIEW);
        $inClient = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $forFiles = $this->gate($inClient, DeliverableStatus::PRODUCTION_FILES);

        app(CompleteTask::class)($forProof, $this->manager());
        app(CompleteTask::class)($forFiles, $this->manager());

        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $inReview->fresh()->status);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $inClient->fresh()->status);
    }

    public function test_a_blocked_deliverable_is_not_advanced_by_its_gate(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);
        $task = $this->gate($deliverable, DeliverableStatus::READY_FOR_PRODUCTION);

        app(CompleteTask::class)($task, $this->manager());

        $this->assertSame('done', $task->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
    }

    public function test_completing_a_done_task_changes_nothing(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $task = $this->gate($deliverable, DeliverableStatus::READY_FOR_PRODUCTION);
        app(CompleteTask::class)($task, $this->manager());

        app(CompleteTask::class)($task, $this->manager());

        $this->assertSame(1, $deliverable->events()->where('event_type', 'deliverable_moved')->count());
        $this->assertSame(1, $deliverable->events()->where('event_type', 'task_completed')->count());
    }

    public function test_reopening_the_gate_reverses_the_move_it_caused(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $task = $this->gate($deliverable, DeliverableStatus::READY_FOR_PRODUCTION);
        app(CompleteTask::class)($task, $this->manager());

        app(ReopenTask::class)($task, $this->manager());

        $this->assertSame('todo', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
        $this->assertSame(0, $deliverable->alerts()->count());
    }

    public function test_reopening_after_the_deliverable_went_further_raises_an_alert_instead(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES, ['assignee_id' => $designer->id]);
        $task = $this->gate($deliverable, DeliverableStatus::READY_FOR_PRODUCTION);
        app(CompleteTask::class)($task, $this->manager());
        $deliverable->update(['status' => DeliverableStatus::IN_PRODUCTION]);

        app(ReopenTask::class)($task, $this->manager());

        $this->assertSame(DeliverableStatus::IN_PRODUCTION, $deliverable->fresh()->status);
        $alert = $deliverable->alerts()->sole();
        $this->assertSame(AlertKind::TASK_REOPENED, $alert->kind);
        $this->assertSame($designer->id, $alert->user_id);
    }

    public function test_reopening_does_not_reverse_a_move_made_by_someone_else(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);
        $task = Task::factory()->gate(TaskKind::GATE_CUSTOM, DeliverableStatus::READY_FOR_PRODUCTION)->done()->create(['order_id' => $deliverable->id]);

        app(ReopenTask::class)($task, $this->manager());

        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $deliverable->fresh()->status);
        $this->assertSame(AlertKind::TASK_REOPENED, $deliverable->alerts()->sole()->kind);
    }

    public function test_reopening_a_work_task_only_reopens_it(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $task = Task::factory()->done()->create(['order_id' => $deliverable->id]);

        app(ReopenTask::class)($task, $this->manager());

        $this->assertSame('todo', $task->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_a_task_named_after_alta_has_no_effect_because_only_data_decides(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $task = Task::factory()->create(['order_id' => $deliverable->id, 'title' => 'Falta logo del cliente']);

        app(CompleteTask::class)($task, $this->manager());

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertSame(0, $deliverable->events()->where('event_type', 'deliverable_moved')->count());
    }
}
