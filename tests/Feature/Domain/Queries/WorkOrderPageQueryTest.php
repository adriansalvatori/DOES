<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Queries\WorkOrderPageQuery;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;

class WorkOrderPageQueryTest extends QueryTestCase
{
    public function test_work_order_without_phases_is_one_implicit_batch(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $a = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder);
        $b = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder);
        $design = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $workOrder);
        Approval::factory()->create(['deliverable_id' => $a->id, 'source' => ApprovalSource::REVIEWER]);
        Approval::factory()->create(['deliverable_id' => $b->id, 'source' => ApprovalSource::REVIEWER]);

        $page = app(WorkOrderPageQuery::class)->run($workOrder);

        $this->assertCount(1, $page->groups);
        $this->assertNull($page->groups[0]->phase);
        $this->assertTrue($page->groups[0]->readiness->ready);
        $this->assertSame([$design->id], $page->leftBehind->modelKeys());
        $this->assertSame([$design->id, $a->id, $b->id], $page->groups[0]->deliverables->modelKeys());
    }

    public function test_phases_have_their_own_readiness_and_loose_deliverables_form_a_trailing_batch(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $first = Phase::factory()->create(['work_order_id' => $workOrder->id, 'position' => 1]);
        $second = Phase::factory()->create(['work_order_id' => $workOrder->id, 'position' => 2]);
        $a = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $first->id]);
        $blocked = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $first->id]);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $b = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $second->id]);
        $loose = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder);
        Approval::factory()->create(['deliverable_id' => $a->id, 'source' => ApprovalSource::REVIEWER]);
        Approval::factory()->create(['deliverable_id' => $loose->id, 'source' => ApprovalSource::CLIENT]);

        $page = app(WorkOrderPageQuery::class)->run($workOrder->id);

        $this->assertCount(3, $page->groups);
        $this->assertTrue($page->groups[0]->readiness->ready, 'blocked deliverables do not hold back the batch');
        $this->assertSame([$blocked->id], $page->groups[0]->readiness->leftBehind->modelKeys());
        $this->assertFalse($page->groups[1]->readiness->ready);
        $this->assertFalse($page->groups[2]->readiness->ready, 'a client approval is not a reviewer approval');
        $this->assertSame([$blocked->id], $page->leftBehind->modelKeys());
        $this->assertNotNull($b);
    }

    public function test_alerts_and_rollups(): void
    {
        $workOrder = WorkOrder::factory()->create(['promised_date' => now()->subDay()->toDateString()]);
        $a = $this->makeDeliverable(DeliverableStatus::CLIENT_REVIEW, $workOrder);
        $b = $this->makeDeliverable(DeliverableStatus::FULFILLMENT, $workOrder);
        Approval::factory()->fromClient()->create(['deliverable_id' => $a->id]);
        Receipt::factory()->create(['deliverable_id' => $b->id]);
        $deliverableAlert = Alert::factory()->create(['deliverable_id' => $a->id]);
        $workOrderAlert = Alert::factory()->create(['deliverable_id' => null, 'work_order_id' => $workOrder->id]);
        Alert::factory()->resolved()->create(['deliverable_id' => $a->id]);
        Alert::factory()->create();
        Task::factory()->create(['order_id' => $a->id, 'status' => 'done']);
        Task::factory()->create(['order_id' => $a->id]);

        $page = app(WorkOrderPageQuery::class)->run($workOrder);

        $this->assertEqualsCanonicalizing([$deliverableAlert->id, $workOrderAlert->id], $page->alerts->modelKeys());
        $this->assertSame([2, 1, 1], [$page->deliverablesCount, $page->approvedCount, $page->receivedCount]);
        $this->assertSame('overdue', $page->health->value);
        $card = $page->groups[0]->deliverables->firstWhere('id', $a->id);
        $this->assertSame([2, 1], [$card->tasks_count, $card->tasks_done_count]);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id]);
        $seed = function (int $times) use ($workOrder, $phase): void {
            foreach (range(1, $times) as $unused) {
                $card = $this->makeDeliverable(DeliverableStatus::INTERNAL_REVIEW, $workOrder, ['phase_id' => $phase->id]);
                Approval::factory()->create(['deliverable_id' => $card->id]);
                Block::factory()->create(['deliverable_id' => $card->id]);
                Alert::factory()->create(['deliverable_id' => $card->id]);
                Task::factory()->create(['order_id' => $card->id]);
                Receipt::factory()->create(['deliverable_id' => $card->id]);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(8), fn () => app(WorkOrderPageQuery::class)->run($workOrder));
    }
}
