<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\SystemTaskConfig;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes AutomationEngine::runDailyAutomations() step by step (the 07:00 job).
 */
class AutomationDailyCycleTest extends AutomationCharacterizationTestCase
{
    private function orderSentToClientWeekdaysAgo(int $weekdays, array $overrides = []): Order
    {
        return $this->makeOrder(array_merge([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'last_sent_to_client_at' => now()->subWeekdays($weekdays),
            'current_due_date' => now()->addDays(10)->toDateString(),
        ], $overrides));
    }

    /** @return array<string, array{int}> */
    public static function followUpOneDays(): array
    {
        return ['day 3' => [3], 'day 4' => [4], 'day 5' => [5]];
    }

    /** @return array<string, array{int}> */
    public static function followUpTwoDays(): array
    {
        return ['day 6' => [6], 'day 7' => [7], 'day 8' => [8]];
    }

    // ---- Step 1: client follow-ups and auto ON HOLD -------------------------------------------------

    #[DataProvider('followUpOneDays')]
    public function test_follow_up_one_task_is_created_between_three_and_five_weekdays(int $days): void
    {
        $order = $this->orderSentToClientWeekdaysAgo($days);

        $this->engine()->runDailyAutomations();

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('Follow Up Cliente #1', $task->title);
        $this->assertSame(RelatedTaskType::FOLLOW_UP_CLIENTE, $task->type);
        $this->assertSame('CLIENT_FOLLOW_UP_CYCLE', $task->trigger_type);
        $this->assertSame('todo', $task->status);
        $this->assertSame('normal', $task->priority);
        $this->assertSame(SubtaskCategory::MANAGEMENT, $task->category);
        $this->assertFalse($task->is_work_task);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame('2026-09-30', $task->scheduled_date->toDateString());
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    /** @return array<string, array{int}> */
    public static function noFollowUpDays(): array
    {
        return ['day 0' => [0], 'day 1' => [1], 'day 2' => [2]];
    }

    #[DataProvider('noFollowUpDays')]
    public function test_no_follow_up_task_before_three_weekdays(int $days): void
    {
        $order = $this->orderSentToClientWeekdaysAgo($days);

        $this->engine()->runDailyAutomations();

        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    #[DataProvider('followUpTwoDays')]
    public function test_follow_up_two_task_is_created_between_six_and_eight_weekdays(int $days): void
    {
        $order = $this->orderSentToClientWeekdaysAgo($days);

        $this->engine()->runDailyAutomations();

        $this->assertSame(['Follow Up Cliente #2'], RelatedTask::where('order_id', $order->id)->pluck('title')->all());
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    /** @return array<string, array{int}> */
    public static function onHoldDays(): array
    {
        return ['day 9' => [9], 'day 10' => [10], 'day 20' => [20]];
    }

    #[DataProvider('onHoldDays')]
    public function test_order_moves_to_on_hold_with_customer_service_required_from_nine_weekdays(int $days): void
    {
        $order = $this->orderSentToClientWeekdaysAgo($days);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::ON_HOLD, $order->core_status);
        $this->assertSame(Substatus::CUSTOMER_SERVICE_REQUIRED, $order->substatus);
        $this->assertTrue($order->customer_service_required);
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());

        $event = $this->eventsOf($order, 'AUTO_MOVED_ON_HOLD')->sole();
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE->value, $event->previous_value);
        $this->assertSame(CoreStatus::ON_HOLD->value, $event->new_value);
        $this->assertSame('Client non-responsive for 9+ business days', $event->metadata['reason']);
    }

    public function test_auto_on_hold_does_not_go_through_handle_status_changed(): void
    {
        $order = $this->orderSentToClientWeekdaysAgo(9);

        $this->engine()->runDailyAutomations();

        $this->assertSame(0, $this->eventsOf($order, 'CORE_STATUS_CHANGED')->count());
    }

    public function test_follow_up_task_is_not_duplicated_on_second_run(): void
    {
        $order = $this->orderSentToClientWeekdaysAgo(4);

        $this->engine()->runDailyAutomations();
        $this->engine()->runDailyAutomations();

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('title', 'Follow Up Cliente #1')->count());
    }

    public function test_follow_up_one_and_two_are_distinct_tasks_across_days(): void
    {
        $order = $this->orderSentToClientWeekdaysAgo(4);
        $this->engine()->runDailyAutomations();

        $this->freezeAt('2026-10-05 09:00:00');
        $this->engine()->runDailyAutomations();

        $this->assertSame(
            ['Follow Up Cliente #1', 'Follow Up Cliente #2'],
            RelatedTask::where('order_id', $order->id)->orderBy('id')->pluck('title')->all()
        );
    }

    public function test_inactive_system_task_config_suppresses_follow_up_tasks(): void
    {
        SystemTaskConfig::create([
            'task_type' => RelatedTaskType::FOLLOW_UP_CLIENTE->value,
            'title' => 'Follow up',
            'description' => 'Follow up with the client',
            'category' => 'Cliente',
            'is_active' => false,
        ]);
        $order = $this->orderSentToClientWeekdaysAgo(4);

        $this->engine()->runDailyAutomations();

        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_sent_date_falls_back_to_last_meaningful_update(): void
    {
        $order = $this->orderSentToClientWeekdaysAgo(0, [
            'last_sent_to_client_at' => null,
            'last_meaningful_update' => now()->subWeekdays(7),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(['Follow Up Cliente #2'], RelatedTask::where('order_id', $order->id)->pluck('title')->all());
    }

    public function test_orders_outside_the_workspace_still_get_client_follow_ups(): void
    {
        $order = $this->orderSentToClientWeekdaysAgo(4, ['in_workspace' => false]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(['Follow Up Cliente #1'], RelatedTask::where('order_id', $order->id)->pluck('title')->all());
    }

    // ---- Step 2: TO DO TODAY not done -> designer queue ---------------------------------------------

    public function test_uncompleted_working_today_order_returns_to_its_designer_queue(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => false]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
        $event = $this->eventsOf($order, 'AUTO_REVERTED_TO_DESIGNER_LIST')->sole();
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $event->previous_value);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED->value, $event->new_value);
        $this->assertSame('End of day automatic reversion for uncompleted order', $event->metadata['reason']);
    }

    /** @return array<string, array{string, CoreStatus}> */
    public static function designerQueues(): array
    {
        return [
            'adrian' => ['adrian', CoreStatus::ADRIAN_ORDERS_RECEIVED],
            'euraliz' => ['euraliz', CoreStatus::EURALIZ_ORDERS_RECEIVED],
            'cesar' => ['cesar', CoreStatus::CESAR_ORDERS_RECEIVED],
        ];
    }

    #[DataProvider('designerQueues')]
    public function test_revert_goes_to_the_queue_of_the_assigned_designer(string $designer, CoreStatus $expected): void
    {
        $order = $this->makeOrder(['designer_id' => $this->{$designer}->id, 'done_today' => false]);

        $this->engine()->runDailyAutomations();

        $this->assertSame($expected, $order->fresh()->core_status);
    }

    public function test_completed_working_today_order_is_not_reverted(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertSame(0, $this->eventsOf($order, 'AUTO_REVERTED_TO_DESIGNER_LIST')->count());
    }

    public function test_revert_applies_even_outside_the_workspace(): void
    {
        $order = $this->makeOrder(['in_workspace' => false, 'done_today' => false]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
    }

    public function test_revert_drops_a_substatus_that_is_not_valid_in_a_designer_queue(): void
    {
        $order = $this->makeOrder(['substatus' => Substatus::CAMBIOS_CLIENTE, 'done_today' => false]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_revert_keeps_a_substatus_that_is_valid_in_a_designer_queue(): void
    {
        $order = $this->makeOrder(['substatus' => Substatus::PONER_EN_ALTA, 'done_today' => false]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
    }

    public function test_order_scheduled_for_today_is_reverted_and_promoted_again_in_the_same_run(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => false,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertSame(1, $this->eventsOf($order, 'AUTO_REVERTED_TO_DESIGNER_LIST')->count());
        $this->assertSame(1, $this->eventsOf($order, 'SCHEDULED_TODAY_PROMOTED')->count());
    }

    // ---- Step 3: scheduled_date <= today -> TO DO TODAY ---------------------------------------------

    /** @return array<string, array{CoreStatus}> */
    public static function promotableStatuses(): array
    {
        return [
            'adrian queue' => [CoreStatus::ADRIAN_ORDERS_RECEIVED],
            'euraliz queue' => [CoreStatus::EURALIZ_ORDERS_RECEIVED],
            'cesar queue' => [CoreStatus::CESAR_ORDERS_RECEIVED],
            'entrante' => [CoreStatus::ENTRANTE],
        ];
    }

    #[DataProvider('promotableStatuses')]
    public function test_order_scheduled_for_today_is_promoted_to_working_today(CoreStatus $from): void
    {
        $order = $this->makeOrder(['core_status' => $from, 'scheduled_date' => now()->toDateString()]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $event = $this->eventsOf($order, 'SCHEDULED_TODAY_PROMOTED')->sole();
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $event->new_value);
        $this->assertSame('Scheduled date reached', $event->metadata['reason']);
    }

    public function test_order_scheduled_in_the_past_is_promoted_too(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'scheduled_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    /** @return array<string, array{CoreStatus, ?string, bool}> */
    public static function notPromotedCases(): array
    {
        return [
            'scheduled tomorrow' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, '+1 day', true],
            'no scheduled date' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, null, true],
            'on hold' => [CoreStatus::ON_HOLD, 'today', true],
            'sent to camila' => [CoreStatus::ENVIADO_A_CAMILA, 'today', true],
            'sent to client' => [CoreStatus::ENVIADO_AL_CLIENTE, 'today', true],
            'in production' => [CoreStatus::EN_PRODUCCION, 'today', true],
            'archived' => [CoreStatus::ARCHIVED, 'today', true],
            'outside workspace' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, 'today', false],
        ];
    }

    #[DataProvider('notPromotedCases')]
    public function test_order_is_not_promoted(CoreStatus $status, ?string $scheduled, bool $inWorkspace): void
    {
        $order = $this->makeOrder([
            'core_status' => $status,
            'scheduled_date' => $scheduled ? now()->modify($scheduled)->toDateString() : null,
            'in_workspace' => $inWorkspace,
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame($status, $order->fresh()->core_status);
        $this->assertSame(0, $this->eventsOf($order, 'SCHEDULED_TODAY_PROMOTED')->count());
    }

    public function test_promotion_keeps_a_substatus_valid_for_working_today(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'substatus' => Substatus::PONER_EN_ALTA,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
    }

    public function test_promoting_a_blocked_order_clears_its_block_through_the_action_required_resolver(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
            'customer_service_required' => true,
            'scheduled_date' => now()->toDateString(),
        ]);
        $this->makeTask($order, ['title' => 'RESOLVER: medidas', 'type' => RelatedTaskType::RESOLVER]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertNull($order->blocking_reason);
        $this->assertFalse($order->customer_service_required);
        $this->assertSame(1, $this->eventsOf($order, 'ORDER_UNBLOCKED')->count());
        $this->assertSame('done', RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::RESOLVER)->first()->status);
    }

    public function test_promoting_an_order_with_only_a_blocked_substatus_drops_the_substatus(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertSame(0, $this->eventsOf($order, 'ORDER_UNBLOCKED')->count());
    }

    // ---- Step 4: PONER EN ALTA + done today -> EN PRODUCCION ---------------------------------------

    public function test_done_working_today_order_with_poner_en_alta_moves_to_production(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => true,
            'approved' => true,
        ]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertFalse($order->done_today);

        $event = $this->eventsOf($order, 'MOVED_TO_PRODUCTION')->sole();
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $event->previous_value);
        $this->assertSame(CoreStatus::EN_PRODUCCION->value, $event->new_value);
        $this->assertSame('ALTA completed', $event->metadata['trigger']);
    }

    public function test_poner_en_alta_order_not_done_today_is_reverted_instead_of_moved_to_production(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => false,
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
        $this->assertSame(0, $this->eventsOf($order, 'MOVED_TO_PRODUCTION')->count());
    }

    public function test_done_working_today_order_without_poner_en_alta_stays_in_working_today(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
            'done_today' => true,
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_production_move_does_not_run_status_change_side_effects(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => true,
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(0, $this->eventsOf($order, 'CORE_STATUS_CHANGED')->count());
    }

    // ---- Step 5: completed "enviar proof al cliente" -> ENVIADO AL CLIENTE --------------------------

    public function test_done_working_today_order_with_completed_proof_task_moves_to_client(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]);
        $this->makeTask($order, [
            'title' => 'Enviar proof al cliente',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $this->engine()->runDailyAutomations();

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
        $this->assertTrue($order->last_sent_to_client_at->isToday());
        $this->assertNull($order->client_last_response);

        $event = $this->eventsOf($order, 'MOVED_TO_CLIENT_CAMILA_PREAPPROVED')->sole();
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $event->previous_value);
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE->value, $event->new_value);
        $this->assertSame('Send proof to client completed', $event->metadata['trigger']);

        $this->assertSame(1, $this->eventsOf($order, 'CORE_STATUS_CHANGED')->count());
        $this->assertSame(1, $this->eventsOf($order, 'ORDER_SENT_TO_CLIENT')->count());
    }

    public function test_proof_task_title_match_ignores_case(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]);
        $this->makeTask($order, ['title' => 'ENVIAR PROOF AL CLIENTE (rev 2)', 'status' => 'done', 'completed_at' => now()]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    public function test_pending_proof_task_does_not_move_the_order_to_client(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]);
        $this->makeTask($order, ['title' => 'Enviar proof al cliente', 'status' => 'todo']);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_completed_proof_task_on_an_order_not_done_today_is_reverted_not_sent(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => false]);
        $this->makeTask($order, ['title' => 'Enviar proof al cliente', 'status' => 'done', 'completed_at' => now()]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
    }

    // ---- Step 6: due today / overdue notifications and the delay-email task ------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationsFor(User $user, string $eventType, Order $order): array
    {
        return $user->notifications()->get()
            ->map(fn ($n) => $n->data)
            ->filter(fn ($d) => $d['event_type'] === $eventType && $d['order_id'] === $order->id)
            ->values()
            ->all();
    }

    public function test_order_due_today_notifies_managers_without_creating_a_delay_task(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $notes = $this->notificationsFor($admin, 'order_due_today', $order);
        $this->assertCount(1, $notes);
        $this->assertSame('Due Today', $notes[0]['label']);
        $this->assertFalse($notes[0]['is_urgent']);
        $this->assertSame([], $this->notificationsFor($admin, 'order_overdue', $order));
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_overdue_order_notifies_urgently_and_creates_the_delay_email_task(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $notes = $this->notificationsFor($admin, 'order_overdue', $order);
        $this->assertCount(1, $notes);
        $this->assertSame('Overdue', $notes[0]['label']);
        $this->assertTrue($notes[0]['is_urgent']);
        $this->assertSame([], $this->notificationsFor($admin, 'order_due_today', $order));

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('Enviar correo de atraso preventivo', $task->title);
        $this->assertSame(RelatedTaskType::CORREO_ATRASO, $task->type);
        $this->assertSame('AUTOMATIC_OVERDUE_DETECTION', $task->trigger_type);
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
    }

    public function test_delay_email_task_is_not_duplicated_on_second_run(): void
    {
        User::factory()->create();
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();
        $this->engine()->runDailyAutomations();

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::CORREO_ATRASO)->count());
    }

    public function test_job_run_after_four_pm_treats_orders_due_today_as_overdue(): void
    {
        $admin = User::factory()->create();
        $this->freezeAt('2026-09-30 16:00:00');
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertCount(1, $this->notificationsFor($admin, 'order_overdue', $order));
        $this->assertCount(1, $this->notificationsFor($admin, 'order_due_today', $order));
        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::CORREO_ATRASO)->count());
    }

    public function test_job_run_at_two_thirty_does_not_create_delay_task_for_orders_only_due_today(): void
    {
        $this->freezeAt('2026-09-30 14:30:00');
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function ordersWithoutDueNotifications(): array
    {
        return [
            'done today' => [['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]],
            'sent to client' => [['core_status' => CoreStatus::ENVIADO_AL_CLIENTE]],
            'in production' => [['core_status' => CoreStatus::EN_PRODUCCION]],
            'on hold' => [['core_status' => CoreStatus::ON_HOLD]],
            'archived' => [['core_status' => CoreStatus::ARCHIVED]],
            'paused substatus' => [['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'substatus' => Substatus::PAUSADO]],
            'outside workspace' => [['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'in_workspace' => false]],
        ];
    }

    #[DataProvider('ordersWithoutDueNotifications')]
    public function test_exempt_orders_get_no_due_or_overdue_notifications_or_delay_task(array $attributes): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(array_merge($attributes, ['current_due_date' => now()->subDays(2)->toDateString()]));
        $dueToday = $this->makeOrder(array_merge($attributes, ['current_due_date' => now()->toDateString()]));

        $this->engine()->runDailyAutomations();

        foreach ([$order, $dueToday] as $candidate) {
            $this->assertSame([], $this->notificationsFor($admin, 'order_overdue', $candidate));
            $this->assertSame([], $this->notificationsFor($admin, 'order_due_today', $candidate));
            $this->assertSame(0, RelatedTask::where('order_id', $candidate->id)->where('type', RelatedTaskType::CORREO_ATRASO)->count());
        }
    }

    public function test_future_due_date_is_not_notified(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame([], $this->notificationsFor($admin, 'order_overdue', $order));
        $this->assertSame([], $this->notificationsFor($admin, 'order_due_today', $order));
    }
}
