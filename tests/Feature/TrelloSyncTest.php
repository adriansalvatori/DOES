<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Order;
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
}
