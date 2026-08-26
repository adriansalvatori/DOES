<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloWoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncing_card_with_no_wo_or_wo_0000_sets_pending_wo_number()
    {
        // 1. Create an existing order with WO 0000 in Backlog
        $order = Order::create([
            'trello_card_id' => 'card123abc',
            'wo_number' => 'WO 0000',
            'company_name' => 'Test Company',
            'task_name' => 'Sample Task',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $cardData = [
            'id' => 'card123abc',
            'name' => 'WO 9876 - Test Company - Sample Task',
            'idList' => 'list_entrante',
            'members' => [],
            'due' => null,
        ];

        $listsMap = ['list_entrante' => 'ENTRANTE'];

        // 2. Run syncCardToOrder
        $service = app(TrelloSyncService::class);
        $res = $service->syncCardToOrder($cardData, $listsMap);

        $order->refresh();

        // 3. Verify wo_number is preserved and pending_wo_number is set
        $this->assertEquals('WO 0000', $order->wo_number);
        $this->assertEquals('WO 9876', $order->pending_wo_number);
        $this->assertEquals('updated', $res['action']);
    }

    public function test_syncing_workspace_card_with_updated_wo_sets_pending_wo_number()
    {
        // Create an active workspace order without WO
        $order = Order::create([
            'trello_card_id' => 'card_workspace_1',
            'wo_number' => null,
            'company_name' => 'Workspace Corp',
            'task_name' => 'Design Banner',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $cardData = [
            'id' => 'card_workspace_1',
            'name' => 'WO 5432 - Workspace Corp - Design Banner',
            'idList' => 'list_entrante',
            'members' => [],
            'due' => null,
        ];

        $service = app(TrelloSyncService::class);
        $res = $service->syncCardToOrder($cardData, ['list_entrante' => 'ENTRANTE']);

        $this->assertEquals('conflict', $res['action']);
        $this->assertContains('wo_number', $res['diff_fields']);
        $this->assertEquals('WO 5432', $res['trello_data']['wo_number']);
    }

    public function test_user_can_accept_pending_wo_in_order_detail_modal()
    {
        $order = Order::create([
            'trello_card_id' => 'card_accept_1',
            'wo_number' => 'WO 0000',
            'pending_wo_number' => 'WO 7777',
            'company_name' => 'Accept Co',
            'task_name' => 'Accept Task',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(OrderDetailModal::class, ['orderId' => $order->id])
            ->call('acceptPendingWo')
            ->assertDispatched('order-updated');

        $order->refresh();

        $this->assertEquals('WO 7777', $order->wo_number);
        $this->assertNull($order->pending_wo_number);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'WO_UPDATED_FROM_TRELLO',
            'previous_value' => 'WO 0000',
            'new_value' => 'WO 7777',
        ]);
    }

    public function test_user_can_dismiss_pending_wo_in_order_detail_modal()
    {
        $order = Order::create([
            'trello_card_id' => 'card_dismiss_1',
            'wo_number' => 'WO 0000',
            'pending_wo_number' => 'WO 8888',
            'company_name' => 'Dismiss Co',
            'task_name' => 'Dismiss Task',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(OrderDetailModal::class, ['orderId' => $order->id])
            ->call('dismissPendingWo')
            ->assertDispatched('order-updated');

        $order->refresh();

        $this->assertEquals('WO 0000', $order->wo_number);
        $this->assertNull($order->pending_wo_number);
    }
}
