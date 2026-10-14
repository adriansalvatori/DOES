<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Orders\HeaderSearch;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use App\Services\TrelloSyncService;
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

    public function test_searches_with_non_exact_multi_word_tokens(): void
    {
        $order = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Talpa Retail',
            'task_name' => 'talpa 8 mableton signage',
            'wo_number' => 'WO-7711',
        ]);

        Livewire::test(HeaderSearch::class)
            ->set('search', 'talpa mableton')
            ->assertViewHas('results', function ($results) use ($order) {
                return $results->count() === 1 && $results->first()->id === $order->id;
            });
    }

    public function test_can_run_trello_sync_only_new_from_header_search(): void
    {
        $existingOrder = Order::create([
            'company_name' => 'EXISTING HEADER CLIENT',
            'task_name' => 'Banner Old',
            'trello_card_id' => 'card_header_old_1',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) {
            $mock->shouldReceive('extractBoardId')->andReturn('test_board_id');
            $mock->shouldReceive('getBoardLists')->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 'list_entrante', 'name' => 'ENTRANTE'],
                ],
            ]);
            $mock->shouldReceive('getBoardCards')->andReturn([
                'success' => true,
                'data' => [
                    [
                        'id' => 'card_header_old_1',
                        'name' => 'WO 111 - EXISTING HEADER CLIENT - Banner Old',
                        'idList' => 'list_entrante',
                    ],
                    [
                        'id' => 'card_header_new_2',
                        'name' => 'WO 222 - FRESH HEADER CLIENT - Poster New',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            $mock->shouldReceive('syncCardToOrder')
                ->once()
                ->withArgs(fn ($card) => $card['id'] === 'card_header_new_2')
                ->andReturnUsing(function ($card) {
                    $newOrder = Order::create([
                        'company_name' => 'FRESH HEADER CLIENT',
                        'task_name' => 'Poster New',
                        'trello_card_id' => 'card_header_new_2',
                        'in_workspace' => false,
                        'is_new_from_trello' => true,
                        'core_status' => CoreStatus::ENTRANTE,
                    ]);

                    return [
                        'order' => $newOrder,
                        'action' => 'created',
                        'company_name' => 'FRESH HEADER CLIENT',
                        'task_name' => 'Poster New',
                        'previous_status' => null,
                        'new_status' => 'ENTRANTE',
                        'details' => ['Nueva tarjeta importada desde Trello'],
                    ];
                });
        });

        Livewire::test(HeaderSearch::class)
            ->assertSeeHtml('bg-[#0079bf]')
            ->assertSeeHtml('runTrelloSyncOnlyNew')
            ->call('runTrelloSyncOnlyNew')
            ->assertDispatched('order-updated')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('orders', [
            'company_name' => 'FRESH HEADER CLIENT',
            'trello_card_id' => 'card_header_new_2',
        ]);
    }

    public function test_header_search_trello_sync_available_for_all_user_roles(): void
    {
        $roles = [UserRole::ADMIN, UserRole::COORDINATOR, UserRole::DESIGNER, UserRole::SALES];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);

            Livewire::test(HeaderSearch::class)
                ->assertStatus(200)
                ->assertSeeHtml('bg-[#0079bf]')
                ->assertSeeHtml('runTrelloSyncOnlyNew');
        }
    }
}
