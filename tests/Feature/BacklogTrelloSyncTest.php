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
            $mock->shouldReceive('handleMissingOrders')->passthru();
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

    public function test_can_run_trello_sync_only_new_from_backlog_view(): void
    {
        $existingOrder = Order::create([
            'company_name' => 'EXISTING BACKLOG CLIENT',
            'task_name' => 'Banner Old',
            'trello_card_id' => 'card_backlog_old_1',
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
                        'id' => 'card_backlog_old_1',
                        'name' => 'WO 111 - EXISTING BACKLOG CLIENT - Banner Old',
                        'idList' => 'list_entrante',
                    ],
                    [
                        'id' => 'card_backlog_new_2',
                        'name' => 'WO 222 - FRESH CLIENT - Poster New',
                        'idList' => 'list_entrante',
                    ],
                ],
            ]);
            $mock->shouldReceive('syncCardToOrder')
                ->once()
                ->withArgs(fn ($card) => $card['id'] === 'card_backlog_new_2')
                ->andReturnUsing(function ($card) {
                    $newOrder = Order::create([
                        'company_name' => 'FRESH CLIENT',
                        'task_name' => 'Poster New',
                        'trello_card_id' => 'card_backlog_new_2',
                        'in_workspace' => false,
                        'is_new_from_trello' => true,
                        'core_status' => CoreStatus::ENTRANTE,
                    ]);

                    return [
                        'order' => $newOrder,
                        'action' => 'created',
                        'company_name' => 'FRESH CLIENT',
                        'task_name' => 'Poster New',
                        'previous_status' => null,
                        'new_status' => 'ENTRANTE',
                        'details' => ['Nueva tarjeta importada desde Trello'],
                    ];
                });
        });

        Livewire::test(BacklogIndex::class)
            ->call('runTrelloSyncOnlyNew')
            ->assertSet('syncReport.show', true)
            ->assertSet('syncReport.added', 1)
            ->assertSet('syncReport.unchanged', 1)
            ->assertSee('Sincronización completada: se importaron 1 tarjetas nuevas de Trello.');
    }
}
