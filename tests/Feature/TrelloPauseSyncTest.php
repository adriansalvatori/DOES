<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\TrelloPauseToggle;
use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloPauseSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(TrelloSyncService::class)->setPaused(false);
    }

    public function test_can_get_set_and_toggle_paused_state(): void
    {
        $service = app(TrelloSyncService::class);

        $this->assertFalse($service->isPaused());

        $service->setPaused(true);
        $this->assertTrue($service->isPaused());

        $newStatus = $service->togglePaused();
        $this->assertFalse($newStatus);
        $this->assertFalse($service->isPaused());
    }

    public function test_update_card_on_trello_is_skipped_when_paused(): void
    {
        Http::preventStrayRequests();

        $service = app(TrelloSyncService::class);
        $service->setPaused(true);

        $order = Order::create([
            'company_name' => 'TEST COMPANY',
            'task_name' => 'TEST TASK',
            'trello_card_id' => 'card_123',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $result = $service->updateCardOnTrello($order);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_create_card_on_trello_is_skipped_when_paused(): void
    {
        Http::preventStrayRequests();

        $service = app(TrelloSyncService::class);
        $service->setPaused(true);

        $order = Order::create([
            'company_name' => 'NEW COMPANY',
            'task_name' => 'NEW TASK',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $result = $service->createCardOnTrello($order);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['paused']);
        Http::assertNothingSent();
    }

    public function test_add_card_comment_is_skipped_when_paused(): void
    {
        Http::preventStrayRequests();

        $service = app(TrelloSyncService::class);
        $service->setPaused(true);

        $result = $service->addCardComment('card_123', 'Test comment');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['paused']);
        Http::assertNothingSent();
    }

    public function test_livewire_trello_pause_toggle_component(): void
    {
        $service = app(TrelloSyncService::class);
        $service->setPaused(false);

        Livewire::test(TrelloPauseToggle::class)
            ->assertSet('isPaused', false)
            ->call('togglePause')
            ->assertSet('isPaused', true)
            ->assertDispatched('trello-pause-updated');

        $this->assertTrue($service->isPaused());
    }
}
