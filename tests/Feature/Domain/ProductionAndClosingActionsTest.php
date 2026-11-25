<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\CloseDeliverable;
use App\Application\Delivery\Actions\LogReceipt;
use App\Application\Delivery\Actions\MarkProduced;
use App\Application\Delivery\Actions\MoveDeliverable;
use App\Application\Delivery\Actions\PullToProduction;
use App\Application\Delivery\Actions\ReopenDeliverable;
use App\Application\Delivery\Actions\StartProduction;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Events\ReceiptLogged;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

class ProductionAndClosingActionsTest extends DeliveryTestCase
{
    public function test_production_moves_follow_the_queue_in_order(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);

        app(PullToProduction::class)($deliverable, $this->manager());
        $this->assertSame(DeliverableStatus::PRODUCTION_QUEUE, $deliverable->fresh()->status);

        app(StartProduction::class)($deliverable, $this->manager());
        $this->assertSame(DeliverableStatus::IN_PRODUCTION, $deliverable->fresh()->status);

        app(MarkProduced::class)($deliverable, $this->manager());
        $this->assertSame(DeliverableStatus::PRODUCED, $deliverable->fresh()->status);
        $this->assertSame(3, $deliverable->events()->where('event_type', 'deliverable_moved')->count());
    }

    public function test_a_production_move_out_of_order_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(StartProduction::class)($this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION), $this->manager());
    }

    public function test_pulling_a_blocked_deliverable_is_refused(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::OTHER]);

        $this->expectException(WorkflowException::class);

        app(PullToProduction::class)($deliverable, $this->manager());
    }

    public function test_sales_cannot_move_work_in_production(): void
    {
        $this->expectException(AuthorizationException::class);

        app(PullToProduction::class)($this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION), User::factory()->sales()->create());
    }

    public function test_the_manual_move_action_delegates_to_the_workflow(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCED);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::FULFILLMENT, $this->manager(), 'Ready to hand over');

        $this->assertSame(DeliverableStatus::FULFILLMENT, $deliverable->fresh()->status);
        $this->assertSame('Ready to hand over', $deliverable->events()->sole()->metadata['note']);
    }

    public function test_the_manual_move_can_assign_and_plan_when_leaving_intake(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::READY, $this->manager(), null, $designer->id, Carbon::parse('2026-10-01'));

        $deliverable->refresh();
        $this->assertSame($designer->id, $deliverable->assignee_id);
        $this->assertSame('2026-10-01', $deliverable->planned_for->toDateString());
    }

    public function test_the_manual_move_cannot_close_a_deliverable_without_a_reason(): void
    {
        $this->expectException(WorkflowException::class);

        app(MoveDeliverable::class)($this->deliverable(DeliverableStatus::IN_PRODUCTION), DeliverableStatus::DONE, $this->manager());
    }

    public function test_logging_a_receipt_completes_a_produced_deliverable_through_fulfillment(): void
    {
        Event::fake([ReceiptLogged::class]);
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCED);
        $logger = $this->designer();

        $receipts = app(LogReceipt::class)($deliverable, FulfillmentMethod::PICKUP, $logger, Carbon::parse('2026-09-30 08:00:00'), 'Picked up by owner');

        $this->assertCount(1, $receipts);
        $receipt = $receipts->first();
        $this->assertSame(FulfillmentMethod::PICKUP, $receipt->method);
        $this->assertSame($logger->id, $receipt->logged_by);
        $this->assertSame('2026-09-30 08:00:00', $receipt->received_at->toDateTimeString());
        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::DONE, $deliverable->status);
        $this->assertSame(CloseReason::COMPLETED, $deliverable->close_reason);
        $this->assertSame(['produced', 'fulfillment'], $deliverable->events()->where('event_type', 'deliverable_moved')->orderBy('id')->pluck('previous_value')->all());
        $this->assertSame('FINALIZADA !', $this->legacyRow($deliverable)->substatus);
        Event::assertDispatched(ReceiptLogged::class);
    }

    public function test_logging_a_receipt_for_a_work_order_covers_each_waiting_piece_and_leaves_the_rest(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $produced = $this->deliverable(DeliverableStatus::PRODUCED, [], $workOrder);
        $inFulfillment = $this->deliverable(DeliverableStatus::FULFILLMENT, [], $workOrder);
        $producing = $this->deliverable(DeliverableStatus::IN_PRODUCTION, [], $workOrder);
        $blocked = $this->deliverable(DeliverableStatus::PRODUCED, [], $workOrder);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $held = $this->deliverable(DeliverableStatus::PRODUCED, [], $workOrder);
        Hold::factory()->create(['deliverable_id' => $held->id]);

        $receipts = app(LogReceipt::class)($workOrder, FulfillmentMethod::DELIVERY, $this->manager());

        $this->assertCount(2, $receipts);
        $this->assertSame(DeliverableStatus::DONE, $produced->fresh()->status);
        $this->assertSame(DeliverableStatus::DONE, $inFulfillment->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_PRODUCTION, $producing->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCED, $blocked->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCED, $held->fresh()->status);
    }

    public function test_a_receipt_for_work_that_was_not_produced_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(LogReceipt::class)($this->deliverable(DeliverableStatus::IN_PRODUCTION), FulfillmentMethod::PICKUP, $this->manager());
    }

    public function test_a_receipt_for_a_work_order_with_nothing_to_receive_is_refused(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $this->deliverable(DeliverableStatus::IN_DESIGN, [], $workOrder);

        $this->expectException(WorkflowException::class);

        app(LogReceipt::class)($workOrder, FulfillmentMethod::PICKUP, $this->manager());
    }

    public function test_a_blocked_piece_cannot_be_received_alone_and_nothing_is_written(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCED);
        Block::factory()->create(['deliverable_id' => $deliverable->id]);

        try {
            app(LogReceipt::class)($deliverable, FulfillmentMethod::PICKUP, $this->manager());
            $this->fail('A blocked piece cannot be received.');
        } catch (WorkflowException) {
        }

        $this->assertSame(0, $deliverable->receipts()->count());
        $this->assertSame(DeliverableStatus::PRODUCED, $deliverable->fresh()->status);
    }

    public function test_closing_records_the_reason_from_any_status(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        app(CloseDeliverable::class)($deliverable, CloseReason::CANCELLED_BY_CLIENT, 'Client changed plans', $this->manager());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::DONE, $deliverable->status);
        $this->assertSame(CloseReason::CANCELLED_BY_CLIENT, $deliverable->close_reason);
        $this->assertNotNull($deliverable->closed_at);
        $this->assertSame('CANCELADA POR CLIENTE', $this->legacyRow($deliverable)->substatus);
        $this->assertSame('Client changed plans', $deliverable->events()->sole()->metadata['note']);
    }

    public function test_only_managers_close(): void
    {
        $this->expectException(AuthorizationException::class);

        app(CloseDeliverable::class)($this->deliverable(), CloseReason::CANCELLED_INTERNAL, null, $this->designer());
    }

    public function test_reopening_returns_to_the_status_it_was_closed_from(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        app(CloseDeliverable::class)($deliverable, CloseReason::CANCELLED_INTERNAL, null, $this->manager());

        app(ReopenDeliverable::class)($deliverable, 'Client is back', $this->manager());

        $deliverable->refresh();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->status);
        $this->assertNull($deliverable->close_reason);
        $this->assertNull($deliverable->closed_at);
        $this->assertSame('TO DO TODAY', $this->legacyRow($deliverable)->core_status);
    }

    public function test_reopening_a_closed_deliverable_with_no_history_goes_to_ready(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::DONE, ['close_reason' => CloseReason::COMPLETED, 'closed_at' => now()]);

        app(ReopenDeliverable::class)($deliverable, null, $this->manager());

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
    }

    public function test_only_closed_work_can_be_reopened_and_only_by_managers(): void
    {
        try {
            app(ReopenDeliverable::class)($this->deliverable(DeliverableStatus::READY), null, $this->manager());
            $this->fail('Only closed work can be reopened.');
        } catch (WorkflowException) {
        }

        $this->expectException(AuthorizationException::class);
        app(ReopenDeliverable::class)($this->deliverable(DeliverableStatus::DONE), null, $this->designer());
    }
}
