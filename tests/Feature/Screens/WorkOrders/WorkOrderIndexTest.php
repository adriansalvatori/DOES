<?php

namespace Tests\Feature\Screens\WorkOrders;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\WorkOrders\WorkOrderIndex;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function workOrder(string $client, DeliverableStatus $status = DeliverableStatus::IN_DESIGN, array $attributes = []): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create(['client_id' => Client::firstOrCreate(['name' => $client])->id] + $attributes);
        Deliverable::factory()->for($workOrder, 'workOrder')->inStatus($status)->create();

        return $workOrder;
    }

    public function test_every_role_can_open_the_list(): void
    {
        $this->workOrder('Kuyen');

        foreach (['admin', 'coordinator', 'designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get(route('v2.work-orders.index'))->assertOk()->assertSee('Kuyen');
        }
    }

    public function test_tabs_split_work_orders_and_show_counts(): void
    {
        $this->workOrder('Design Studio', DeliverableStatus::IN_DESIGN);
        $this->workOrder('Production Studio', DeliverableStatus::IN_PRODUCTION);
        $this->workOrder('Fulfillment Studio', DeliverableStatus::FULFILLMENT);
        $this->workOrder('Done Studio', DeliverableStatus::DONE);

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderIndex::class);
        $this->assertSame(['active' => 1, 'production' => 1, 'fulfillment' => 1, 'done' => 1], $component->instance()->counts());

        $component->call('setTab', 'production')->assertSeeHtml('>Production Studio</a>')->assertDontSeeHtml('>Design Studio</a>');
        $component->call('setTab', 'done')->assertSeeHtml('>Done Studio</a>')->assertDontSeeHtml('>Production Studio</a>');
    }

    public function test_search_and_client_filter_narrow_the_list(): void
    {
        $kuyen = $this->workOrder('KUYEN AREPAS');
        $this->workOrder('Talpa');

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderIndex::class)
            ->set('search', 'Talpa')->assertSeeHtml('>Talpa</a>')->assertDontSeeHtml('>Kuyen Arepas</a>')
            ->set('search', '')->set('clientId', $kuyen->client_id)->assertSeeHtml('>Kuyen Arepas</a>')->assertDontSeeHtml('>Talpa</a>');
    }

    public function test_row_shows_tag_title_case_client_location_counts_and_health(): void
    {
        $client = Client::create(['name' => 'KUYEN AREPAS']);
        $location = $client->locations()->create(['name' => 'MARIETTA', 'address' => '1 Main St']);
        $workOrder = WorkOrder::factory()->create(['number' => 'WO 15741', 'client_id' => $client->id, 'client_location_id' => $location->id, 'promised_date' => '2026-11-20']);
        Phase::factory()->count(2)->create(['work_order_id' => $workOrder->id]);
        $approved = Deliverable::factory()->for($workOrder, 'workOrder')->inStatus(DeliverableStatus::PRODUCTION_FILES)->create();
        Deliverable::factory()->for($workOrder, 'workOrder')->create();
        Approval::factory()->fromClient()->create(['deliverable_id' => $approved->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderIndex::class)
            ->assertSee('WO 15741')->assertSee('Kuyen Arepas')->assertSee('Marietta')
            ->assertSee('1 / 2')->assertSee('0 / 2')->assertSee('Overdue 5 d')
            ->assertSeeHtml('bg-wo');
    }

    public function test_rows_link_to_the_work_order_page_and_new_button_opens_the_modal(): void
    {
        $workOrder = $this->workOrder('Kuyen');

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderIndex::class)
            ->assertSeeHtml(route('v2.work-orders.show', $workOrder))
            ->assertSeeHtml('open-create-work-order');
    }

    public function test_paginates_by_25(): void
    {
        foreach (range(1, 27) as $i) {
            $this->workOrder('Client '.$i);
        }

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderIndex::class);
        $this->assertSame(25, $component->instance()->rows()->count());
        $this->assertSame(27, $component->instance()->rows()->total());
    }

    public function test_both_locales_render_translated(): void
    {
        $this->workOrder('Kuyen');
        $admin = User::factory()->admin()->create();

        app()->setLocale('en');
        Livewire::actingAs($admin)->test(WorkOrderIndex::class)->assertSee('Work orders')->assertSee('New work order');

        app()->setLocale('es');
        $html = Livewire::actingAs($admin)->test(WorkOrderIndex::class)->html();
        $this->assertStringContainsString('Activo', $html);
        $this->assertStringContainsString('Hecho', $html);
    }

    public function test_query_count_does_not_grow_with_more_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($admin)->test(WorkOrderIndex::class);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->workOrder('One');
        $count();
        $small = $count();
        foreach (range(1, 10) as $i) {
            $this->workOrder('More '.$i);
        }
        $this->assertSame($small, $count());
    }
}
