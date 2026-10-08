<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncing_trello_removes_orders_that_no_longer_exist_on_trello(): void
    {
        // Existing order in backlog that was deleted/archived on Trello
        $archivedOrder = Order::create([
            'company_name' => 'ARCHIVED CLIENT',
            'task_name' => 'Old Banner',
            'trello_card_id' => 'trello_card_archived_999',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        // Active order in Trello
        $activeOrder = Order::create([
            'company_name' => 'ACTIVE CLIENT',
            'task_name' => 'Active Sign',
            'trello_card_id' => 'trello_card_active_111',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        // Mock Trello API response returning only activeOrder
        $mockService = $this->mock(TrelloSyncService::class, function ($mock) use ($activeOrder) {
            $mock->shouldReceive('extractBoardId')->andReturn('mock_board_id');
            $mock->shouldReceive('getBoardLists')->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 'list_1', 'name' => 'ENTRANTE'],
                ],
            ]);
            $mock->shouldReceive('getBoardCards')->andReturn([
                'success' => true,
                'data' => [
                    [
                        'id' => 'trello_card_active_111',
                        'name' => 'WO 100 - ACTIVE CLIENT - Active Sign',
                        'idList' => 'list_1',
                    ],
                ],
            ]);
            $mock->shouldReceive('syncCardToOrder')->andReturn([
                'order' => $activeOrder,
                'action' => 'unchanged',
                'company_name' => 'ACTIVE CLIENT',
                'task_name' => 'Active Sign',
                'previous_status' => 'ENTRANTE',
                'new_status' => 'ENTRANTE',
            ]);
            $mock->shouldReceive('handleMissingOrders')->passthru();
        });

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'mock_board_id')
            ->call('runTrelloSync')
            ->assertSet('syncReport.deleted', 1);

        // Verify archived order was marked as missing from Trello instead of being purged
        $this->assertDatabaseHas('orders', [
            'id' => $archivedOrder->id,
            'is_missing_from_trello' => true,
        ]);

        // Verify active order remains
        $this->assertDatabaseHas('orders', [
            'id' => $activeOrder->id,
            'is_missing_from_trello' => false,
        ]);
    }

    public function test_syncing_trello_completes_order_if_core_status_is_in_production(): void
    {
        $productionOrder = Order::create([
            'company_name' => 'PRODUCTION CLIENT',
            'task_name' => 'Production Banner',
            'trello_card_id' => 'trello_prod_card_999',
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        $service = new TrelloSyncService;
        $result = $service->handleMissingOrders(['some_other_card_123']);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals('completed', $result['changes'][0]['action']);

        $productionOrder->refresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $productionOrder->core_status);
        $this->assertNotNull($productionOrder->archived_at);
        $this->assertTrue($productionOrder->is_missing_from_trello);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $productionOrder->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'previous_value' => CoreStatus::EN_PRODUCCION->value,
            'new_value' => CoreStatus::ARCHIVED->value,
        ]);
    }

    public function test_active_workspace_orders_retain_local_status_during_trello_sync(): void
    {

        $workspaceOrder = Order::create([
            'company_name' => 'WORKSPACE CLIENT',
            'task_name' => 'Poster Design',
            'wo_number' => 'WO 200',
            'trello_card_id' => 'trello_card_workspace_555',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $service = new TrelloSyncService;
        $res = $service->syncCardToOrder([
            'id' => 'trello_card_workspace_555',
            'name' => 'WO 200 - WORKSPACE CLIENT - Poster Design',
            'idList' => 'list_entrante',
        ], [
            'list_entrante' => 'ENTRANTE',
        ]);

        $this->assertEquals('pushed_to_trello', $res['action']);
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $res['order']->fresh()->core_status);
    }

    public function test_syncing_card_tracks_update_details(): void
    {
        $existingOrder = Order::create([
            'company_name' => 'TALPA CORPORATE',
            'task_name' => 'CORPORATE - VIEJA LOCACION',
            'trello_title' => 'WO 100 - TALPA CORPORATE - CORPORATE - VIEJA LOCACION',
            'trello_card_id' => 'trello_card_update_777',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
            'current_due_date' => '2026-08-20',
        ]);

        $service = new TrelloSyncService;
        $res = $service->syncCardToOrder([
            'id' => 'trello_card_update_777',
            'name' => 'WO 100 - TALPA CORPORATE - CORPORATE - NUEVA LOCACION',
            'due' => '2026-08-25T12:00:00.000Z',
            'idList' => 'list_entrante',
        ], [
            'list_entrante' => 'ENTRANTE',
        ]);

        $this->assertEquals('updated', $res['action']);
        $this->assertNotEmpty($res['details']);
        $this->assertStringContainsString('Fecha de entrega', implode(' ', $res['details']));
        $this->assertStringContainsString('Tarea:', implode(' ', $res['details']));
    }

    public function test_save_settings_persists_board_id_and_token_in_database_and_dispatches_event(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'https://trello.com/b/NEWBOARD123/my-board')
            ->set('userToken', 'new_token_64_characters_long_12345678901234567890123456789012345')
            ->call('saveSettings')
            ->assertDispatched('trello-settings-saved');

        $this->assertEquals('https://trello.com/b/NEWBOARD123/my-board', Setting::get('trello_board_id'));
        $this->assertEquals('new_token_64_characters_long_12345678901234567890123456789012345', Setting::get('trello_user_token'));
    }

    public function test_sync_only_new_cards_imports_new_cards_and_leaves_existing_untouched(): void
    {
        $existingOrder = Order::create([
            'company_name' => 'ORIGINAL COMPANY',
            'task_name' => 'Original Task Name',
            'trello_card_id' => 'card_existing_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) {
            $mock->shouldReceive('extractBoardId')->andReturn('mock_board_id');
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
                        'id' => 'card_existing_123',
                        'name' => 'WO 999 - CHANGED COMPANY - Changed Task Name',
                        'idList' => 'list_entrante',
                    ],
                    [
                        'id' => 'card_brand_new_456',
                        'name' => 'WO 500 - BRAND NEW CLIENT - New Flyer Design',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            $mock->shouldReceive('syncCardToOrder')
                ->once()
                ->withArgs(fn ($card) => $card['id'] === 'card_brand_new_456')
                ->andReturnUsing(function ($card) {
                    $newOrder = Order::create([
                        'company_name' => 'BRAND NEW CLIENT',
                        'task_name' => 'New Flyer Design',
                        'trello_card_id' => 'card_brand_new_456',
                        'in_workspace' => false,
                        'is_new_from_trello' => true,
                        'core_status' => CoreStatus::ENTRANTE,
                    ]);

                    return [
                        'order' => $newOrder,
                        'action' => 'created',
                        'company_name' => 'BRAND NEW CLIENT',
                        'task_name' => 'New Flyer Design',
                        'previous_status' => null,
                        'new_status' => 'ENTRANTE',
                        'details' => ['Nueva tarjeta importada desde Trello'],
                    ];
                });
        });

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'mock_board_id')
            ->call('runTrelloSyncOnlyNew')
            ->assertSet('syncReport.show', true)
            ->assertSet('syncReport.added', 1)
            ->assertSet('syncReport.unchanged', 1)
            ->assertSet('syncReport.conflicts', 0)
            ->assertSet('syncReport.pushed', 0)
            ->assertSet('syncReport.moved', 0)
            ->assertSet('syncReport.updated', 0)
            ->assertSet('syncReport.deleted', 0)
            ->assertSee('Sincronización completada: se importaron 1 tarjetas nuevas de Trello.');

        // Verify existing order was NOT modified at all
        $existingOrder->refresh();
        $this->assertEquals('ORIGINAL COMPANY', $existingOrder->company_name);
        $this->assertEquals('ORIGINAL TASK NAME', $existingOrder->task_name);
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $existingOrder->core_status);
        $this->assertTrue($existingOrder->in_workspace);

        // Verify new order exists in database
        $this->assertDatabaseHas('orders', [
            'trello_card_id' => 'card_brand_new_456',
            'company_name' => 'BRAND NEW CLIENT',
            'is_new_from_trello' => true,
            'in_workspace' => false,
        ]);
    }

    public function test_sync_only_new_cards_when_no_new_cards_exist(): void
    {
        Order::create([
            'company_name' => 'EXISTING CLIENT',
            'task_name' => 'Existing Task',
            'trello_card_id' => 'card_existing_999',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) {
            $mock->shouldReceive('extractBoardId')->andReturn('mock_board_id');
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
                        'id' => 'card_existing_999',
                        'name' => 'WO 123 - EXISTING CLIENT - Existing Task',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            // syncCardToOrder should NOT be called at all
            $mock->shouldNotReceive('syncCardToOrder');
        });

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'mock_board_id')
            ->call('runTrelloSyncOnlyNew')
            ->assertSet('syncReport.show', true)
            ->assertSet('syncReport.added', 0)
            ->assertSet('syncReport.unchanged', 1)
            ->assertSee('No hay tarjetas nuevas en Trello (1 tarjetas existentes ya están registradas).');
    }

    public function test_sync_only_new_skips_and_canonicalizes_order_matched_by_shortlink(): void
    {
        // Existing order in DOES created by pasting Trello link with 8-char shortLink
        $manualOrder = Order::create([
            'company_name' => 'MANUAL CLIENT',
            'task_name' => 'Manual Card',
            'trello_card_id' => 'AbCdEf12',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) {
            $mock->shouldReceive('extractBoardId')->andReturn('mock_board_id');
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
                        'id' => '674a2b8e19c43f721598b012', // 24-char canonical ID from Trello API
                        'shortLink' => 'AbCdEf12',           // 8-char shortLink from web link
                        'name' => 'WO 999 - MANUAL CLIENT - Manual Card',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            // It should NOT call syncCardToOrder to import a new card
            $mock->shouldNotReceive('syncCardToOrder');
        });

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'mock_board_id')
            ->call('runTrelloSyncOnlyNew')
            ->assertSet('syncReport.show', true)
            ->assertSet('syncReport.added', 0)
            ->assertSet('syncReport.unchanged', 1);

        // Verify order trello_card_id was auto-canonicalized to the 24-char ID
        $manualOrder->refresh();
        $this->assertEquals('674a2b8e19c43f721598b012', $manualOrder->trello_card_id);
        $this->assertTrue($manualOrder->in_workspace);

        // Verify NO duplicate orders were created
        $this->assertEquals(1, Order::count());
    }

    public function test_cleanup_duplicates_command_merges_and_removes_duplicate_order(): void
    {
        // 1. Original order created by user
        $original = Order::create([
            'company_name' => 'ORIGINAL CLIENT',
            'task_name' => 'Main Design',
            'trello_card_id' => 'AbCdEf12',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        // 2. Duplicate order imported by sync into backlog
        $duplicate = Order::create([
            'company_name' => 'ORIGINAL CLIENT',
            'task_name' => 'Main Design',
            'trello_card_id' => '674a2b8e19c43f721598b012',
            'in_workspace' => false,
            'is_new_from_trello' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) {
            $mock->shouldReceive('extractBoardId')->andReturn('mock_board_id');
            $mock->shouldReceive('getBoardCards')->andReturn([
                'success' => true,
                'data' => [
                    [
                        'id' => '674a2b8e19c43f721598b012',
                        'shortLink' => 'AbCdEf12',
                        'name' => 'ORIGINAL CLIENT - Main Design',
                    ],
                ],
            ]);
        });

        Setting::set('trello_board_id', 'mock_board_id');
        Setting::set('trello_api_key', 'test_key');
        Setting::set('trello_user_token', 'test_token');

        $this->artisan('trello:cleanup-duplicates')
            ->expectsOutputToContain('Limpieza completada: Se resolvieron 1 órdenes duplicadas.')
            ->assertSuccessful();

        // Original order was updated with canonical ID
        $original->refresh();
        $this->assertEquals('674a2b8e19c43f721598b012', $original->trello_card_id);

        // Duplicate order was deleted
        $this->assertDatabaseMissing('orders', ['id' => $duplicate->id]);
        $this->assertEquals(1, Order::count());
    }

    public function test_find_existing_order_by_trello_card_matches_shortlink_and_canonicalizes(): void
    {
        $service = app(TrelloSyncService::class);

        $order = Order::create([
            'company_name' => 'ACME CORP',
            'task_name' => 'Logo Redesign',
            'trello_card_id' => 'xYz98765',
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $cardData = [
            'id' => '6543210987abcdef01234567',
            'shortLink' => 'xYz98765',
            'shortUrl' => 'https://trello.com/c/xYz98765',
            'name' => 'ACME CORP - Logo Redesign',
        ];

        $found = $service->findExistingOrderByTrelloCard($cardData);

        $this->assertNotNull($found);
        $this->assertEquals($order->id, $found->id);

        $order->refresh();
        $this->assertEquals('6543210987abcdef01234567', $order->trello_card_id);
    }

    public function test_handle_missing_orders_preserves_orders_matching_incoming_shortlinks(): void
    {
        $service = app(TrelloSyncService::class);

        $order = Order::create([
            'company_name' => 'ACME',
            'task_name' => 'Signage',
            'trello_card_id' => 'shortLink99',
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        // Incoming card has canonical ID and shortLink
        $res = $service->handleMissingOrders(['674a2b8e19c43f721598b012'], ['shortLink99']);

        $this->assertEquals(0, $res['count']);
        $order->refresh();
        $this->assertFalse((bool) $order->is_missing_from_trello);
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $order->core_status);
    }

    public function test_resolve_canonical_card_id_strips_url_and_handles_inputs(): void
    {
        $service = app(TrelloSyncService::class);

        // 1. Full URL with slug
        $res1 = $service->resolveCanonicalCardId('https://trello.com/c/short123/123-some-card-slug');
        $this->assertEquals('short123', $res1['id']);

        // 2. Full URL with query parameters
        $res2 = $service->resolveCanonicalCardId('https://trello.com/c/short456?filter=open');
        $this->assertEquals('short456', $res2['id']);

        // 3. 24-char hex ID already canonical
        $canonical = '674a2b8e19c43f721598b012';
        $res3 = $service->resolveCanonicalCardId($canonical);
        $this->assertEquals($canonical, $res3['id']);
    }
}
