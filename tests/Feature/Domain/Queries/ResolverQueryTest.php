<?php

namespace Tests\Feature\Domain\Queries;

use App\Application\Delivery\Queries\ResolverQuery;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Carbon;

class ResolverQueryTest extends QueryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00:00');
    }

    public function test_groups_open_blocks_and_holds_by_work_order_with_age_reason_owner_and_phone(): void
    {
        $owner = User::factory()->customerService()->create();
        $contact = Contact::factory()->create(['phone' => '305-555-0100']);
        $workOrder = WorkOrder::factory()->create(['contact_id' => $contact->id]);
        $a = $this->makeDeliverable(DeliverableStatus::INTAKE, $workOrder);
        $b = $this->makeDeliverable(DeliverableStatus::CLIENT_REVIEW, $workOrder);
        Block::factory()->create(['deliverable_id' => $a->id, 'owner_id' => $owner->id, 'reason' => BlockReason::MISSING_LOGO, 'raised_at' => '2026-11-20 10:00:00']);
        Hold::factory()->create(['deliverable_id' => $b->id, 'started_at' => '2026-11-24 10:00:00']);
        Block::factory()->resolved()->create(['deliverable_id' => $b->id]);
        $other = $this->makeDeliverable(DeliverableStatus::INTAKE);
        Block::factory()->create(['deliverable_id' => $other->id, 'raised_at' => '2026-11-25 08:00:00']);

        $groups = app(ResolverQuery::class)->run();

        $this->assertCount(2, $groups);
        $this->assertSame($workOrder->id, $groups[0]->workOrder->id);
        $this->assertSame(5, $groups[0]->oldestAgeInDays);
        $this->assertCount(2, $groups[0]->items);
        $first = $groups[0]->items[0];
        $this->assertSame('block', $first->type);
        $this->assertSame(BlockReason::MISSING_LOGO, $first->reason);
        $this->assertSame($owner->id, $first->owner->id);
        $this->assertNotEmpty($first->clientPhone);
        $this->assertSame('hold', $groups[0]->items[1]->type);
        $this->assertSame(0, $groups[1]->oldestAgeInDays);
    }

    public function test_owner_filter_keeps_own_and_unowned_blocks(): void
    {
        $cs = User::factory()->customerService()->create();
        $someoneElse = User::factory()->create();
        $own = $this->makeDeliverable(DeliverableStatus::INTAKE);
        $unowned = $this->makeDeliverable(DeliverableStatus::INTAKE);
        $foreign = $this->makeDeliverable(DeliverableStatus::INTAKE);
        Block::factory()->create(['deliverable_id' => $own->id, 'owner_id' => $cs->id]);
        Block::factory()->create(['deliverable_id' => $unowned->id]);
        Block::factory()->create(['deliverable_id' => $foreign->id, 'owner_id' => $someoneElse->id]);

        $ids = collect(app(ResolverQuery::class)->run($cs->id))->flatMap(fn ($group) => collect($group->items)->map(fn ($item) => $item->deliverable->id));

        $this->assertEqualsCanonicalizing([$own->id, $unowned->id], $ids->all());
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $seed = function (int $times): void {
            foreach (range(1, $times) as $unused) {
                $contact = Contact::factory()->create();
                $workOrder = WorkOrder::factory()->create(['contact_id' => $contact->id]);
                $card = $this->makeDeliverable(DeliverableStatus::INTAKE, $workOrder);
                Block::factory()->create(['deliverable_id' => $card->id, 'owner_id' => User::factory()->customerService()->create()->id]);
                Hold::factory()->create(['deliverable_id' => $this->makeDeliverable(DeliverableStatus::READY, $workOrder)->id]);
            }
        };
        $seed(2);

        $this->assertConstantQueries(fn () => $seed(6), fn () => app(ResolverQuery::class)->run());
    }
}
