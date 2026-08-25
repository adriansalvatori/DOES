<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloCardCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetching_trello_card_comments(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_123/actions*' => Http::response([
                [
                    'id' => 'action_comment_1',
                    'date' => '2026-08-23T12:00:00.000Z',
                    'data' => ['text' => 'Primer comentario de prueba en Trello'],
                    'memberCreator' => [
                        'id' => 'user_1',
                        'fullName' => 'Juan Pérez',
                        'username' => 'juanperez',
                        'avatarUrl' => 'https://trello-avatars.s3.amazonaws.com/avatar1',
                    ],
                ],
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $result = $service->getCardComments('card_123');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['comments']);
        $this->assertEquals('action_comment_1', $result['comments'][0]['id']);
        $this->assertEquals('Primer comentario de prueba en Trello', $result['comments'][0]['text']);
        $this->assertEquals('Juan Pérez', $result['comments'][0]['author_name']);
    }

    public function test_adding_trello_card_comment(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_123/actions/comments*' => Http::response([
                'id' => 'action_comment_new',
                'date' => '2026-08-23T14:00:00.000Z',
                'data' => ['text' => 'Nuevo comentario agregado desde Kudos'],
                'memberCreator' => [
                    'id' => 'user_kudos',
                    'fullName' => 'Admin Kudos',
                    'username' => 'adminkudos',
                ],
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $result = $service->addCardComment('card_123', 'Nuevo comentario agregado desde Kudos', 'fake_key', 'fake_token');

        $this->assertTrue($result['success']);
        $this->assertEquals('action_comment_new', $result['comment']['id']);
        $this->assertEquals('Nuevo comentario agregado desde Kudos', $result['comment']['text']);
    }

    public function test_get_workspace_active_orders_comments(): void
    {
        $orderInWorkspace = Order::create([
            'company_name' => 'EMPRESA WORKSPACE',
            'task_name' => 'Diseño de Pendón',
            'trello_card_id' => 'card_workspace_999',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Order::create([
            'company_name' => 'EMPRESA BACKLOG',
            'task_name' => 'Logo',
            'trello_card_id' => 'card_backlog_888',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_workspace_999/actions*' => Http::response([
                [
                    'id' => 'comm_ws_1',
                    'date' => '2026-08-23T15:00:00.000Z',
                    'data' => ['text' => 'Comentario en orden activa del workspace'],
                    'memberCreator' => ['fullName' => 'Diseñador 1'],
                ],
            ], 200),
        ]);

        $service = new TrelloSyncService;
        $results = $service->getWorkspaceActiveOrdersComments('fake_key', 'fake_token');

        $this->assertArrayHasKey($orderInWorkspace->id, $results);
        $this->assertCount(1, $results);
        $this->assertTrue($results[$orderInWorkspace->id]['success']);
        $this->assertCount(1, $results[$orderInWorkspace->id]['comments']);
        $this->assertEquals('Comentario en orden activa del workspace', $results[$orderInWorkspace->id]['comments'][0]['text']);
    }

    public function test_order_detail_modal_loads_and_posts_trello_comments(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA TEST',
            'task_name' => 'Volante Promocional',
            'trello_card_id' => 'card_test_modal',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_test_modal/actions*' => Http::response([
                [
                    'id' => 'comm_modal_1',
                    'date' => '2026-08-23T15:30:00.000Z',
                    'data' => ['text' => 'Comentario de prueba en modal'],
                    'memberCreator' => ['fullName' => 'Camila'],
                ],
            ], 200),
            'https://api.trello.com/1/cards/card_test_modal/actions/comments*' => Http::response([
                'id' => 'comm_modal_posted',
                'date' => '2026-08-23T15:35:00.000Z',
                'data' => ['text' => 'Comentario publicado desde el modal'],
                'memberCreator' => ['fullName' => 'Usuario App'],
            ], 200),
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertSet('isLoadingTrelloComments', false)
            ->assertCount('trelloComments', 1)
            ->set('newTrelloComment', 'Comentario publicado desde el modal')
            ->call('addTrelloComment')
            ->assertSet('newTrelloComment', '');

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'TRELLO_COMMENT_ADDED',
        ]);
    }
}
