<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Orders\HeaderSearch;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeaderSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        Livewire::test(HeaderSearch::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.orders.header-search');
    }

    public function test_returns_empty_results_when_query_is_empty(): void
    {
        Order::create([
            'in_workspace' => true,
            'company_name' => 'Acme Inc',
            'task_name' => 'Logo Design',
            'core_status' => CoreStatus::TO_DO_TODAY->value,
        ]);

        Livewire::test(HeaderSearch::class)
            ->set('search', '')
            ->assertViewHas('results', fn ($results) => $results->isEmpty());
    }

    public function test_searches_active_workspace_orders_by_company_wo_and_task_name(): void
    {
        $matchingOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Cyberdyne Systems',
            'task_name' => 'T-800 Blueprint Design',
            'wo_number' => 'WO-9988',
        ]);

        $otherActiveOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Wayne Enterprises',
            'task_name' => 'Batmobile Graphics',
            'wo_number' => 'WO-1122',
        ]);

        $archivedOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::ARCHIVED->value,
            'company_name' => 'Cyberdyne Old Branch',
            'task_name' => 'T-800 Legacy',
            'wo_number' => 'WO-9988-OLD',
        ]);

        $backlogOrder = Order::create([
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE->value,
            'company_name' => 'Cyberdyne Future',
            'task_name' => 'T-1000 Design',
            'wo_number' => 'WO-9989',
        ]);

        // Search by company name
        Livewire::test(HeaderSearch::class)
            ->set('search', 'Cyberdyne')
            ->assertViewHas('results', function ($results) use ($matchingOrder) {
                return $results->count() === 1 && $results->first()->id === $matchingOrder->id;
            });

        // Search by WO number
        Livewire::test(HeaderSearch::class)
            ->set('search', '9988')
            ->assertViewHas('results', function ($results) use ($matchingOrder) {
                return $results->count() === 1 && $results->first()->id === $matchingOrder->id;
            });
    }

    public function test_searches_by_assigned_designer(): void
    {
        $designer = Designer::create([
            'name' => 'Euralíz',
            'active' => true,
            'color_type' => 'magenta',
        ]);

        $order = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Stark Industries',
            'task_name' => 'Iron Suit Branding',
            'designer_id' => $designer->id,
        ]);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Euralíz')
            ->assertViewHas('results', function ($results) use ($order) {
                return $results->count() === 1 && $results->first()->id === $order->id;
            });
    }

    public function test_dispatches_open_order_detail_event_and_resets_search_on_select(): void
    {
        $order = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Oscorp Industries',
            'task_name' => 'Serum Labeling',
        ]);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Oscorp')
            ->call('selectOrder', $order->id)
            ->assertDispatched('open-order-detail', orderId: $order->id)
            ->assertSet('search', '');
    }

    public function test_searches_client_database_and_dispatches_open_client_flyout_event(): void
    {
        $client = Client::create([
            'name' => 'PORKYS REAL MEXICAN FOOD',
            'website' => 'https://porkysmexican.com',
        ]);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'Porkys Client')
            ->assertViewHas('clientResults', function ($clients) use ($client) {
                return $clients->count() === 1 && $clients->first()->id === $client->id;
            })
            ->call('selectClient', $client->id)
            ->assertDispatched('open-client-flyout', clientId: $client->id)
            ->assertSet('search', '');
    }
}
