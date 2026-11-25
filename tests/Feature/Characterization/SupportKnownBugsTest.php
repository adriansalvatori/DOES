<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\ClientMatchingService;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Each test asserts the CORRECT behavior and is marked incomplete because current code is wrong.
 * Remove the markTestIncomplete line when the bug is fixed in the rebuild.
 */
class SupportKnownBugsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_done_evaluation_runs_once_per_task_update(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: evaluateSubtaskCompletionAutoDone runs twice per task update (RelatedTask booted hook and RelatedTaskObserver).');

        $order = Order::create(['company_name' => 'BUG CO', 'task_name' => 'x', 'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'in_workspace' => true]);
        $task = RelatedTask::create(['order_id' => $order->id, 'title' => 'Ajuste', 'status' => 'todo', 'scheduled_date' => today()->toDateString()]);

        $this->partialMock(AutomationEngine::class, function (MockInterface $mock) {
            $mock->shouldReceive('evaluateSubtaskCompletionAutoDone')->once();
        });

        $task->update(['status' => 'done']);
    }

    public function test_falta_logo_is_not_detected_as_poner_en_alta(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: "falta logo" is detected as Poner en alta because "FALTA" contains "ALTA" (RelatedTask::isPonerEnAlta).');

        $task = new RelatedTask(['title' => 'Falta logo', 'category' => SubtaskCategory::NEW_DESIGN]);

        $this->assertFalse($task->isPonerEnAlta());
        $this->assertNotSame('production', $task->colorCodingKey());
    }

    public function test_new_trello_cards_stay_in_the_backlog_even_when_a_welcome_task_is_created(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: new Trello cards jump into the workspace via the welcome task (RelatedTask created hook sets in_workspace).');

        Http::fake(['*' => Http::response([], 200)]);
        $cardId = dechex(now()->timestamp).str_repeat('a', 16);

        app(TrelloSyncService::class)->syncCardToOrder(
            ['id' => $cardId, 'name' => 'WO 20100 - BUG CLIENT - Sign', 'idList' => 'l1', 'members' => [], 'due' => null],
            ['l1' => 'ENTRANTE'],
        );

        $this->assertFalse(Order::where('trello_card_id', $cardId)->firstOrFail()->in_workspace);
    }

    public function test_trello_sync_does_not_create_designers_for_unknown_card_members(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: Trello sync creates a Designer for every unknown card member (and currently crashes: TrelloSyncService lacks the Str import).');

        Http::fake(['*' => Http::response([], 200)]);
        $cardId = dechex(now()->timestamp).str_repeat('b', 16);

        app(TrelloSyncService::class)->syncCardToOrder(
            ['id' => $cardId, 'name' => 'WO 20100 - BUG CLIENT - Sign', 'idList' => 'l1', 'members' => [['id' => 'm1', 'fullName' => 'Random Member', 'username' => 'rm']], 'due' => null],
            ['l1' => 'ENTRANTE'],
        );

        $this->assertSame(0, Designer::count());
        $this->assertNotNull(Order::where('trello_card_id', $cardId)->first());
    }

    public function test_client_matching_does_not_split_hyphenated_brand_names(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: client matching splits on any hyphen or slash, so COCA-COLA becomes client COCA with location COLA.');

        $parsed = app(ClientMatchingService::class)->cleanCompanyName('COCA-COLA');

        $this->assertSame('COCA-COLA', $parsed['client_name']);
        $this->assertNull($parsed['location_name']);
    }

    public function test_overdue_flags_are_refreshed_without_a_trello_sync(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: overdue flags only update inside a Trello sync (SlaEngine::checkOverdue has no other caller), so they freeze when sync is paused or off.');

        $order = Order::create([
            'company_name' => 'BUG CO', 'task_name' => 'x', 'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'in_workspace' => true,
            'current_due_date' => now()->subDays(3)->toDateString(),
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $this->assertTrue($order->fresh()->hasFlag(Substatus::OVERDUE));
    }
}
