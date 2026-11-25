<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\SlaEngine;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Characterizes what a Trello sync run does to local data. The per-card step is
 * TrelloSyncService::syncCardToOrder (there is no syncBoard method); the board loop
 * lives in the trello:sync command and the Settings TrelloSync component.
 */
class SupportTrelloSyncSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    private const LISTS = ['list_entrante' => 'ENTRANTE', 'list_today' => 'TO DO TODAY', 'list_prod' => 'EN PRODUCCION'];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 200)]);
    }

    private function recentCardId(string $suffix = 'a'): string
    {
        return dechex(now()->timestamp).str_repeat($suffix, 16);
    }

    private function oldCardId(string $suffix = 'b'): string
    {
        return '5a0c0000'.str_repeat($suffix, 16);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function card(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => 'WO 20100 - SYNC CLIENT - Window Sign',
            'idList' => 'list_entrante',
            'members' => [],
            'due' => null,
        ], $overrides);
    }

    private function sync(array $card): ?array
    {
        return app(TrelloSyncService::class)->syncCardToOrder($card, self::LISTS, 'board1', 'key', 'token');
    }

    public function test_old_new_card_lands_in_backlog_without_entering_the_workspace(): void
    {
        $result = $this->sync($this->card($this->oldCardId()));

        $this->assertSame('created', $result['action']);
        $order = Order::where('trello_card_id', $this->oldCardId())->firstOrFail();
        $this->assertFalse($order->in_workspace);
        $this->assertTrue((bool) $order->is_new_from_trello);
        $this->assertSame('WO 20100', $order->wo_number);
        $this->assertSame('SYNC CLIENT', $order->company_name);
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_recent_new_card_gets_welcome_task_which_flips_it_into_the_workspace(): void
    {
        $this->sync($this->card($this->recentCardId()));

        $order = Order::where('trello_card_id', $this->recentCardId())->firstOrFail();
        $this->assertTrue($order->in_workspace);
        $welcome = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::BIENVENIDA->value)->first();
        $this->assertNotNull($welcome);
        $this->assertSame('NEW_ORDER_CREATED', $welcome->trigger_type);
    }

    public function test_due_dates_and_start_date_are_overwritten_on_every_run(): void
    {
        $cardId = $this->oldCardId();
        $this->sync($this->card($cardId, ['due' => now()->addDays(10)->toIso8601String()]));
        $order = Order::where('trello_card_id', $cardId)->firstOrFail();
        $order->update(['current_due_date' => now()->addDays(2)->toDateString(), 'original_due_date' => now()->addDays(1)->toDateString(), 'start_date' => now()->subDays(5)->toDateString()]);

        $this->sync($this->card($cardId, ['due' => now()->addDays(20)->toIso8601String()]));

        $order->refresh();
        $this->assertSame(now()->addDays(20)->toDateString(), $order->current_due_date->toDateString());
        $this->assertSame(now()->addDays(20)->toDateString(), $order->original_due_date->toDateString());
        $this->assertSame(now()->toDateString(), $order->start_date->toDateString());
    }

    public function test_removing_the_trello_due_date_clears_the_local_due_date(): void
    {
        $cardId = $this->oldCardId();
        $this->sync($this->card($cardId, ['due' => now()->addDays(10)->toIso8601String()]));

        $this->sync($this->card($cardId, ['due' => null]));

        $this->assertNull(Order::where('trello_card_id', $cardId)->firstOrFail()->current_due_date);
    }

    public function test_card_member_already_known_by_trello_member_id_is_reused_without_creating_designers(): void
    {
        $designer = Designer::create(['name' => 'Zoe Existing', 'active' => true, 'trello_member_id' => 'member-1']);
        $member = ['id' => 'member-1', 'fullName' => 'Zoe Existing', 'username' => 'zoeex'];

        $this->sync($this->card($this->oldCardId('b'), ['members' => [$member]]));
        $this->sync($this->card($this->oldCardId('c'), ['name' => 'WO 20101 - OTHER CLIENT - Flyer', 'members' => [$member]]));

        $this->assertSame(1, Designer::count());
        $this->assertSame($designer->id, Order::where('trello_card_id', $this->oldCardId('c'))->firstOrFail()->designer_id);
    }

    public function test_known_designer_is_matched_by_name_and_gets_member_id_stored(): void
    {
        $designer = Designer::create(['name' => 'Adrián Known', 'active' => true]);

        $this->sync($this->card($this->oldCardId(), ['members' => [['id' => 'm-9', 'fullName' => 'Adrián Known', 'username' => 'adk']]]));

        $this->assertSame(1, Designer::count());
        $this->assertSame('m-9', $designer->fresh()->trello_member_id);
    }

    public function test_card_without_members_falls_back_to_designer_queue_for_its_list(): void
    {
        $queueOwner = Designer::create(['name' => 'Queue Owner', 'active' => true, 'queue_status_value' => CoreStatus::ENTRANTE->value]);

        $this->sync($this->card($this->oldCardId()));

        $this->assertSame($queueOwner->id, Order::where('trello_card_id', $this->oldCardId())->firstOrFail()->designer_id);
    }

    public function test_valid_trello_wo_on_existing_order_goes_to_pending_and_keeps_local_wo(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-1', 'wo_number' => 'WO 10050', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign',
            'in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE,
        ]);

        $result = $this->sync($this->card('existing-card-1', ['name' => 'WO 20100 - SYNC CLIENT - Window Sign']));

        $order->refresh();
        $this->assertSame('WO 10050', $order->wo_number);
        $this->assertSame('WO 20100', $order->pending_wo_number);
        $this->assertSame('updated', $result['action']);
    }

    public function test_matching_wo_clears_a_stale_pending_wo(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-2', 'wo_number' => 'WO 20100', 'pending_wo_number' => 'WO 99999', 'company_name' => 'SYNC CLIENT',
            'task_name' => 'Window Sign', 'in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->sync($this->card('existing-card-2'));

        $this->assertNull($order->fresh()->pending_wo_number);
    }

    public function test_zero_wo_from_trello_is_not_treated_as_valid(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-3', 'wo_number' => 'WO 10050', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign',
            'in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->sync($this->card('existing-card-3', ['name' => 'WO 0000 - SYNC CLIENT - Window Sign']));

        $this->assertNull($order->fresh()->pending_wo_number);
    }

    public function test_workspace_conflict_is_returned_and_not_applied(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-4', 'wo_number' => 'WO 20100', 'company_name' => 'LOCAL NAME', 'task_name' => 'Window Sign',
            'in_workspace' => true, 'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $result = $this->sync($this->card('existing-card-4', ['name' => 'WO 20100 - TRELLO NAME - Window Sign']));

        $this->assertSame('conflict', $result['action']);
        $this->assertContains('company_name', $result['diff_fields']);
        $order->refresh();
        $this->assertSame('LOCAL NAME', $order->company_name);
        $this->assertNotSame('TRELLO NAME', $order->trello_title);
    }

    public function test_workspace_order_keeps_local_status_and_pushes_it_to_trello(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-5', 'wo_number' => 'WO 20100', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign',
            'in_workspace' => true, 'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $result = $this->sync($this->card('existing-card-5', ['idList' => 'list_entrante']));

        $this->assertSame('pushed_to_trello', $result['action']);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_backlog_order_follows_the_trello_list(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-6', 'wo_number' => 'WO 20100', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign',
            'in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE,
        ]);

        $result = $this->sync($this->card('existing-card-6', ['idList' => 'list_today']));

        $this->assertSame('moved', $result['action']);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_overdue_flag_is_only_set_when_a_sync_run_evaluates_the_order(): void
    {
        $order = Order::create([
            'trello_card_id' => 'existing-card-7', 'wo_number' => 'WO 20100', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign',
            'in_workspace' => true, 'core_status' => CoreStatus::TO_DO_TODAY, 'current_due_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->assertFalse($order->fresh()->hasFlag(Substatus::OVERDUE));

        $this->sync($this->card('existing-card-7', ['idList' => 'list_today', 'due' => now()->subDays(3)->toIso8601String()]));

        $this->assertTrue($order->fresh()->hasFlag(Substatus::OVERDUE));
    }

    public function test_sla_check_overdue_is_not_invoked_for_new_cards(): void
    {
        $this->sync($this->card($this->oldCardId(), ['idList' => 'list_today', 'due' => now()->subDays(3)->toIso8601String()]));

        $order = Order::where('trello_card_id', $this->oldCardId())->firstOrFail();
        $this->assertFalse($order->hasFlag(Substatus::OVERDUE));
    }

    public function test_sla_engine_check_overdue_can_be_called_directly_to_flag_an_order(): void
    {
        $order = Order::create([
            'wo_number' => 'WO 20100', 'company_name' => 'SYNC CLIENT', 'task_name' => 'Window Sign', 'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY, 'current_due_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertTrue(app(SlaEngine::class)->checkOverdue($order));
        $this->assertTrue($order->fresh()->hasFlag(Substatus::OVERDUE));
    }

    public function test_missing_production_order_is_archived_and_others_are_flagged(): void
    {
        $present = Order::create(['trello_card_id' => 'present', 'company_name' => 'A', 'task_name' => 'a', 'core_status' => CoreStatus::ENTRANTE]);
        $production = Order::create(['trello_card_id' => 'gone-prod', 'company_name' => 'B', 'task_name' => 'b', 'core_status' => CoreStatus::EN_PRODUCCION, 'in_workspace' => true]);
        $other = Order::create(['trello_card_id' => 'gone-other', 'company_name' => 'C', 'task_name' => 'c', 'core_status' => CoreStatus::TO_DO_TODAY, 'in_workspace' => true]);
        $noCard = Order::create(['company_name' => 'D', 'task_name' => 'd', 'core_status' => CoreStatus::ENTRANTE]);

        $result = app(TrelloSyncService::class)->handleMissingOrders(['present']);

        $this->assertSame(2, $result['count']);
        $production->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $production->core_status);
        $this->assertNotNull($production->archived_at);
        $this->assertTrue($production->is_missing_from_trello);
        $other->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $other->core_status);
        $this->assertTrue($other->is_missing_from_trello);
        $this->assertFalse((bool) $present->fresh()->is_missing_from_trello);
        $this->assertFalse((bool) $noCard->fresh()->is_missing_from_trello);
        $this->assertEqualsCanonicalizing(['completed', 'deleted'], array_column($result['changes'], 'action'));
    }

    public function test_incompatible_header_cards_are_skipped(): void
    {
        $result = $this->sync($this->card($this->oldCardId(), ['name' => 'TO DO TODAY']));

        $this->assertNull($result);
        $this->assertSame(0, Order::count());
    }

    public function test_trello_sync_command_creates_cards_and_marks_missing_ones_using_http_fake(): void
    {
        config(['services.trello.api_key' => 'key', 'services.trello.token' => 'token', 'services.trello.paused' => false]);
        $stale = Order::create(['trello_card_id' => 'stale-card', 'company_name' => 'S', 'task_name' => 's', 'core_status' => CoreStatus::ENTRANTE]);
        Http::swap(new Factory);
        Http::fake([
            'api.trello.com/1/boards/*/lists*' => Http::response([['id' => 'list_entrante', 'name' => 'ENTRANTE']], 200),
            'api.trello.com/1/boards/*/cards*' => Http::response([$this->card($this->oldCardId())], 200),
            '*' => Http::response([], 200),
        ]);

        $this->artisan('trello:sync', ['--board' => 'board1'])->assertExitCode(0);

        $this->assertNotNull(Order::where('trello_card_id', $this->oldCardId())->first());
        $this->assertTrue($stale->fresh()->is_missing_from_trello);
    }

    public function test_trello_sync_command_does_nothing_when_paused(): void
    {
        config(['services.trello.api_key' => 'key', 'services.trello.token' => 'token', 'services.trello.paused' => true]);

        $this->artisan('trello:sync', ['--board' => 'board1'])->assertExitCode(0);

        $this->assertSame(0, Order::count());
    }
}
