<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloSyncConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(TrelloSyncService::class)->setPaused(false);
    }

    public function test_unicode_en_dash_does_not_trigger_false_conflict(): void
    {
        $order = Order::create([
            'company_name' => 'NITRO 2 GO',
            'task_name' => 'LOGO PROPOSAL',
            'wo_number' => 'WO 16170',
            'trello_card_id' => 'card_dash_999',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $service = app(TrelloSyncService::class);

        // Card title uses Unicode en-dash '–' (\u{2013})
        $cardData = [
            'id' => 'card_dash_999',
            'name' => "WO 16170 - NITRO 2 GO \u{2013} LOGO PROPOSAL",
            'due' => null,
            'idList' => 'list_123',
        ];

        $res = $service->syncCardToOrder($cardData, ['list_123' => 'ENTRANTE'], 'board_123');

        // Should not trigger a company name conflict because en-dash is normalized to hyphen
        $this->assertNotEquals('conflict', $res['action']);
    }

    public function test_conflict_detected_when_active_workspace_order_has_different_name(): void
    {
        $order = Order::create([
            'company_name' => 'KUDOS LOCAL NAME',
            'task_name' => 'NEW WORKSPACE TASK',
            'trello_card_id' => 'card_conflict_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $service = app(TrelloSyncService::class);

        $cardData = [
            'id' => 'card_conflict_123',
            'name' => 'KUDOS LOCAL NAME - OLD TRELLO TASK',
            'due' => null,
            'idList' => 'list_123',
            'dateLastActivity' => '2026-08-26T10:00:00Z',
        ];

        $listsMap = ['list_123' => 'ENTRANTE'];

        $res = $service->syncCardToOrder($cardData, $listsMap, 'board_123');

        $this->assertNotNull($res);
        $this->assertEquals('conflict', $res['action']);
        $this->assertContains('task_name', $res['diff_fields']);
        $this->assertEquals('NEW WORKSPACE TASK', $res['workspace_data']['task_name']);
        $this->assertEquals('OLD TRELLO TASK', $res['trello_data']['task_name']);

        // Assert local order was NOT overwritten
        $order->refresh();
        $this->assertEquals('NEW WORKSPACE TASK', $order->task_name);
    }

    public function test_resolve_conflict_use_workspace_pushes_to_trello(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response(['id' => 'card_conflict_123', 'name' => 'KUDOS LOCAL NAME - NEW WORKSPACE TASK'], 200),
        ]);

        $order = Order::create([
            'company_name' => 'KUDOS LOCAL NAME',
            'task_name' => 'NEW WORKSPACE TASK',
            'trello_card_id' => 'card_conflict_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $testComp = Livewire::test(TrelloSync::class)
            ->set('syncReport', [
                'show' => true,
                'total' => 1,
                'added' => 0,
                'moved' => 0,
                'pushed' => 0,
                'updated' => 0,
                'conflicts' => 1,
                'deleted' => 0,
                'unchanged' => 0,
                'timestamp' => now()->format('d M, Y'),
                'changes' => [
                    [
                        'order_id' => $order->id,
                        'action' => 'conflict',
                        'company' => 'KUDOS LOCAL NAME',
                        'task' => 'NEW WORKSPACE TASK',
                        'diff_fields' => ['task_name'],
                        'workspace_data' => [
                            'company_name' => 'KUDOS LOCAL NAME',
                            'task_name' => 'NEW WORKSPACE TASK',
                            'wo_number' => null,
                            'designer_name' => 'Sin asignar',
                        ],
                        'trello_data' => [
                            'company_name' => 'KUDOS LOCAL NAME',
                            'task_name' => 'OLD TRELLO TASK',
                            'wo_number' => null,
                            'designer_name' => 'Sin asignar',
                        ],
                    ],
                ],
            ])
            ->call('resolveUseWorkspace', $order->id);

        $testComp->assertSet('syncReport.conflicts', 0);
        $testComp->assertSet('syncReport.pushed', 1);

        // Order in DB remains workspace name
        $order->refresh();
        $this->assertEquals('NEW WORKSPACE TASK', $order->task_name);
    }

    public function test_resolve_conflict_use_trello_updates_local_order(): void
    {
        $order = Order::create([
            'company_name' => 'KUDOS LOCAL NAME',
            'task_name' => 'NEW WORKSPACE TASK',
            'trello_card_id' => 'card_conflict_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $testComp = Livewire::test(TrelloSync::class)
            ->set('syncReport', [
                'show' => true,
                'total' => 1,
                'added' => 0,
                'moved' => 0,
                'pushed' => 0,
                'updated' => 0,
                'conflicts' => 1,
                'deleted' => 0,
                'unchanged' => 0,
                'timestamp' => now()->format('d M, Y'),
                'changes' => [
                    [
                        'order_id' => $order->id,
                        'action' => 'conflict',
                        'company' => 'KUDOS LOCAL NAME',
                        'task' => 'NEW WORKSPACE TASK',
                        'diff_fields' => ['task_name'],
                        'workspace_data' => [
                            'company_name' => 'KUDOS LOCAL NAME',
                            'task_name' => 'NEW WORKSPACE TASK',
                            'wo_number' => null,
                            'designer_name' => 'Sin asignar',
                        ],
                        'trello_data' => [
                            'company_name' => 'KUDOS LOCAL NAME',
                            'task_name' => 'OLD TRELLO TASK',
                            'wo_number' => null,
                            'designer_name' => 'Sin asignar',
                        ],
                    ],
                ],
            ])
            ->call('resolveUseTrello', $order->id);

        $testComp->assertSet('syncReport.conflicts', 0);
        $testComp->assertSet('syncReport.updated', 1);

        // Order in DB is updated to Trello's name
        $order->refresh();
        $this->assertEquals('OLD TRELLO TASK', $order->task_name);
    }
}
