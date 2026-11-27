<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Data\PromiseHealth;
use App\Application\Delivery\Data\WorkOrderTab;
use App\Application\Delivery\Queries\WorkOrderListQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Support\Carbon;

class WorkOrderListQueryTest extends QueryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00:00');
    }

    /**
     * @param  list<DeliverableStatus>  $statuses
     */
    private function workOrderWith(array $statuses, string $number, ?string $promised = '2026-12-20'): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create(['number' => $number, 'promised_date' => $promised]);
        foreach ($statuses as $status) {
            $this->makeDeliverable($status, $workOrder);
        }

        return $workOrder;
    }

    public function test_tabs_classify_by_earliest_unfinished_deliverable(): void
    {
        $active = $this->workOrderWith([DeliverableStatus::IN_DESIGN, DeliverableStatus::IN_PRODUCTION], 'WO 1');
        $empty = $this->workOrderWith([], 'WO 2');
        $production = $this->workOrderWith([DeliverableStatus::IN_PRODUCTION, DeliverableStatus::FULFILLMENT, DeliverableStatus::DONE], 'WO 3');
        $fulfillment = $this->workOrderWith([DeliverableStatus::FULFILLMENT, DeliverableStatus::DONE], 'WO 4');
        $done = $this->workOrderWith([DeliverableStatus::DONE], 'WO 5');
        Deliverable::factory()->inBacklog()->create();

        $ids = fn (WorkOrderTab $tab) => collect(app(WorkOrderListQuery::class)->run($tab)->items())->map(fn ($row) => $row->workOrder->id)->all();

        $this->assertEqualsCanonicalizing([$active->id, $empty->id], $ids(WorkOrderTab::ACTIVE));
        $this->assertSame([$production->id], $ids(WorkOrderTab::PRODUCTION));
        $this->assertSame([$fulfillment->id], $ids(WorkOrderTab::FULFILLMENT));
        $this->assertSame([$done->id], $ids(WorkOrderTab::DONE));
        $this->assertSame(['active' => 2, 'production' => 1, 'fulfillment' => 1, 'done' => 1], app(WorkOrderListQuery::class)->tabCounts());
    }

    public function test_rollups_and_promise_health(): void
    {
        $workOrder = $this->workOrderWith([DeliverableStatus::CLIENT_REVIEW, DeliverableStatus::CLIENT_REVIEW, DeliverableStatus::DONE], 'WO 10', '2026-11-20');
        [$a, $b, $c] = $workOrder->deliverables()->orderBy('id')->get()->all();
        Approval::factory()->fromClient()->create(['deliverable_id' => $a->id]);
        Approval::factory()->fromClient()->reset()->create(['deliverable_id' => $b->id]);
        Approval::factory()->create(['deliverable_id' => $b->id]);
        Receipt::factory()->create(['deliverable_id' => $c->id]);

        $row = app(WorkOrderListQuery::class)->run(WorkOrderTab::ACTIVE)->items()[0];

        $this->assertSame(3, $row->deliverablesCount);
        $this->assertSame(2, $row->openCount);
        $this->assertSame(1, $row->approvedCount);
        $this->assertSame(1, $row->receivedCount);
        $this->assertSame(PromiseHealth::OVERDUE, $row->health);

        $this->assertSame(PromiseHealth::DUE_SOON, PromiseHealth::for(Carbon::parse('2026-11-27'), true, now()));
        $this->assertSame(PromiseHealth::ON_TRACK, PromiseHealth::for(Carbon::parse('2026-12-10'), true, now()));
        $this->assertSame(PromiseHealth::NONE, PromiseHealth::for(Carbon::parse('2026-11-01'), false, now()));
    }

    public function test_search_client_filter_and_pagination(): void
    {
        $client = $this->makeClient('Initech');
        foreach (range(1, 5) as $index) {
            $workOrder = WorkOrder::factory()->create(['number' => "WO 20{$index}", 'client_id' => $client->id]);
            $this->makeDeliverable(DeliverableStatus::READY, $workOrder);
        }
        $this->workOrderWith([DeliverableStatus::READY], 'WO 999');

        $page = app(WorkOrderListQuery::class)->run(WorkOrderTab::ACTIVE, clientId: $client->id, perPage: 2);
        $this->assertSame(5, $page->total());
        $this->assertCount(2, $page->items());

        $this->assertSame(1, app(WorkOrderListQuery::class)->run(WorkOrderTab::ACTIVE, search: '999')->total());
        $this->assertSame(5, app(WorkOrderListQuery::class)->run(WorkOrderTab::ACTIVE, search: 'Initech')->total());
        $this->assertSame(5, app(WorkOrderListQuery::class)->tabCounts(clientId: $client->id)['active']);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $times): void {
            foreach (range(1, $times) as $unused) {
                $workOrder = $this->workOrderWith([DeliverableStatus::CLIENT_REVIEW, DeliverableStatus::READY], 'WO '.fake()->unique()->numberBetween(100000, 999999));
                Approval::factory()->fromClient()->create(['deliverable_id' => $workOrder->deliverables()->first()->id]);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(8), function (): void {
            app(WorkOrderListQuery::class)->run(WorkOrderTab::ACTIVE, perPage: 50);
            app(WorkOrderListQuery::class)->tabCounts();
        });
    }
}
