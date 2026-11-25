<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\ApproveByReviewer;
use App\Application\Delivery\Actions\RequestChangesByReviewer;
use App\Application\Delivery\Actions\SendBatchToClient;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\ApprovedByReviewer;
use App\Domain\Delivery\Events\PhaseSentToClient;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

class ReviewActionsTest extends DeliveryTestCase
{
    public function test_the_reviewer_approves_work_in_internal_review(): void
    {
        Event::fake([ApprovedByReviewer::class]);
        $reviewer = $this->reviewer();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::REVIEWER_FOLLOW_UP]);

        $approval = app(ApproveByReviewer::class)($deliverable, 'Looks good', $reviewer);

        $this->assertSame(ApprovalSource::REVIEWER, $approval->source);
        $this->assertSame($reviewer->id, $approval->approved_by);
        $this->assertSame('Looks good', $approval->note);
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $deliverable->fresh()->status);
        Event::assertDispatched(ApprovedByReviewer::class);
    }

    public function test_a_manager_approving_for_the_reviewer_is_recorded_as_a_manager_approval(): void
    {
        $approval = app(ApproveByReviewer::class)($this->deliverable(DeliverableStatus::INTERNAL_REVIEW), null, $this->manager());

        $this->assertSame(ApprovalSource::MANAGER, $approval->source);
    }

    public function test_approving_twice_leaves_one_standing_approval(): void
    {
        $reviewer = $this->reviewer();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $action = app(ApproveByReviewer::class);

        $action($deliverable, null, $reviewer);
        $action($deliverable, null, $reviewer);

        $this->assertSame(1, Approval::standing()->where('deliverable_id', $deliverable->id)->count());
        $this->assertSame(2, Approval::where('deliverable_id', $deliverable->id)->count());
    }

    public function test_only_work_in_internal_review_can_be_approved_by_the_reviewer(): void
    {
        $this->expectException(WorkflowException::class);

        app(ApproveByReviewer::class)($this->deliverable(DeliverableStatus::IN_DESIGN), null, $this->reviewer());
    }

    public function test_a_designer_who_is_not_the_reviewer_cannot_approve(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ApproveByReviewer::class)($this->deliverable(DeliverableStatus::INTERNAL_REVIEW), null, $this->designer());
    }

    public function test_requesting_changes_returns_the_work_to_design_and_resets_the_approval(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $approval = $this->reviewerApproval($deliverable);

        app(RequestChangesByReviewer::class)($deliverable, 'Fix the kerning', $this->reviewer());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->status);
        $this->assertSame(1, $deliverable->internal_revision_count);
        $this->assertNotNull($approval->fresh()->reset_at);
        $this->assertSame('Fix the kerning', $deliverable->events()->where('event_type', 'deliverable_moved')->sole()->metadata['note']);
        $this->assertSame('ENVIAR A CAMILA', $this->legacyRow($deliverable)->substatus);
    }

    public function test_requesting_changes_outside_internal_review_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(RequestChangesByReviewer::class)($this->deliverable(DeliverableStatus::CLIENT_REVIEW), 'x', $this->reviewer());
    }

    /**
     * @return array{0: Phase, 1: array<string, Deliverable>}
     */
    private function phaseWithThreePieces(): array
    {
        $workOrder = WorkOrder::factory()->create();
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id]);
        $pieces = [];
        foreach (['approved', 'blocked', 'held'] as $name) {
            $pieces[$name] = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['phase_id' => $phase->id, 'task_name' => $name], $workOrder);
            $this->reviewerApproval($pieces[$name]);
        }
        Block::factory()->create(['deliverable_id' => $pieces['blocked']->id, 'reason' => BlockReason::MISSING_LOGO]);
        Hold::factory()->create(['deliverable_id' => $pieces['held']->id, 'reason' => HoldReason::PAUSED]);

        return [$phase, $pieces];
    }

    public function test_a_phase_moves_to_the_client_together_and_leaves_blocked_and_held_pieces_behind(): void
    {
        Event::fake([PhaseSentToClient::class]);
        [$phase, $pieces] = $this->phaseWithThreePieces();

        $moved = app(SendBatchToClient::class)($phase, $this->reviewer());

        $this->assertSame(['approved'], $moved->pluck('task_name')->all());
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $pieces['approved']->fresh()->status);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $pieces['blocked']->fresh()->status);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $pieces['held']->fresh()->status);
        Event::assertDispatched(PhaseSentToClient::class, fn (PhaseSentToClient $event): bool => $event->phase->is($phase) && $event->deliverableIds === [$pieces['approved']->id]);
    }

    public function test_a_left_behind_piece_can_be_sent_alone_once_it_is_free(): void
    {
        [$phase, $pieces] = $this->phaseWithThreePieces();
        app(SendBatchToClient::class)($phase, $this->reviewer());
        $pieces['blocked']->blocks()->open()->update(['resolved_at' => now()]);

        $moved = app(SendBatchToClient::class)($pieces['blocked'], $this->reviewer());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $pieces['blocked']->fresh()->status);
        $this->assertCount(1, $moved);
    }

    public function test_sending_a_blocked_piece_alone_is_refused(): void
    {
        [, $pieces] = $this->phaseWithThreePieces();

        $this->expectException(WorkflowException::class);

        app(SendBatchToClient::class)($pieces['blocked'], $this->reviewer());
    }

    public function test_a_batch_is_refused_when_a_ready_piece_lacks_the_reviewer_approval(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $approved = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, [], $workOrder);
        $this->reviewerApproval($approved);
        $unapproved = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['task_name' => 'No approval yet'], $workOrder);

        try {
            app(SendBatchToClient::class)($workOrder, $this->reviewer());
            $this->fail('The batch should be refused.');
        } catch (WorkflowException $exception) {
            $this->assertStringContainsString('NO APPROVAL YET', mb_strtoupper($exception->getMessage()));
        }

        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $approved->fresh()->status);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $unapproved->fresh()->status);
    }

    public function test_a_work_order_without_phases_is_a_batch_of_its_own(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $first = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, [], $workOrder);
        $second = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, [], $workOrder);
        $this->reviewerApproval($first);
        $this->reviewerApproval($second, ApprovalSource::MANAGER);

        $moved = app(SendBatchToClient::class)($workOrder, $this->reviewer());

        $this->assertCount(2, $moved);
        $this->assertSame(2, $workOrder->deliverables()->inStatus(DeliverableStatus::CLIENT_REVIEW)->count());
        $this->assertSame(3, $first->fresh()->alerts()->open()->count());
    }

    public function test_a_work_order_with_phases_needs_the_phase_to_be_chosen(): void
    {
        $workOrder = WorkOrder::factory()->create();
        Phase::factory()->create(['work_order_id' => $workOrder->id]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('phase');

        app(SendBatchToClient::class)($workOrder, $this->reviewer());
    }

    public function test_a_batch_with_nothing_in_internal_review_is_refused(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $this->deliverable(DeliverableStatus::IN_DESIGN, [], $workOrder);

        $this->expectException(WorkflowException::class);

        app(SendBatchToClient::class)($workOrder, $this->reviewer());
    }

    public function test_pieces_of_other_phases_are_not_sent(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $one = Phase::factory()->create(['work_order_id' => $workOrder->id, 'position' => 1]);
        $two = Phase::factory()->create(['work_order_id' => $workOrder->id, 'position' => 2]);
        $inOne = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['phase_id' => $one->id], $workOrder);
        $inTwo = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['phase_id' => $two->id], $workOrder);
        $this->reviewerApproval($inOne);
        $this->reviewerApproval($inTwo);

        app(SendBatchToClient::class)($one, $this->reviewer());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $inOne->fresh()->status);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $inTwo->fresh()->status);
    }
}
