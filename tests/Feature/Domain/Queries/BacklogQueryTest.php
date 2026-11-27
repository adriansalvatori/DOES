<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Data\BacklogFilters;
use App\Application\Delivery\Queries\BacklogQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Contact;
use App\Models\User;

class BacklogQueryTest extends QueryTestCase
{
    private function backlogOrder(string $number, string $company, array $attributes = [], ?DeliverableStatus $status = DeliverableStatus::INTAKE, array $deliverable = []): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create(['number' => $number, ...$attributes]);
        $this->makeDeliverable($status, $workOrder, ['company_name' => $company, 'in_backlog' => true, ...$deliverable]);

        return $workOrder;
    }

    public function test_only_backlog_work_orders_are_listed(): void
    {
        $this->backlogOrder('WO 1', 'Acme');
        $this->makeDeliverable(DeliverableStatus::INTAKE);

        $result = app(BacklogQuery::class)->run();

        $this->assertSame(1, $result->workOrders->total());
        $this->assertSame(1, $result->totalInBacklog);
        $this->assertCount(1, $result->workOrders->items()[0]->deliverables);
    }

    public function test_filters_and_sorting(): void
    {
        $contact = Contact::factory()->create(['name' => 'Pat']);
        $ana = User::factory()->designer()->create();
        $a = $this->backlogOrder('WO 1', 'Zeta', ['contact_id' => $contact->id, 'promised_date' => '2026-12-09'], DeliverableStatus::READY, ['assignee_id' => $ana->id]);
        $b = $this->backlogOrder('WO 2', 'Alpha', ['promised_date' => '2026-12-01']);
        $c = $this->backlogOrder('WO 3', 'Mid', ['promised_date' => null]);

        $ids = fn (BacklogFilters $filters) => collect(app(BacklogQuery::class)->run($filters)->workOrders->items())->pluck('id')->all();

        $this->assertSame([$a->id], $ids(new BacklogFilters(status: DeliverableStatus::READY)));
        $this->assertSame([$a->id], $ids(new BacklogFilters(assigneeId: $ana->id)));
        $this->assertSame([$b->id], $ids(new BacklogFilters(company: 'Alpha')));
        $this->assertSame([$a->id], $ids(new BacklogFilters(contactId: $contact->id)));
        $this->assertSame([$a->id], $ids(new BacklogFilters(search: 'Pat')));
        $this->assertSame([$c->id], $ids(new BacklogFilters(search: 'WO 3')));
        $this->assertSame([$b->id, $a->id, $c->id], $ids(new BacklogFilters(sortBy: 'due_date_asc')));
        $this->assertSame([$b->id, $c->id, $a->id], $ids(new BacklogFilters(sortBy: 'company_asc')));
        $this->assertSame([$a->id, $b->id, $c->id], $ids(new BacklogFilters(sortBy: 'created_asc')));
    }

    public function test_filter_options_and_pagination(): void
    {
        foreach (range(1, 5) as $index) {
            $this->backlogOrder("WO {$index}", $index % 2 ? 'Odd' : 'Even', ['contact_id' => Contact::factory()->create()->id]);
        }
        $this->makeDeliverable(DeliverableStatus::INTAKE, null, ['company_name' => 'Not backlog']);

        $result = app(BacklogQuery::class)->run(perPage: 2);

        $this->assertSame(5, $result->workOrders->total());
        $this->assertCount(2, $result->workOrders->items());
        $this->assertSame(['Even', 'Odd'], $result->companies->all());
        $this->assertCount(5, $result->contacts);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $times): void {
            foreach (range(1, $times) as $index) {
                $this->backlogOrder('WO '.fake()->unique()->numberBetween(1000, 99999), 'Co '.$index, ['contact_id' => Contact::factory()->create()->id]);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(8), fn () => app(BacklogQuery::class)->run(new BacklogFilters(search: 'Co', sortBy: 'company_asc'), 50));
    }
}
