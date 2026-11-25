<?php

namespace Tests\Feature\Backfill;

use App\Application\Delivery\Actions\AddDeliverable;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Infrastructure\Backfill\LegacyBackfill;
use App\Livewire\WorkOrders\WorkOrderIndex;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderCompanyNameTest extends TestCase
{
    use RefreshDatabase;

    private function unlinkedWorkOrder(string ...$companyNames): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create();
        foreach ($companyNames as $index => $name) {
            Deliverable::factory()->for($workOrder, 'workOrder')->inStatus(DeliverableStatus::IN_DESIGN)->create([
                'company_name' => $name,
                'updated_at' => now()->subDays(10 - $index),
            ]);
        }

        return $workOrder;
    }

    public function test_backfill_uses_the_linked_client_name(): void
    {
        $client = Client::create(['name' => 'KUYEN AREPAS']);
        $workOrder = WorkOrder::factory()->create(['client_id' => $client->id]);

        $this->assertSame(1, app(LegacyBackfill::class)->fillWorkOrderCompanyNames());

        $this->assertSame('KUYEN AREPAS', $workOrder->fresh()->company_name);
    }

    public function test_backfill_takes_the_most_recent_non_empty_deliverable_company_for_unlinked_work_orders(): void
    {
        $workOrder = $this->unlinkedWorkOrder('OLD NAME', 'NEWER NAME', '');

        app(LegacyBackfill::class)->fillWorkOrderCompanyNames();

        $this->assertSame('NEWER NAME', $workOrder->fresh()->company_name);
    }

    public function test_backfill_ignores_the_no_client_placeholder_and_work_orders_without_names(): void
    {
        $placeholder = $this->unlinkedWorkOrder('No client');
        $bare = $this->unlinkedWorkOrder();

        app(LegacyBackfill::class)->fillWorkOrderCompanyNames();

        $this->assertNull($placeholder->fresh()->company_name);
        $this->assertNull($bare->fresh()->company_name);
    }

    public function test_backfill_is_idempotent_and_keeps_a_name_already_set(): void
    {
        $filled = $this->unlinkedWorkOrder('FROM DELIVERABLE');
        $typed = $this->unlinkedWorkOrder('FROM DELIVERABLE');
        $typed->update(['company_name' => 'TYPED BY HAND']);

        $backfill = app(LegacyBackfill::class);
        $this->assertSame(1, $backfill->fillWorkOrderCompanyNames());
        $this->assertSame(0, $backfill->fillWorkOrderCompanyNames());

        $this->assertSame('FROM DELIVERABLE', $filled->fresh()->company_name);
        $this->assertSame('TYPED BY HAND', $typed->fresh()->company_name);
    }

    public function test_display_name_prefers_the_client_then_the_company_name(): void
    {
        $client = Client::create(['name' => 'LINKED CO']);

        $this->assertSame('LINKED CO', WorkOrder::factory()->create(['client_id' => $client->id, 'company_name' => 'STALE'])->displayName());
        $this->assertSame('TYPED CO', WorkOrder::factory()->create(['company_name' => 'TYPED CO'])->displayName());
        $this->assertSame('', WorkOrder::factory()->create()->displayName());
    }

    public function test_add_deliverable_copies_the_display_name_and_never_a_placeholder(): void
    {
        $named = WorkOrder::factory()->create(['company_name' => 'TYPED CO']);
        $bare = WorkOrder::factory()->create();

        $first = app(AddDeliverable::class)($named, 'Panel');
        $second = app(AddDeliverable::class)($bare, 'Panel');

        $this->assertSame('TYPED CO', $first->company_name);
        $this->assertSame('', (string) $second->company_name);
    }

    public function test_list_page_and_search_show_the_company_of_an_unlinked_work_order(): void
    {
        $workOrder = $this->unlinkedWorkOrder();
        $workOrder->update(['company_name' => 'TALPA 14 MARIETTA']);
        Deliverable::factory()->for($workOrder, 'workOrder')->inStatus(DeliverableStatus::IN_DESIGN)->create(['company_name' => 'SOMETHING ELSE']);
        $this->unlinkedWorkOrder();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('work-orders.index'))->assertSee('Talpa 14 Marietta');
        Livewire::actingAs($admin)->test(WorkOrderIndex::class)
            ->set('search', 'marietta')
            ->assertSee('Talpa 14 Marietta')
            ->assertDontSee('No client');
        $this->actingAs($admin)->get(route('work-orders.show', $workOrder))->assertSee('Talpa 14 Marietta');
    }
}
