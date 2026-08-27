<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Client;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrelloSyncConflictFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(TrelloSyncService::class)->setPaused(false);
    }

    public function test_resolve_use_workspace_pushes_title_to_trello_and_persists_in_local_db(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response([], 200),
        ]);

        $order = Order::create([
            'wo_number' => 'WO 1500',
            'company_name' => 'ACME CORP',
            'task_name' => 'ACME CORP - LOGO DESIGN',
            'trello_card_id' => 'card_123',
            'trello_title' => 'Old Raw Trello Title',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $component = new TrelloSync;
        $component->userToken = 'valid_token';
        $component->syncReport = [
            'total' => 1,
            'conflicts' => 1,
            'pushed' => 0,
            'changes' => [
                [
                    'order_id' => $order->id,
                    'action' => 'conflict',
                    'company' => 'ACME CORP',
                    'task' => 'LOGO DESIGN',
                ],
            ],
        ];

        $component->resolveUseWorkspace($order->id);

        $order->refresh();
        $this->assertEquals('WO 1500 ACME CORP - LOGO DESIGN', $order->trello_title);
        $this->assertEquals('LOGO DESIGN', $order->task_name);
        $this->assertEquals(0, $component->syncReport['conflicts']);
        $this->assertEquals(1, $component->syncReport['pushed']);
        $this->assertEquals('pushed_to_trello', $component->syncReport['changes'][0]['action']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.trello.com/1/cards/card_123')
                && $request['name'] === 'WO 1500 ACME CORP - LOGO DESIGN';
        });
    }

    public function test_resolve_use_workspace_retains_conflict_on_trello_api_failure_for_retry(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $order = Order::create([
            'wo_number' => 'WO 9999',
            'company_name' => 'FAILED INC',
            'task_name' => 'FLYER',
            'trello_card_id' => 'card_failed_1',
            'trello_title' => 'Initial Title',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $component = new TrelloSync;
        $component->userToken = 'invalid_token';
        $component->syncReport = [
            'total' => 1,
            'conflicts' => 1,
            'pushed' => 0,
            'changes' => [
                [
                    'order_id' => $order->id,
                    'action' => 'conflict',
                    'company' => 'FAILED INC',
                    'task' => 'FLYER',
                ],
            ],
        ];

        $component->resolveUseWorkspace($order->id);

        $order->refresh();
        $this->assertEquals('Initial Title', $order->trello_title);
        $this->assertEquals(1, $component->syncReport['conflicts']);
        $this->assertEquals('conflict', $component->syncReport['changes'][0]['action']);
        $this->assertTrue($component->syncReport['changes'][0]['push_error']);
    }

    public function test_resolve_use_trello_updates_local_db_and_re_evaluates_client(): void
    {
        $client = Client::create([
            'name' => 'SUPERMERCADOS METRO',
            'aliases' => ['METRO'],
        ]);

        $order = Order::create([
            'company_name' => 'OLD NAME',
            'task_name' => 'OLD TASK',
            'wo_number' => 'WO 100',
            'trello_card_id' => 'card_trello_1',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $component = new TrelloSync;
        $component->syncReport = [
            'total' => 1,
            'conflicts' => 1,
            'updated' => 0,
            'changes' => [
                [
                    'order_id' => $order->id,
                    'action' => 'conflict',
                    'trello_data' => [
                        'company_name' => 'METRO',
                        'task_name' => 'NUEVO AFICHE',
                        'wo_number' => 'WO 200',
                        'trello_title' => 'WO 200 METRO NUEVO AFICHE',
                    ],
                ],
            ],
        ];

        $component->resolveUseTrello($order->id);

        $order->refresh();
        $this->assertEquals('SUPERMERCADOS METRO', $order->company_name);
        $this->assertEquals($client->id, $order->client_id);
        $this->assertEquals('NUEVO AFICHE', $order->task_name);
        $this->assertEquals('WO 200', $order->wo_number);
        $this->assertEquals('WO 200 METRO NUEVO AFICHE', $order->trello_title);
        $this->assertEquals(0, $component->syncReport['conflicts']);
        $this->assertEquals(1, $component->syncReport['updated']);
    }
}
