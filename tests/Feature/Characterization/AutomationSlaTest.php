<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\SlaEngine;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes the SLA rules: initial due dates, the Order SLA predicates and SlaEngine::checkOverdue().
 */
class AutomationSlaTest extends AutomationCharacterizationTestCase
{
    private function sla(): SlaEngine
    {
        return app(SlaEngine::class);
    }

    public function test_sla_constants(): void
    {
        $this->assertSame(3, SlaEngine::DESIGN_BASE_SLA_DAYS);
        $this->assertSame(2, SlaEngine::CLIENT_CHANGES_SLA_DAYS);
        $this->assertSame(2, SlaEngine::RESUME_FROM_HOLD_SLA_DAYS);
        $this->assertSame(3, SlaEngine::CLIENT_FOLLOWUP_INTERVAL_DAYS);
        $this->assertSame(9, SlaEngine::ON_HOLD_NO_RESPONSE_DAYS);
    }

    // ---- Initial due date ---------------------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function newOrderDueDates(): array
    {
        return [
            'monday' => ['2026-09-28 10:00:00', '2026-10-01'],
            'wednesday' => ['2026-09-30 10:00:00', '2026-10-05'],
            'friday' => ['2026-10-02 10:00:00', '2026-10-07'],
            'saturday counts from monday' => ['2026-10-03 10:00:00', '2026-10-08'],
            'sunday counts from monday' => ['2026-10-04 10:00:00', '2026-10-08'],
        ];
    }

    #[DataProvider('newOrderDueDates')]
    public function test_new_order_gets_three_business_days(string $createdAt, string $expectedDue): void
    {
        $this->freezeAt($createdAt);
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE]);

        $this->engine()->handleOrderCreated($order);

        $order->refresh();
        $this->assertSame($expectedDue, $order->current_due_date->toDateString());
        $this->assertSame($expectedDue, $order->original_due_date->toDateString());
        $this->assertSame(Carbon::parse($createdAt)->toDateString(), $order->start_date->toDateString());
    }

    public function test_new_order_logs_the_creation_event(): void
    {
        $order = $this->makeOrder(['company_name' => 'Acme', 'task_name' => 'Sign', 'core_status' => CoreStatus::ENTRANTE]);

        $this->engine()->handleOrderCreated($order, 'trello', 'Trello Sync');

        $event = $this->eventsOf($order, 'ORDER_CREATED')->sole();
        $this->assertSame('Trello Sync', $event->actor);
        $this->assertSame('ACME - SIGN', $event->new_value);
        $this->assertSame(['status' => CoreStatus::ENTRANTE->value, 'source' => 'trello'], $event->metadata);
    }

    public function test_new_order_from_trello_without_an_actor_is_attributed_to_trello(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE]);

        $this->engine()->handleOrderCreated($order, 'trello');

        $this->assertSame('Trello', $this->eventsOf($order, 'ORDER_CREATED')->sole()->actor);
    }

    public function test_welcome_task_is_skipped_for_cards_older_than_a_week(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE, 'trello_created_at' => now()->subDays(8)]);

        $this->engine()->handleOrderCreated($order);

        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
        $this->assertNotNull($order->fresh()->current_due_date);
    }

    public function test_welcome_task_is_created_for_cards_within_a_week(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE, 'trello_created_at' => now()->subDays(6)]);

        $this->engine()->handleOrderCreated($order);

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame(RelatedTaskType::BIENVENIDA, $task->type);
        $this->assertSame('NEW_ORDER_CREATED', $task->trigger_type);
        $this->assertSame('high', $task->priority);
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
    }

    // ---- isSlaExempt ---------------------------------------------------------------------------------

    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function slaExemptCases(): array
    {
        return [
            'entrante' => [['core_status' => CoreStatus::ENTRANTE], false],
            'euraliz queue' => [['core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED], false],
            'adrian queue' => [['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED], false],
            'cesar queue' => [['core_status' => CoreStatus::CESAR_ORDERS_RECEIVED], false],
            'working today' => [['core_status' => CoreStatus::TO_DO_TODAY], false],
            'sent to camila' => [['core_status' => CoreStatus::ENVIADO_A_CAMILA], false],
            'sent to client' => [['core_status' => CoreStatus::ENVIADO_AL_CLIENTE], true],
            'on hold' => [['core_status' => CoreStatus::ON_HOLD], true],
            'in production' => [['core_status' => CoreStatus::EN_PRODUCCION], true],
            'archived' => [['core_status' => CoreStatus::ARCHIVED], true],
            'done today' => [['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true], true],
            'outside workspace' => [['core_status' => CoreStatus::TO_DO_TODAY, 'in_workspace' => false], true],
            'paused substatus' => [['core_status' => CoreStatus::TO_DO_TODAY, 'substatus' => Substatus::PAUSADO], true],
        ];
    }

    #[DataProvider('slaExemptCases')]
    public function test_is_sla_exempt(array $attributes, bool $expected): void
    {
        $order = new Order(array_merge(['in_workspace' => true], $attributes));

        $this->assertSame($expected, $order->isSlaExempt());
    }

    // ---- isDueToday / isAlmostOverdue / isOverdue ----------------------------------------------------------

    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function dueTodayCases(): array
    {
        return [
            'working today' => [['core_status' => CoreStatus::TO_DO_TODAY], true],
            'camila' => [['core_status' => CoreStatus::ENVIADO_A_CAMILA], true],
            'entrante' => [['core_status' => CoreStatus::ENTRANTE], true],
            'archived is not excluded' => [['core_status' => CoreStatus::ARCHIVED], true],
            'done today' => [['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true], false],
            'outside workspace' => [['core_status' => CoreStatus::TO_DO_TODAY, 'in_workspace' => false], false],
            'on hold' => [['core_status' => CoreStatus::ON_HOLD], false],
            'paused substatus' => [['core_status' => CoreStatus::TO_DO_TODAY, 'substatus' => Substatus::PAUSADO], false],
            'production' => [['core_status' => CoreStatus::EN_PRODUCCION], false],
            'client' => [['core_status' => CoreStatus::ENVIADO_AL_CLIENTE], false],
        ];
    }

    #[DataProvider('dueTodayCases')]
    public function test_is_due_today(array $attributes, bool $expected): void
    {
        $order = $this->makeOrder(array_merge(['current_due_date' => now()->toDateString()], $attributes));

        $this->assertSame($expected, $order->isDueToday());
    }

    public function test_is_due_today_requires_a_due_date_of_today(): void
    {
        $this->assertFalse($this->makeOrder(['current_due_date' => now()->subDay()->toDateString()])->isDueToday());
        $this->assertFalse($this->makeOrder(['current_due_date' => now()->addDay()->toDateString()])->isDueToday());
        $this->assertFalse($this->makeOrder(['current_due_date' => null])->isDueToday());
    }

    /** @return array<string, array{string, bool}> */
    public static function almostOverdueByHour(): array
    {
        return [
            '00:00' => ['2026-09-30 00:00:00', true],
            '15:59' => ['2026-09-30 15:59:00', true],
            '16:00' => ['2026-09-30 16:00:00', false],
            '20:00' => ['2026-09-30 20:00:00', false],
        ];
    }

    #[DataProvider('almostOverdueByHour')]
    public function test_is_almost_overdue_only_before_four_pm_on_the_due_date(string $moment, bool $expected): void
    {
        $this->freezeAt($moment);
        $order = $this->makeOrder(['current_due_date' => now()->toDateString()]);

        $this->assertSame($expected, $order->isAlmostOverdue());
    }

    public function test_is_almost_overdue_is_false_when_not_due_today(): void
    {
        $this->assertFalse($this->makeOrder(['current_due_date' => now()->subDay()->toDateString()])->isAlmostOverdue());
        $this->assertFalse($this->makeOrder(['current_due_date' => now()->addDay()->toDateString()])->isAlmostOverdue());
    }

    /** @return array<string, array{string, int, bool}> */
    public static function overdueByDateAndHour(): array
    {
        return [
            'yesterday morning' => ['2026-09-30 09:00:00', -1, true],
            'last week' => ['2026-09-30 09:00:00', -7, true],
            'today 15:59' => ['2026-09-30 15:59:00', 0, false],
            'today 16:00' => ['2026-09-30 16:00:00', 0, true],
            'tomorrow 18:00' => ['2026-09-30 18:00:00', 1, false],
        ];
    }

    #[DataProvider('overdueByDateAndHour')]
    public function test_is_overdue(string $moment, int $dueOffsetDays, bool $expected): void
    {
        $this->freezeAt($moment);
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'current_due_date' => now()->addDays($dueOffsetDays)->toDateString()]);

        $this->assertSame($expected, $order->isOverdue());
    }

    public function test_is_overdue_is_false_without_a_due_date_even_with_overdue_substatus(): void
    {
        $order = $this->makeOrder(['substatus' => Substatus::OVERDUE, 'current_due_date' => null]);

        $this->assertFalse($order->isOverdue());
    }

    public function test_overdue_substatus_makes_a_non_exempt_order_overdue_regardless_of_the_date(): void
    {
        $order = new Order([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'substatus' => Substatus::OVERDUE,
            'current_due_date' => now()->addDays(5),
        ]);

        $this->assertTrue($order->isOverdue());
    }

    #[DataProvider('slaExemptCases')]
    public function test_exempt_orders_are_never_overdue(array $attributes, bool $exempt): void
    {
        $order = $this->makeOrder(array_merge(['current_due_date' => now()->subDays(3)->toDateString()], $attributes));

        $this->assertSame(! $exempt, $order->isOverdue());
    }

    // ---- SlaEngine::updateDueDate --------------------------------------------------------------------------

    public function test_update_due_date_records_history_and_event_and_keeps_the_original_date(): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-10-01', 'original_due_date' => '2026-09-29']);

        $this->sla()->updateDueDate($order, Carbon::parse('2026-10-08'), 'Client asked for more time', 'CUSTOM_TRIGGER', Carbon::parse('2026-10-08'), 'Camila');

        $order->refresh();
        $this->assertSame('2026-10-08', $order->current_due_date->toDateString());
        $this->assertSame('2026-09-29', $order->original_due_date->toDateString());
        $this->assertTrue($order->last_meaningful_update->isSameMinute(now()));

        $history = DueDateHistory::where('order_id', $order->id)->sole();
        $this->assertSame('2026-10-01', $history->previous_due_date->toDateString());
        $this->assertSame('2026-10-08', $history->new_due_date->toDateString());
        $this->assertSame('CUSTOM_TRIGGER', $history->trigger_event);
        $this->assertSame('Camila', $history->created_by);
        $this->assertSame('2026-10-08', $history->client_promised_date->toDateString());

        $event = $this->eventsOf($order, 'DUE_DATE_CHANGED')->sole();
        $this->assertSame('Camila', $event->actor);
        $this->assertSame('2026-10-01', $event->previous_value);
        $this->assertSame('2026-10-08', $event->new_value);
        $this->assertSame('Client asked for more time', $event->metadata['reason']);
    }

    public function test_update_due_date_sets_the_original_date_when_missing_and_uses_na_as_previous_value(): void
    {
        $order = $this->makeOrder(['current_due_date' => null, 'original_due_date' => null]);

        $this->sla()->updateDueDate($order, Carbon::parse('2026-10-08'), 'First date');

        $order->refresh();
        $this->assertSame('2026-10-08', $order->original_due_date->toDateString());
        $this->assertSame('N/A', $this->eventsOf($order, 'DUE_DATE_CHANGED')->sole()->previous_value);
        $this->assertNull(DueDateHistory::where('order_id', $order->id)->sole()->previous_due_date);
    }

    /** @return array<string, array{string}> */
    public static function silentTriggers(): array
    {
        return ['order approved' => ['ORDER_APPROVED'], 'delay resolved' => ['DELAY_RESOLVED_CLIENT_PROMISED_DATE']];
    }

    #[DataProvider('silentTriggers')]
    public function test_update_due_date_does_not_log_an_event_for_approval_and_delay_resolution(string $trigger): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-10-01']);

        $this->sla()->updateDueDate($order, Carbon::parse('2026-10-08'), 'x', $trigger);

        $this->assertSame(0, $this->eventsOf($order, 'DUE_DATE_CHANGED')->count());
        $this->assertSame(1, DueDateHistory::where('order_id', $order->id)->count());
    }

    // ---- SlaEngine::checkOverdue ---------------------------------------------------------------------------

    public function test_check_overdue_flags_an_overdue_order_and_clears_the_almost_overdue_flag(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->subDay()->toDateString(),
            'flags' => [Substatus::ALMOST_OVERDUE->value],
        ]);

        $this->assertTrue($this->sla()->checkOverdue($order));

        $order->refresh();
        $this->assertSame([Substatus::OVERDUE->value], $order->flags);
    }

    public function test_check_overdue_flags_an_order_due_today_as_almost_overdue(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->toDateString(),
        ]);

        $this->assertTrue($this->sla()->checkOverdue($order));

        $this->assertSame([Substatus::ALMOST_OVERDUE->value], $order->fresh()->flags);
    }

    public function test_check_overdue_turns_almost_overdue_into_overdue_after_four_pm(): void
    {
        $this->freezeAt('2026-09-30 16:30:00');
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->toDateString(),
            'flags' => [Substatus::ALMOST_OVERDUE->value],
        ]);

        $this->assertTrue($this->sla()->checkOverdue($order));

        $this->assertSame([Substatus::OVERDUE->value], $order->fresh()->flags);
    }

    public function test_check_overdue_clears_flags_when_the_due_date_moves_to_the_future(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->addDays(2)->toDateString(),
            'flags' => [Substatus::OVERDUE->value, 'KEEP'],
        ]);

        $this->assertFalse($this->sla()->checkOverdue($order));

        $this->assertSame(['KEEP'], $order->fresh()->flags);
    }

    public function test_check_overdue_clears_flags_and_overdue_substatus_for_exempt_statuses(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'current_due_date' => now()->subDays(3)->toDateString(),
            'flags' => [Substatus::OVERDUE->value],
        ]);

        $this->assertFalse($this->sla()->checkOverdue($order));

        $this->assertSame([], $order->fresh()->flags);
    }

    public function test_check_overdue_replaces_overdue_substatus_on_exempt_orders_with_the_status_default(): void
    {
        $inProduction = new Order(['core_status' => CoreStatus::EN_PRODUCCION, 'in_workspace' => true, 'substatus' => Substatus::OVERDUE]);
        $withClient = new Order(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'in_workspace' => true, 'substatus' => Substatus::ALMOST_OVERDUE]);
        $onHold = new Order(['core_status' => CoreStatus::ON_HOLD, 'in_workspace' => true, 'substatus' => Substatus::OVERDUE]);
        foreach ([$inProduction, $withClient, $onHold] as $order) {
            $order->company_name = 'X';
            $order->task_name = 'Y';
            $order->save();
        }

        $this->sla()->checkOverdue($inProduction);
        $this->sla()->checkOverdue($withClient);
        $this->sla()->checkOverdue($onHold);

        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $inProduction->fresh()->substatus);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $withClient->fresh()->substatus);
        $this->assertNull($onHold->fresh()->substatus);
    }

    public function test_check_overdue_without_a_due_date_clears_flags_and_dismisses_delay_tasks(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => null,
            'flags' => [Substatus::OVERDUE->value],
        ]);
        $delay = $this->makeTask($order, ['title' => 'Enviar correo de atraso preventivo', 'type' => RelatedTaskType::CORREO_ATRASO]);

        $this->assertFalse($this->sla()->checkOverdue($order));

        $this->assertSame([], $order->fresh()->flags);
        $this->assertNull(RelatedTask::withTrashed()->find($delay->id));
    }

    /** @return array<string, array{string, bool}> */
    public static function delayTaskByClock(): array
    {
        return [
            '14:29' => ['2026-09-30 14:29:00', false],
            '14:30' => ['2026-09-30 14:30:00', true],
            '15:00' => ['2026-09-30 15:00:00', true],
        ];
    }

    #[DataProvider('delayTaskByClock')]
    public function test_check_overdue_creates_the_delay_task_for_orders_due_today_only_from_two_thirty(string $moment, bool $creates): void
    {
        $this->freezeAt($moment);
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'current_due_date' => now()->toDateString()]);

        $this->sla()->checkOverdue($order);

        $this->assertSame($creates ? 1 : 0, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::CORREO_ATRASO)->count());
    }

    public function test_check_overdue_creates_no_delay_task_for_overdue_orders_before_two_thirty(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'current_due_date' => now()->subDay()->toDateString()]);

        $this->sla()->checkOverdue($order);

        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_check_overdue_is_not_part_of_the_daily_automations(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->subDay()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertNull($order->fresh()->flags);
    }
}
