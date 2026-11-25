<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Queries\OverviewQuery;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OverviewQueryTest extends QueryTestCase
{
    public function test_one_row_per_work_order_with_deliverables_and_excel_columns(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana']);
        $workOrder = WorkOrder::factory()->create([
            'number' => 'WO 55', 'estimate_invoice_number' => 'EST-9', 'installation_types' => ['KUDOS'], 'promised_date' => '2026-12-05',
        ]);
        $a = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $workOrder, ['assignee_id' => $ana->id, 'flag_codes' => [DeliverableFlag::URGENT->value]]);
        $b = $this->makeDeliverable(DeliverableStatus::READY, $workOrder);
        DB::table('orders')->where('id', $a->id)->update(['production_note' => 'Rush', 'delivery_note' => 'Dock B', 'email_date' => '2026-11-10', 'overview_checked' => 1]);
        DB::table('orders')->where('id', $b->id)->update(['email_date' => '2026-11-12', 'production_processed_at' => '2026-11-15']);

        $page = app(OverviewQuery::class)->run();
        $row = $page->items()[0];

        $this->assertSame(1, $page->total());
        $this->assertSame('WO 55', $row->workOrder->number);
        $this->assertCount(2, $row->deliverables);
        $this->assertSame('2026-11-12', $row->emailDate);
        $this->assertSame('2026-11-15', $row->processedAt);
        $this->assertSame('Rush', $row->productionNote);
        $this->assertSame('Dock B', $row->deliveryNote);
        $this->assertSame(['Ana'], $row->designers);
        $this->assertSame([DeliverableFlag::URGENT], $row->flags);
        $this->assertSame(['in_design' => 1, 'ready' => 1], $row->statusCounts);
        $this->assertFalse($row->checked);
    }

    public function test_tabs_and_filters(): void
    {
        $client = $this->makeClient('Hooli');
        $ana = User::factory()->designer()->create();
        $urgent = WorkOrder::factory()->create(['number' => 'WO 1', 'client_id' => $client->id, 'installation_types' => ['CLIENTE']]);
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $urgent, ['flag_codes' => [DeliverableFlag::URGENT->value], 'assignee_id' => $ana->id]);
        $production = WorkOrder::factory()->create(['number' => 'WO 2', 'promised_date' => null]);
        $this->makeDeliverable(DeliverableStatus::IN_PRODUCTION, $production);
        $done = WorkOrder::factory()->create(['number' => 'WO 3']);
        $this->makeDeliverable(DeliverableStatus::DONE, $done);
        $backlog = WorkOrder::factory()->inBacklog()->create(['number' => 'WO 4']);
        $this->makeDeliverable(DeliverableStatus::INTAKE, $backlog);

        $numbers = fn (OverviewFilters $filters) => collect(app(OverviewQuery::class)->run($filters)->items())->map(fn ($row) => $row->workOrder->number)->sort()->values()->all();

        $this->assertSame(['WO 1', 'WO 2', 'WO 3'], $numbers(new OverviewFilters));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(tab: 'active')));
        $this->assertSame(['WO 2'], $numbers(new OverviewFilters(tab: 'production')));
        $this->assertSame(['WO 3'], $numbers(new OverviewFilters(tab: 'done')));
        $this->assertSame(['WO 4'], $numbers(new OverviewFilters(tab: 'backlog')));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(tab: 'urgent')));
        $this->assertSame(['WO 1', 'WO 3'], $numbers(new OverviewFilters(tab: 'with_due_date')));
        $this->assertSame(['WO 2'], $numbers(new OverviewFilters(workOrderNumber: '2')));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(clientId: $client->id)));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(assigneeId: $ana->id)));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(installation: 'CLIENTE')));
        $this->assertSame(['WO 2', 'WO 3'], $numbers(new OverviewFilters(installation: 'NONE')));
        $this->assertSame(['WO 1'], $numbers(new OverviewFilters(search: 'Hooli')));
    }

    public function test_paginates_by_work_order(): void
    {
        foreach (range(1, 7) as $unused) {
            $workOrder = WorkOrder::factory()->create();
            $this->makeDeliverable(DeliverableStatus::READY, $workOrder);
            $this->makeDeliverable(DeliverableStatus::READY, $workOrder);
        }

        $page = app(OverviewQuery::class)->run(perPage: 3);

        $this->assertSame(7, $page->total());
        $this->assertCount(3, $page->items());
        $this->assertCount(2, $page->items()[0]->deliverables);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $times): void {
            foreach (range(1, $times) as $unused) {
                $workOrder = WorkOrder::factory()->create();
                $this->makeDeliverable(DeliverableStatus::READY, $workOrder, ['assignee_id' => User::factory()->designer()->create()->id]);
                $this->makeDeliverable(DeliverableStatus::DONE, $workOrder);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(8), fn () => app(OverviewQuery::class)->run(perPage: 50));
    }
}
