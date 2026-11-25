<?php

namespace Tests\Feature\Orders;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Orders\HeaderSearch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HeaderSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_component_renders_successfully(): void
    {
        Livewire::test(HeaderSearch::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.orders.header-search')
            ->assertSee(__('New work order'));
    }

    public function test_returns_empty_results_when_query_is_empty(): void
    {
        $this->workOrder('WO 100', 'Acme Inc', 'Logo Design');

        Livewire::test(HeaderSearch::class)
            ->set('search', '')
            ->assertViewHas('workOrders', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('deliverables', fn ($rows) => $rows->isEmpty());
    }

    public function test_searches_work_orders_by_number_and_client(): void
    {
        $match = $this->workOrder('WO 9988', 'Cyberdyne Systems', 'T-800 Blueprint Design');
        $this->workOrder('WO 1122', 'Wayne Enterprises', 'Batmobile Graphics');
        $backlog = WorkOrder::factory()->inBacklog()->create(['number' => 'WO 9989', 'client_id' => $match->client_id]);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Cyberdyne')
            ->assertViewHas('workOrders', fn ($rows) => $rows->pluck('id')->all() === [$match->id]);

        Livewire::test(HeaderSearch::class)
            ->set('search', '9988')
            ->assertViewHas('workOrders', fn ($rows) => $rows->pluck('id')->all() === [$match->id] && ! $rows->contains('id', $backlog->id))
            ->assertSee('WO 9988')
            ->assertSeeHtml(route('work-orders.show', $match));
    }

    public function test_searches_deliverables_by_name_with_multi_word_tokens(): void
    {
        $workOrder = $this->workOrder('WO 7711', 'Talpa Retail', 'talpa 8 mableton signage');
        $this->workOrder('WO 7712', 'Other Retail', 'Unrelated banner');

        Livewire::test(HeaderSearch::class)
            ->set('search', 'talpa mableton')
            ->assertViewHas('deliverables', fn ($rows) => $rows->count() === 1 && $rows->first()->work_order_id === $workOrder->id);
    }

    public function test_selecting_a_deliverable_opens_the_panel_and_resets_the_search(): void
    {
        $workOrder = $this->workOrder('WO 55', 'Oscorp Industries', 'Serum Labeling');
        $deliverable = $workOrder->deliverables()->first();

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Serum')
            ->call('selectDeliverable', $deliverable->id)
            ->assertDispatched('open-deliverable', deliverableId: $deliverable->id)
            ->assertSet('search', '');
    }

    public function test_searches_clients_and_dispatches_open_client_flyout_event(): void
    {
        $client = Client::create(['name' => 'PORKYS REAL MEXICAN FOOD', 'website' => 'https://porkysmexican.com']);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Porkys Client')
            ->assertViewHas('clientResults', fn ($clients) => $clients->count() === 1 && $clients->first()->id === $client->id)
            ->call('selectClient', $client->id)
            ->assertDispatched('open-client-flyout', clientId: $client->id)
            ->assertSet('search', '');
    }

    public function test_new_work_order_action_dispatches_the_create_event_and_trello_sync_is_gone(): void
    {
        $component = Livewire::test(HeaderSearch::class);

        $component->assertSeeHtml("\$dispatch('open-create-work-order')");
        $this->assertStringNotContainsString('open-create-order', $component->html());
        $this->assertFalse(method_exists(HeaderSearch::class, 'runTrelloSyncOnlyNew'));
    }

    public function test_a_search_costs_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 8) as $index) {
            $this->workOrder('WO 30'.$index, 'Same Client '.$index, 'Same banner '.$index);
        }

        $component = Livewire::test(HeaderSearch::class);
        DB::enableQueryLog();
        $component->set('search', 'Same');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queries);
    }

    private function workOrder(string $number, string $clientName, string $deliverableName): WorkOrder
    {
        $client = Client::create(['name' => $clientName]);
        $workOrder = WorkOrder::factory()->create(['number' => $number, 'client_id' => $client->id]);
        Deliverable::factory()->inStatus(DeliverableStatus::IN_DESIGN)->create(['work_order_id' => $workOrder->id, 'task_name' => $deliverableName]);

        return $workOrder;
    }
}
