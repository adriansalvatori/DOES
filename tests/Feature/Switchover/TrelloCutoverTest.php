<?php

namespace Tests\Feature\Switchover;

use App\Models\Order;
use App\Services\TrelloSyncService;
use App\Support\TrelloCutover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrelloCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected const CARD_ID = '5f0000000000000000000009';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(TrelloSyncService::PAUSE_CACHE_KEY);
        config([
            'services.trello.board_id' => 'board-1',
            'services.trello.api_key' => 'trello-key',
            'services.trello.token' => 'trello-token',
            'services.trello.paused' => false,
        ]);
    }

    private function fakeBoard(int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.trello.com/1/boards/board-1/cards/all*' => Http::response($status === 200 ? [[
                'id' => self::CARD_ID,
                'shortLink' => 'AbCd0009',
                'name' => 'ACME - SIGN',
                'desc' => 'Medidas confirmadas',
                'closed' => false,
                'attachments' => [],
            ]] : ['error' => 'boom'], $status),
            'api.trello.com/1/boards/board-1/actions*' => Http::response([]),
            'api.trello.com/1/boards/board-1/members*' => Http::response([]),
        ]);
    }

    private function linkedOrder(): Order
    {
        return Order::factory()->create(['trello_card_id' => self::CARD_ID, 'in_workspace' => true]);
    }

    public function test_cutover_pauses_sync_imports_every_order_and_marks_the_cutover_complete(): void
    {
        $this->fakeBoard();
        $order = $this->linkedOrder();

        $this->artisan('trello:cutover', ['--without-files' => true])->assertSuccessful();

        $this->assertTrue(TrelloCutover::isComplete());
        $this->assertTrue(app(TrelloSyncService::class)->isPaused());
        $order->refresh();
        $this->assertNotNull($order->trello_migrated_at);
        $this->assertSame('Medidas confirmadas', $order->description);
    }

    public function test_dry_run_reads_the_board_but_changes_nothing(): void
    {
        $this->fakeBoard();
        $order = $this->linkedOrder();

        $this->artisan('trello:cutover', ['--dry-run' => true, '--without-files' => true])->assertSuccessful();

        $this->assertFalse(TrelloCutover::isComplete());
        $this->assertFalse(app(TrelloSyncService::class)->isPaused());
        $this->assertNull($order->fresh()->trello_migrated_at);
    }

    public function test_a_failed_import_leaves_the_cutover_incomplete_and_the_sync_paused(): void
    {
        $this->fakeBoard(500);
        $this->linkedOrder();

        $this->artisan('trello:cutover', ['--without-files' => true])->assertFailed();

        $this->assertFalse(TrelloCutover::isComplete());
        $this->assertTrue(app(TrelloSyncService::class)->isPaused());
    }

    public function test_running_it_again_after_completion_does_nothing(): void
    {
        $this->fakeBoard();
        TrelloCutover::markComplete();

        $this->artisan('trello:cutover')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_after_the_cutover_changing_an_order_never_pushes_to_trello(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.trello.com/*' => Http::response([])]);
        $order = $this->linkedOrder();
        TrelloCutover::markComplete();
        Http::fake(['api.trello.com/*' => Http::response([])]);

        $order->update(['task_name' => 'NEW TITLE AFTER CUTOVER']);
        $order->delete();
        $order->restore();

        Http::assertNothingSent();
    }

    public function test_before_the_cutover_changing_an_order_still_pushes_to_trello(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.trello.com/*' => Http::response([])]);
        $order = $this->linkedOrder();
        Http::fake(['api.trello.com/*' => Http::response([])]);

        $order->update(['task_name' => 'NEW TITLE BEFORE CUTOVER']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.trello.com'));
    }
}
