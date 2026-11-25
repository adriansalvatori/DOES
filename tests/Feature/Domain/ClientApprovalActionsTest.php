<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\ApproveAllInClientReview;
use App\Application\Delivery\Actions\RecordClientApproval;
use App\Application\Delivery\Actions\RecordClientChanges;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\ApprovedByClient;
use App\Domain\Delivery\Events\ClientResponded;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Support\Facades\Event;

class ClientApprovalActionsTest extends DeliveryTestCase
{
    private function approve($deliverable, bool $measurements, bool $estimate, ApprovalSource $source = ApprovalSource::CLIENT)
    {
        return app(RecordClientApproval::class)($deliverable, $source, $measurements, $estimate, 'By email', 'proofs/ok.png', $this->customerService());
    }

    public function test_a_complete_client_approval_moves_the_deliverable_to_production_files(): void
    {
        Event::fake([ApprovedByClient::class]);
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $approval = $this->approve($deliverable, true, true);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->status);
        $this->assertSame('2026-10-01', $deliverable->due_target->toDateString());
        $this->assertTrue($approval->measurements_confirmed && $approval->estimate_approved);
        $this->assertSame('proofs/ok.png', $approval->evidence_path);
        $this->assertSame('By email', $approval->note);
        $this->assertSame(1, $deliverable->tasks()->where('kind', 'gate_prepare_production_files')->count());
        $this->assertSame(0, $deliverable->blocks()->count());
        $row = $this->legacyRow($deliverable);
        $this->assertSame('PONER EN ALTA', $row->substatus);
        $this->assertSame(1, (int) $row->measures_confirmed);
        $this->assertSame(1, (int) $row->estimate_approved);
        Event::assertDispatched(ApprovedByClient::class);
    }

    public function test_the_client_approval_move_is_logged_with_the_real_previous_status(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->approve($deliverable, true, true);

        $move = $deliverable->events()->where('event_type', 'deliverable_moved')->sole();
        $this->assertSame('client_review', $move->previous_value);
        $this->assertSame('production_files', $move->new_value);
    }

    public function test_an_urgent_approval_keeps_the_same_day_due_date(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['flag_codes' => ['urgent']]);

        $this->approve($deliverable, true, true);

        $this->assertSame('2026-09-30', $deliverable->fresh()->due_target->toDateString());
    }

    public function test_missing_measurements_block_the_piece_for_customer_service_and_keep_it_in_client_review(): void
    {
        $service = $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $other = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $deliverable->workOrder);

        $this->approve($deliverable, false, true);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->status);
        $block = $deliverable->blocks()->open()->sole();
        $this->assertSame(BlockReason::MISSING_MEASUREMENTS, $block->reason);
        $this->assertSame($service->id, $block->owner_id);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $other->fresh()->status);
        $this->assertFalse($other->isBlocked());
        $this->assertSame('FALTAN MEDIDAS', $this->legacyRow($deliverable)->blocking_reason);
    }

    public function test_a_missing_estimate_approval_raises_its_own_block_without_looping(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->approve($deliverable, true, false);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->status);
        $this->assertSame(BlockReason::AWAITING_ESTIMATE_APPROVAL, $deliverable->blocks()->open()->sole()->reason);
        $this->assertSame('FALTA APROBACIÓN DE ESTIMADO', $this->legacyRow($deliverable)->substatus);
        $this->assertSame(1, (int) $this->legacyRow($deliverable)->approved);
    }

    public function test_missing_both_raises_both_blocks(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->approve($deliverable, false, false);

        $this->assertEqualsCanonicalizing(
            [BlockReason::MISSING_MEASUREMENTS, BlockReason::AWAITING_ESTIMATE_APPROVAL],
            $deliverable->blocks()->open()->get()->map->reason->all(),
        );
    }

    public function test_a_second_approval_with_the_missing_item_clears_the_block_and_moves_on(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $this->approve($deliverable, false, true);

        $this->approve($deliverable, true, true);

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->status);
        $this->assertSame(0, $deliverable->blocks()->open()->count());
        $this->assertSame(1, Approval::standing()->where('deliverable_id', $deliverable->id)->count());
    }

    public function test_the_same_missing_item_does_not_duplicate_its_block(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->approve($deliverable, false, true);
        $this->approve($deliverable, false, true);

        $this->assertSame(1, $deliverable->blocks()->open()->count());
    }

    public function test_a_complete_approval_waits_for_an_unrelated_block(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        $this->approve($deliverable, true, true);

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->fresh()->status);
    }

    public function test_a_manager_can_approve_on_behalf_of_the_client(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $approval = $this->approve($deliverable, true, true, ApprovalSource::MANAGER);

        $this->assertSame(ApprovalSource::MANAGER, $approval->source);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
        $this->assertSame('manager', $this->legacyRow($deliverable)->approval_type);
    }

    public function test_the_reviewer_cannot_record_a_client_approval(): void
    {
        $this->expectException(WorkflowException::class);

        $this->approve($this->deliverable(DeliverableStatus::CLIENT_REVIEW), true, true, ApprovalSource::REVIEWER);
    }

    public function test_only_work_waiting_for_the_client_can_be_approved(): void
    {
        $this->expectException(WorkflowException::class);

        $this->approve($this->deliverable(DeliverableStatus::IN_DESIGN), true, true);
    }

    public function test_approving_a_deliverable_on_hold_records_it_but_does_not_move_it(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::NO_RESPONSE]);

        $this->approve($deliverable, true, true);

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->fresh()->status);
        $this->assertSame(1, Approval::standing()->where('deliverable_id', $deliverable->id)->count());
    }

    public function test_approve_all_covers_only_the_work_waiting_for_the_client(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $first = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $workOrder);
        $second = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $workOrder);
        $inDesign = $this->deliverable(DeliverableStatus::IN_DESIGN, [], $workOrder);
        $held = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $workOrder);
        Hold::factory()->create(['deliverable_id' => $held->id]);

        $approvals = app(ApproveAllInClientReview::class)($workOrder, ApprovalSource::CLIENT, true, true, null, null, $this->customerService());

        $this->assertCount(2, $approvals);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $first->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $second->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $inDesign->fresh()->status);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $held->fresh()->status);
        $this->assertSame(0, $inDesign->approvals()->count());
    }

    public function test_approve_all_with_missing_measurements_blocks_each_waiting_piece(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $first = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $workOrder);
        $second = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [], $workOrder);

        app(ApproveAllInClientReview::class)($workOrder, ApprovalSource::CLIENT, false, true, null, null, $this->customerService());

        $this->assertTrue($first->fresh()->isBlocked());
        $this->assertTrue($second->fresh()->isBlocked());
    }

    public function test_approve_all_with_nothing_waiting_is_refused(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $this->deliverable(DeliverableStatus::IN_DESIGN, [], $workOrder);

        $this->expectException(WorkflowException::class);

        app(ApproveAllInClientReview::class)($workOrder, ApprovalSource::CLIENT, true, true, null, null, $this->customerService());
    }

    public function test_client_changes_really_return_the_work_to_design(): void
    {
        Event::fake([ClientResponded::class]);
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $this->reviewerApproval($deliverable);
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1]);

        app(RecordClientChanges::class)($deliverable, 'Bigger logo', $this->customerService());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->status);
        $this->assertSame('2026-10-02', $deliverable->due_target->toDateString());
        $this->assertSame(1, $deliverable->client_revision_count);
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertSame(0, Approval::standing()->where('deliverable_id', $deliverable->id)->count());
        $row = $this->legacyRow($deliverable);
        $this->assertSame('TO DO TODAY', $row->core_status);
        $this->assertSame('CAMBIOS CLIENTE', $row->substatus);
        Event::assertDispatched(ClientResponded::class, fn (ClientResponded $event): bool => $event->note === 'Bigger logo');
    }

    public function test_client_changes_outside_client_review_are_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(RecordClientChanges::class)($this->deliverable(DeliverableStatus::INTERNAL_REVIEW), null, $this->customerService());
    }
}
