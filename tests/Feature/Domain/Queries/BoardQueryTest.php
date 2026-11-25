<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Data\BoardFilters;
use App\Application\Delivery\Queries\BoardQuery;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;

class BoardQueryTest extends QueryTestCase
{
    private function column($result, DeliverableStatus $status)
    {
        return collect($result->columns)->firstWhere('status', $status);
    }

    public function test_shows_columns_of_the_area_only(): void
    {
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN);
        $this->makeDeliverable(DeliverableStatus::IN_PRODUCTION);

        $design = app(BoardQuery::class)->run(User::factory()->create(), Area::DESIGN);
        $production = app(BoardQuery::class)->run(User::factory()->create(), Area::PRODUCTION);

        $this->assertCount(7, $design->columns);
        $this->assertSame(1, $this->column($design, DeliverableStatus::IN_DESIGN)->total);
        $this->assertSame(1, $this->column($production, DeliverableStatus::IN_PRODUCTION)->total);
        $this->assertNull($this->column($design, DeliverableStatus::IN_PRODUCTION));
    }

    public function test_caps_cards_per_column_but_keeps_real_total(): void
    {
        foreach (range(1, 7) as $unused) {
            $this->makeDeliverable(DeliverableStatus::READY);
        }

        $column = $this->column(app(BoardQuery::class)->run(User::factory()->create(), Area::DESIGN, columnCap: 3), DeliverableStatus::READY);

        $this->assertSame(7, $column->total);
        $this->assertCount(3, $column->cards);
        $this->assertSame(4, $column->hiddenCount());
    }

    public function test_filters_by_assignee_client_flags_and_search(): void
    {
        $mine = User::factory()->designer()->create();
        $client = $this->makeClient('Globex');
        $workOrder = WorkOrder::factory()->create(['client_id' => $client->id, 'number' => 'WO 7777']);
        $match = $this->makeDeliverable(DeliverableStatus::IN_DESIGN, $workOrder, [
            'assignee_id' => $mine->id, 'flag_codes' => [DeliverableFlag::URGENT->value], 'task_name' => 'Banner',
        ]);
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['task_name' => 'Other']);
        $viewer = User::factory()->create();

        $ids = fn (BoardFilters $filters) => $this->column(app(BoardQuery::class)->run($viewer, Area::DESIGN, $filters), DeliverableStatus::IN_DESIGN)->cards->modelKeys();

        $this->assertSame([$match->id], $ids(new BoardFilters(assigneeId: $mine->id)));
        $this->assertSame([$match->id], $ids(new BoardFilters(clientId: $client->id)));
        $this->assertSame([$match->id], $ids(new BoardFilters(flags: [DeliverableFlag::URGENT])));
        $this->assertSame([$match->id], $ids(new BoardFilters(search: '7777')));
        $this->assertSame([$match->id], $ids(new BoardFilters(search: 'Banner')));
        $this->assertCount(2, $ids(new BoardFilters));
    }

    public function test_blocked_cards_are_hidden_from_designers_but_visible_to_others(): void
    {
        $blocked = $this->makeDeliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $free = $this->makeDeliverable(DeliverableStatus::READY);

        $asDesigner = $this->column(app(BoardQuery::class)->run(User::factory()->designer()->create(), Area::DESIGN), DeliverableStatus::READY);
        $asCoordinator = $this->column(app(BoardQuery::class)->run(User::factory()->coordinator()->create(), Area::DESIGN), DeliverableStatus::READY);

        $this->assertSame([$free->id], $asDesigner->cards->modelKeys());
        $this->assertSame(1, $asDesigner->total);
        $this->assertSame(2, $asCoordinator->total);
    }

    public function test_groups_cards_into_lanes_by_assignee_with_unassigned_last(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana']);
        $this->makeDeliverable(DeliverableStatus::IN_DESIGN, null, ['assignee_id' => $ana->id]);
        $this->makeDeliverable(DeliverableStatus::READY);

        $lanes = app(BoardQuery::class)->run(User::factory()->create(), Area::DESIGN)->lanes();

        $this->assertCount(2, $lanes);
        $this->assertSame('Ana', $lanes[0]['assignee']->name);
        $this->assertNull($lanes[1]['assignee']);
        $this->assertCount(1, $lanes[0]['cells']['in_design']);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $viewer = User::factory()->coordinator()->create();
        $seed = function (int $times): void {
            foreach (range(1, $times) as $unused) {
                foreach ([DeliverableStatus::INTAKE, DeliverableStatus::IN_DESIGN, DeliverableStatus::CLIENT_REVIEW] as $status) {
                    $deliverable = $this->makeDeliverable($status, null, ['assignee_id' => User::factory()->designer()->create()->id]);
                    Block::factory()->create(['deliverable_id' => $deliverable->id]);
                }
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(8), fn () => app(BoardQuery::class)->run($viewer, Area::DESIGN)->lanes());
    }
}
