<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Orders\OrderDetailModal;
use App\Livewire\Orders\TrashBin;
use App\Models\Order;
use App\Models\User;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloTrashAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_trello_sync_service_archive_unarchive_and_delete_card(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/card_123*' => Http::response(['id' => 'card_123', 'closed' => true], 200),
        ]);

        $service = app(TrelloSyncService::class);

        $archiveResult = $service->archiveCard('card_123', apiKey: 'key_test', apiToken: 'token_test');
        $this->assertTrue($archiveResult);

        $unarchiveResult = $service->unarchiveCard('card_123', apiKey: 'key_test', apiToken: 'token_test');
        $this->assertTrue($unarchiveResult);

        $deleteResult = $service->deleteCard('card_123', apiKey: 'key_test', apiToken: 'token_test');
        $this->assertTrue($deleteResult);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_123')
                && $request->method() === 'PUT'
                && ($request['closed'] === 'true' || $request->data()['closed'] === 'true');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_123')
                && $request->method() === 'PUT'
                && ($request['closed'] === 'false' || $request->data()['closed'] === 'false');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_123')
                && $request->method() === 'DELETE';
        });
    }

    public function test_soft_deleting_order_archives_trello_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $order = Order::create([
            'company_name' => 'EMPRESA ARCHIVAR',
            'task_name' => 'TARJETA TEST',
            'trello_card_id' => 'card_archive_test',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/card_archive_test*' => Http::response(['id' => 'card_archive_test', 'closed' => true], 200),
        ]);

        $this->actingAs($admin);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->assertSeeHtml(__('Enviar a la papelera (archivar)'))
            ->call('deleteOrder')
            ->assertDispatched('order-updated');

        $this->assertSoftDeleted('orders', ['id' => $order->id]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_archive_test')
                && $request->method() === 'PUT'
                && ($request['closed'] === 'true' || $request->data()['closed'] === 'true');
        });
    }

    public function test_restoring_order_unarchives_trello_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $order = Order::create([
            'company_name' => 'EMPRESA RESTAURAR',
            'task_name' => 'TARJETA RESTORE',
            'trello_card_id' => 'card_restore_test',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);
        $order->delete();

        Http::fake([
            'https://api.trello.com/1/cards/card_restore_test*' => Http::response(['id' => 'card_restore_test', 'closed' => false], 200),
        ]);

        $this->actingAs($admin);

        Livewire::test(TrashBin::class)
            ->call('restoreOrder', $order->id);

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_restore_test')
                && $request->method() === 'PUT'
                && ($request['closed'] === 'false' || $request->data()['closed'] === 'false');
        });
    }

    public function test_force_deleting_order_permanently_deletes_trello_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $order = Order::create([
            'company_name' => 'EMPRESA FORCE DELETE',
            'task_name' => 'TARJETA FORCE DELETE',
            'trello_card_id' => 'card_permanent_test',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);
        $order->delete();

        Http::fake([
            'https://api.trello.com/1/cards/card_permanent_test*' => Http::response(['id' => 'card_permanent_test'], 200),
        ]);

        $this->actingAs($admin);

        Livewire::test(TrashBin::class)
            ->assertSeeHtml(__('Eliminar también de Trello'))
            ->call('forceDeleteOrder', $order->id);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_permanent_test')
                && $request->method() === 'DELETE';
        });
    }

    public function test_empty_trash_permanently_deletes_all_orders_and_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $order1 = Order::create([
            'company_name' => 'ORDEN TRASH 1',
            'task_name' => 'TASK 1',
            'trello_card_id' => 'card_empty_1',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);
        $order1->delete();

        $order2 = Order::create([
            'company_name' => 'ORDEN TRASH 2',
            'task_name' => 'TASK 2',
            'trello_card_id' => 'card_empty_2',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);
        $order2->delete();

        Http::fake([
            'https://api.trello.com/1/cards/card_empty_1*' => Http::response(['id' => 'card_empty_1'], 200),
            'https://api.trello.com/1/cards/card_empty_2*' => Http::response(['id' => 'card_empty_2'], 200),
        ]);

        $this->actingAs($admin);

        Livewire::test(TrashBin::class)
            ->assertSeeHtml(__('Vaciar papelera / eliminar todo definitivamente'))
            ->call('emptyTrash');

        $this->assertDatabaseMissing('orders', ['id' => $order1->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order2->id]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_empty_1')
                && $request->method() === 'DELETE';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/cards/card_empty_2')
                && $request->method() === 'DELETE';
        });
    }
}
