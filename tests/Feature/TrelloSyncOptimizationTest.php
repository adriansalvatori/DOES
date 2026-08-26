<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Client;
use App\Models\Order;
use App\Services\OrderTitleParserService;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrelloSyncOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(TrelloSyncService::class)->setPaused(false);
    }

    public function test_client_alias_matches_parsed_company_name(): void
    {
        $client = Client::create([
            'name' => 'FUERZA LATINA',
            'aliases' => ['FL', 'Fuerza Latina Insurance'],
        ]);

        $res = OrderTitleParserService::parse('WO 1234 - FL - LOGO DESIGN');

        $this->assertEquals('FUERZA LATINA', $res['company_name']);
        $this->assertEquals('LOGO DESIGN', $res['task_name']);
    }

    public function test_wo_normalization_prevents_false_conflict(): void
    {
        $order = Order::create([
            'company_name' => 'TAQUITO',
            'task_name' => 'MENU BANNER',
            'wo_number' => '16181',
            'trello_card_id' => 'card_wo_norm_1',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $service = app(TrelloSyncService::class);

        $cardData = [
            'id' => 'card_wo_norm_1',
            'name' => 'WO 16181 - TAQUITO - MENU BANNER',
            'due' => null,
            'idList' => 'list_entrante',
        ];

        $res = $service->syncCardToOrder($cardData, ['list_entrante' => 'ENTRANTE'], 'board_123');

        $this->assertNotEquals('conflict', $res['action']);
    }

    public function test_batch_resolve_all_workspace_resolves_all_conflicts(): void
    {
        $order1 = Order::create([
            'company_name' => 'CLIENT ONE',
            'task_name' => 'NEW TASK ONE',
            'trello_card_id' => 'card_batch_1',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $order2 = Order::create([
            'company_name' => 'CLIENT TWO',
            'task_name' => 'NEW TASK TWO',
            'trello_card_id' => 'card_batch_2',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $component = new TrelloSync;
        $component->syncReport = [
            'total' => 2,
            'created' => 0,
            'pushed' => 0,
            'moved' => 0,
            'updated' => 0,
            'deleted' => 0,
            'conflicts' => 2,
            'changes' => [
                [
                    'order_id' => $order1->id,
                    'action' => 'conflict',
                    'company' => 'CLIENT ONE',
                    'task' => 'NEW TASK ONE',
                ],
                [
                    'order_id' => $order2->id,
                    'action' => 'conflict',
                    'company' => 'CLIENT TWO',
                    'task' => 'NEW TASK TWO',
                ],
            ],
        ];

        $component->resolveAllWorkspace();

        $this->assertEquals(0, $component->syncReport['conflicts']);
        $this->assertEquals('pushed_to_trello', $component->syncReport['changes'][0]['action']);
        $this->assertEquals('pushed_to_trello', $component->syncReport['changes'][1]['action']);
    }
}
