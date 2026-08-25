<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Backlog\Index as BacklogIndex;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BacklogTrelloSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_run_trello_sync_from_backlog_view_and_display_modal(): void
    {
        $order = Order::create([
            'company_name' => 'BACKLOG CLIENT',
            'task_name' => 'New Logo Design',
            'trello_card_id' => 'trello_card_backlog_101',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->mock(TrelloSyncService::class, function ($mock) use ($order) {
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
                        'id' => 'trello_card_backlog_101',
                        'name' => 'WO 999 - BACKLOG CLIENT - New Logo Design',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            $mock->shouldReceive('syncCardToOrder')->andReturn([
                'order' => $order,
                'action' => 'updated',
                'company_name' => 'BACKLOG CLIENT',
                'task_name' => 'New Logo Design',
                'previous_status' => 'ENTRANTE',
                'new_status' => 'ENTRANTE',
            ]);
        });

        Livewire::test(BacklogIndex::class)
            ->call('runTrelloSync')
            ->assertSet('syncReport.show', true)
            ->assertSet('syncReport.updated', 1)
            ->assertSee(__('Resumen de Sincronización Trello'))
            ->call('setFilter', 'updated')
            ->assertSet('activeFilter', 'updated')
            ->call('closeReportModal')
            ->assertSet('syncReport.show', false);
    }
}
