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
        });

        Livewire::test(TrelloSync::class)
            ->set('boardId', 'mock_board_id')
            ->call('runTrelloSync')
            ->assertSet('syncReport.deleted', 1);

        // Verify archived order was purged from database
        $this->assertDatabaseMissing('orders', [
            'id' => $archivedOrder->id,
        ]);

        // Verify active order remains
        $this->assertDatabaseHas('orders', [
            'id' => $activeOrder->id,
        ]);
    }
}
