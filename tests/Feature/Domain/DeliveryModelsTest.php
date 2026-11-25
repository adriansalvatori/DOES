<?php

namespace Tests\Feature\Domain;

use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\DeliverableEvent;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_factory_creates_a_row(): void
    {
        $this->assertModelExists(WorkOrder::factory()->create());
        $this->assertModelExists(Phase::factory()->create());
        $this->assertModelExists(Deliverable::factory()->create());
        $this->assertModelExists(Task::factory()->create());
        $this->assertModelExists(Approval::factory()->create());
        $this->assertModelExists(Block::factory()->create());
        $this->assertModelExists(Hold::factory()->create());
        $this->assertModelExists(Receipt::factory()->create());
        $this->assertModelExists(Alert::factory()->create());
        $this->assertModelExists(DeliverableEvent::factory()->create());
    }

    public function test_relations_connect_a_work_order_to_its_deliverable_records(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id]);
        $deliverable = Deliverable::factory()->create(['work_order_id' => $workOrder->id, 'phase_id' => $phase->id]);
        $task = Task::factory()->create(['order_id' => $deliverable->id]);
        $approval = Approval::factory()->create(['deliverable_id' => $deliverable->id]);
        $receipt = Receipt::factory()->create(['deliverable_id' => $deliverable->id]);
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id]);
        $event = DeliverableEvent::factory()->create(['order_id' => $deliverable->id]);

        $this->assertTrue($workOrder->deliverables->first()->is($deliverable));
        $this->assertTrue($workOrder->phases->first()->is($phase));
        $this->assertTrue($deliverable->workOrder->is($workOrder));
        $this->assertTrue($deliverable->phase->is($phase));
        $this->assertTrue($phase->deliverables->first()->is($deliverable));
        $this->assertTrue($deliverable->tasks->first()->is($task));
        $this->assertTrue($task->deliverable->is($deliverable));
        $this->assertTrue($deliverable->approvals->first()->is($approval));
        $this->assertTrue($deliverable->receipts->first()->is($receipt));
        $this->assertTrue($deliverable->alerts->first()->is($alert));
        $this->assertTrue($deliverable->events->first()->is($event));
    }

    public function test_assignee_and_reviewer_are_users(): void
    {
        $designer = User::factory()->designer()->create();
        $reviewer = User::factory()->reviewer()->create();
        $deliverable = Deliverable::factory()->create(['assignee_id' => $designer->id, 'reviewer_id' => $reviewer->id]);

        $this->assertTrue($deliverable->assignee->is($designer));
        $this->assertTrue($deliverable->reviewer->is($reviewer));
        $this->assertTrue($designer->assignedDeliverables->first()->is($deliverable));
        $this->assertTrue($reviewer->is_reviewer);
    }

    public function test_open_block_and_open_hold_ignore_resolved_ones(): void
    {
        $deliverable = Deliverable::factory()->create();
        Block::factory()->resolved()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);
        $open = Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_MEASUREMENTS]);
        Hold::factory()->ended()->create(['deliverable_id' => $deliverable->id]);
        $openHold = Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::NO_RESPONSE]);

        $this->assertTrue($deliverable->openBlock->is($open));
        $this->assertSame(BlockReason::MISSING_MEASUREMENTS, $deliverable->openBlock->reason);
        $this->assertCount(2, $deliverable->blocks);
        $this->assertTrue($deliverable->openHold->is($openHold));
        $this->assertCount(2, $deliverable->holds);
        $this->assertTrue($deliverable->isBlocked());
        $this->assertTrue($deliverable->isOnHold());
    }

    public function test_casts_use_the_new_enums(): void
    {
        $deliverable = Deliverable::factory()->create([
            'status' => DeliverableStatus::DONE,
            'close_reason' => CloseReason::CANCELLED_BY_CLIENT,
            'flag_codes' => [DeliverableFlag::URGENT, DeliverableFlag::REPRINT],
            'planned_for' => '2026-11-26',
        ])->fresh();
        $task = Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::CLIENT_REVIEW)->create()->fresh();

        $this->assertSame(DeliverableStatus::DONE, $deliverable->status);
        $this->assertSame(CloseReason::CANCELLED_BY_CLIENT, $deliverable->close_reason);
        $this->assertSame([DeliverableFlag::URGENT, DeliverableFlag::REPRINT], $deliverable->flag_codes->all());
        $this->assertSame('2026-11-26', $deliverable->planned_for->toDateString());
        $this->assertSame(TaskKind::GATE_SEND_PROOF, $task->kind);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $task->advances_to);
        $this->assertTrue($task->required);
        $this->assertTrue($task->isGate());
    }

    public function test_status_and_area_scopes(): void
    {
        $intake = Deliverable::factory()->create();
        $inDesign = Deliverable::factory()->inStatus(DeliverableStatus::IN_DESIGN)->create();
        $queue = Deliverable::factory()->inStatus(DeliverableStatus::PRODUCTION_QUEUE)->create();
        $done = Deliverable::factory()->done()->create();

        $this->assertSame([$inDesign->id], Deliverable::inStatus(DeliverableStatus::IN_DESIGN)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$intake->id, $inDesign->id],
            Deliverable::inStatus([DeliverableStatus::INTAKE, DeliverableStatus::IN_DESIGN])->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing([$intake->id, $inDesign->id], Deliverable::inArea(Area::DESIGN)->pluck('id')->all());
        $this->assertSame([$queue->id], Deliverable::inArea(Area::PRODUCTION)->pluck('id')->all());
        $this->assertSame([$done->id], Deliverable::inArea(Area::FULFILLMENT)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$intake->id, $inDesign->id, $queue->id], Deliverable::active()->pluck('id')->all());
    }

    public function test_blocked_not_blocked_and_on_hold_scopes_ignore_resolved_records(): void
    {
        $blocked = Deliverable::factory()->create();
        $wasBlocked = Deliverable::factory()->create();
        $clear = Deliverable::factory()->create();
        $held = Deliverable::factory()->create();
        $wasHeld = Deliverable::factory()->create();
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        Block::factory()->resolved()->create(['deliverable_id' => $wasBlocked->id]);
        Hold::factory()->create(['deliverable_id' => $held->id]);
        Hold::factory()->ended()->create(['deliverable_id' => $wasHeld->id]);

        $this->assertSame([$blocked->id], Deliverable::blocked()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$wasBlocked->id, $clear->id, $held->id, $wasHeld->id],
            Deliverable::notBlocked()->pluck('id')->all(),
        );
        $this->assertSame([$held->id], Deliverable::onHold()->pluck('id')->all());
    }

    public function test_planned_for_and_assigned_to_scopes(): void
    {
        $designer = User::factory()->designer()->create();
        $other = User::factory()->designer()->create();
        $mine = Deliverable::factory()->assignedTo($designer)->plannedFor('2026-11-26')->create();
        Deliverable::factory()->assignedTo($designer)->plannedFor('2026-11-27')->create();
        Deliverable::factory()->assignedTo($other)->plannedFor('2026-11-26')->create();

        $this->assertSame([$mine->id], Deliverable::assignedTo($designer)->plannedFor('2026-11-26')->pluck('id')->all());
        $this->assertSame([$mine->id], Deliverable::assignedTo($designer->id)->plannedFor(now()->setDate(2026, 11, 26))->pluck('id')->all());
    }

    public function test_work_order_number_is_unique_but_may_be_null_many_times(): void
    {
        WorkOrder::factory()->withoutNumber()->count(2)->create();
        WorkOrder::factory()->create(['number' => 'WO 1']);

        $this->expectException(UniqueConstraintViolationException::class);

        WorkOrder::factory()->create(['number' => 'WO 1']);
    }

    public function test_customer_service_is_a_user_role_with_a_factory_state(): void
    {
        $user = User::factory()->customerService()->create();

        $this->assertSame(UserRole::CUSTOMER_SERVICE, $user->fresh()->role);
        $this->assertTrue($user->isCustomerService());
        $this->assertFalse($user->isAdmin());
    }
}
