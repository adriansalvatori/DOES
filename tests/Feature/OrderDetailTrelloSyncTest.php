<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDetailTrelloSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_order_details_in_modal_triggers_realtime_trello_sync(): void
    {
        Http::fake([
            'api.trello.com/1/cards/*' => Http::response(['id' => 'trello_card_detail_123', 'name' => 'WO 500 - NUEVA EMPRESA - NUEVA TAREA'], 200),
            'api.trello.com/1/boards/*' => Http::response([['id' => 'list_123', 'name' => 'ENTRANTE']], 200),
        ]);

        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'VIEJA EMPRESA',
            'task_name' => 'VIEJA TAREA',
            'wo_number' => 'WO 100',
            'trello_card_id' => 'trello_card_detail_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id, true)
            ->set('editCompanyName', 'NUEVA EMPRESA')
            ->set('editTaskName', 'NUEVA TAREA')
            ->set('editWoNumber', '500')
            ->call('saveOrder');

        $order->refresh();
        $this->assertEquals('NUEVA EMPRESA', $order->company_name);
        $this->assertEquals('NUEVA TAREA', $order->task_name);
        $this->assertEquals('WO 500', $order->wo_number);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/trello_card_detail_123') &&
                   $request->method() === 'PUT' &&
                   str_contains($request['name'], 'NUEVA EMPRESA');
        });
    }

    public function test_order_observer_automatically_syncs_direct_updates_to_trello(): void
    {
        Http::fake([
            'api.trello.com/1/cards/*' => Http::response(['id' => 'trello_card_observer_123'], 200),
            'api.trello.com/1/boards/*' => Http::response([['id' => 'list_123', 'name' => 'ENTRANTE']], 200),
        ]);

        $order = Order::create([
            'company_name' => 'ORIGINAL CO',
            'task_name' => 'ORIGINAL TASK',
            'trello_card_id' => 'trello_card_observer_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $order->update(['task_name' => 'UPDATED DIRECT TASK']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/trello_card_observer_123') &&
                   $request->method() === 'PUT' &&
                   str_contains($request['name'], 'UPDATED DIRECT TASK');
        });
    }

    public function test_clearing_due_date_in_order_detail_sends_null_due_to_trello(): void
    {
        Http::fake([
            'api.trello.com/1/cards/*' => Http::response(['id' => 'trello_card_date_123'], 200),
            'api.trello.com/1/boards/*' => Http::response([['id' => 'list_123', 'name' => 'ENTRANTE']], 200),
        ]);

        $order = Order::create([
            'company_name' => 'DATE CO',
            'task_name' => 'DATE TASK',
            'trello_card_id' => 'trello_card_date_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'current_due_date' => '2026-08-30',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('clearDueDate');

        $order->refresh();
        $this->assertNull($order->current_due_date);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/trello_card_date_123') &&
                   $request->method() === 'PUT' &&
                   isset($request['due']) &&
                   $request['due'] === 'null';
        });
    }

    public function test_accepting_pending_wo_syncs_new_wo_title_to_trello(): void
    {
        Http::fake([
            'api.trello.com/1/cards/*' => Http::response(['id' => 'trello_card_wo_123'], 200),
            'api.trello.com/1/boards/*' => Http::response([['id' => 'list_123', 'name' => 'ENTRANTE']], 200),
        ]);

        $order = Order::create([
            'company_name' => 'WO CO',
            'task_name' => 'WO TASK',
            'wo_number' => null,
            'pending_wo_number' => 'WO 9999',
            'trello_card_id' => 'trello_card_wo_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('acceptPendingWo');

        $order->refresh();
        $this->assertEquals('WO 9999', $order->wo_number);
        $this->assertNull($order->pending_wo_number);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/trello_card_wo_123') &&
                   $request->method() === 'PUT' &&
                   str_contains($request['name'], 'WO 9999');
        });
    }
}
