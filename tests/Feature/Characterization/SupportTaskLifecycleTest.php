<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\User;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterizes the current RelatedTask lifecycle behavior (hooks, observer, auto-done engine).
 */
class SupportTaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'company_name' => 'LIFECYCLE CO',
            'task_name' => 'Banner',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ], $attributes));
    }

    private function makeTask(Order $order, array $attributes = []): RelatedTask
    {
        return RelatedTask::create(array_merge([
            'order_id' => $order->id,
            'title' => 'Ajuste de diseno',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'is_work_task' => true,
            'scheduled_date' => today()->toDateString(),
        ], $attributes));
    }

    public function test_creating_a_task_flips_a_backlog_order_into_the_workspace(): void
    {
        $order = $this->makeOrder(['in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE]);

        $this->makeTask($order, ['scheduled_date' => today()->addDays(3)->toDateString()]);

        $this->assertTrue($order->fresh()->in_workspace);
    }

    public function test_creating_a_task_logs_an_automatic_task_triggered_event(): void
    {
        $order = $this->makeOrder();

        $task = $this->makeTask($order, ['trigger_type' => 'NEW_ORDER_CREATED', 'priority' => 'high']);

        $event = OrderEvent::where('order_id', $order->id)->where('event_type', 'AUTOMATIC_TASK_TRIGGERED')->first();
        $this->assertNotNull($event);
        $this->assertSame('AutomationEngine', $event->actor);
        $this->assertSame($task->title, $event->new_value);
        $this->assertSame($task->id, $event->metadata['task_id']);
        $this->assertSame('NEW_ORDER_CREATED', $event->metadata['trigger_type']);
        $this->assertSame('high', $event->metadata['priority']);
    }

    public function test_manual_task_event_actor_is_sistema_when_nobody_is_logged_in_and_defaults_apply(): void
    {
        $order = $this->makeOrder();

        $this->makeTask($order);

        $event = OrderEvent::where('order_id', $order->id)->where('event_type', 'AUTOMATIC_TASK_TRIGGERED')->first();
        $this->assertSame('System', $event->actor);
        $this->assertSame('SYSTEM_AUTOMATION', $event->metadata['trigger_type']);
        $this->assertSame('normal', $event->metadata['priority']);
    }

    public function test_note_tasks_without_an_order_do_not_log_events(): void
    {
        RelatedTask::create(['title' => 'Nota suelta', 'type' => RelatedTaskType::SUBTASK, 'status' => 'todo']);

        $this->assertSame(0, OrderEvent::where('event_type', 'AUTOMATIC_TASK_TRIGGERED')->count());
    }

    public function test_observer_sets_completed_at_when_status_done_and_clears_it_on_reopen(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => today()->addDays(2)->toDateString()]);

        $task->update(['status' => 'done']);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('event_type', 'SUBTASK_COMPLETED')->count());

        $task->update(['status' => 'todo']);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame(0, OrderEvent::where('order_id', $order->id)->where('event_type', 'SUBTASK_COMPLETED')->count());
    }

    public function test_observer_forces_follow_up_tasks_to_management_without_return_status(): void
    {
        $order = $this->makeOrder();

        $task = $this->makeTask($order, [
            'title' => 'Follow up con cliente',
            'category' => SubtaskCategory::CLIENT_ADJUSTMENTS,
            'return_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(SubtaskCategory::MANAGEMENT, $fresh->category);
        $this->assertNull($fresh->return_core_status);
        $this->assertFalse($fresh->is_work_task);
    }

    public function test_completing_a_welcome_task_dispatches_welcome_email_notification(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder();
        $task = $this->makeTask($order, ['type' => RelatedTaskType::BIENVENIDA, 'title' => 'Enviar correo bienvenida']);

        $task->update(['status' => 'done']);

        $this->assertSame(1, $admin->notifications()->where('data->event_type', 'welcome_email_sent')->count());
    }

    public function test_is_follow_up_detection(): void
    {
        $this->assertTrue((new RelatedTask(['type' => RelatedTaskType::FOLLOW_UP_CLIENTE, 'title' => 'x']))->isFollowUp());
        $this->assertTrue((new RelatedTask(['type' => RelatedTaskType::FOLLOW_UP_ALTA, 'title' => 'x']))->isFollowUp());
        $this->assertTrue((new RelatedTask(['title' => 'x', 'trigger_type' => 'CLIENT_FOLLOW_UP_CYCLE']))->isFollowUp());
        $this->assertTrue((new RelatedTask(['title' => 'Hacer FollowUp']))->isFollowUp());
        $this->assertTrue((new RelatedTask(['title' => 'Llamar al cliente']))->isFollowUp());
        $this->assertFalse((new RelatedTask(['title' => 'Ajuste de logo']))->isFollowUp());
    }

    public function test_is_poner_en_alta_detection(): void
    {
        $this->assertTrue((new RelatedTask(['type' => RelatedTaskType::PONER_ALTA, 'title' => 'x']))->isPonerEnAlta());
        $this->assertTrue((new RelatedTask(['title' => 'Poner en ALTA', 'category' => SubtaskCategory::NEW_DESIGN]))->isPonerEnAlta());
        $this->assertTrue((new RelatedTask(['title' => 'x', 'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS]))->isPonerEnAlta());
        $this->assertTrue((new RelatedTask(['title' => 'x', 'category' => SubtaskCategory::NEW_DESIGN, 'return_core_status' => CoreStatus::EN_PRODUCCION]))->isPonerEnAlta());
        $this->assertFalse((new RelatedTask(['title' => 'Ajuste de color', 'category' => SubtaskCategory::NEW_DESIGN]))->isPonerEnAlta());
        $this->assertFalse((new RelatedTask(['title' => 'Follow up alta', 'category' => SubtaskCategory::NEW_DESIGN]))->isPonerEnAlta());
        $this->assertFalse((new RelatedTask(['title' => 'Poner en alta', 'category' => SubtaskCategory::MANAGEMENT]))->isPonerEnAlta());
    }

    public function test_subtask_category_detect_from_context_by_title_keywords(): void
    {
        $this->assertSame(SubtaskCategory::MANAGEMENT, SubtaskCategory::detectFromContext('Follow up cliente'));
        $this->assertSame(SubtaskCategory::MANAGEMENT, SubtaskCategory::detectFromContext('Llamar'));
        $this->assertSame(SubtaskCategory::MANAGEMENT, SubtaskCategory::detectFromContext('Confirmar medidas'));
        $this->assertSame(SubtaskCategory::MANAGEMENT, SubtaskCategory::detectFromContext('Solicitar logo'));
        $this->assertSame(SubtaskCategory::CLIENT_ADJUSTMENTS, SubtaskCategory::detectFromContext('Ajustes cliente'));
        $this->assertSame(SubtaskCategory::CLIENT_ADJUSTMENTS, SubtaskCategory::detectFromContext('Enviar proof'));
        $this->assertSame(SubtaskCategory::CAMILA_ADJUSTMENTS, SubtaskCategory::detectFromContext('Revisar con Camila'));
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, SubtaskCategory::detectFromContext('Poner en alta'));
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, SubtaskCategory::detectFromContext('Ajustes de producción'));
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, SubtaskCategory::detectFromContext('Llevar al taller'));
        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('Nuevo diseno'));
    }

    public function test_subtask_category_detect_uses_word_boundaries(): void
    {
        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('Trabajar en Camilas'));
        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('Revisar clientela'));
        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('Salta'));
    }

    public function test_subtask_category_detect_falls_back_to_order_status_context(): void
    {
        $this->assertSame(SubtaskCategory::CLIENT_ADJUSTMENTS, SubtaskCategory::detectFromContext('x', new Order(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE])));
        $this->assertSame(SubtaskCategory::CAMILA_ADJUSTMENTS, SubtaskCategory::detectFromContext('x', new Order(['core_status' => CoreStatus::ENVIADO_A_CAMILA])));
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, SubtaskCategory::detectFromContext('x', new Order(['core_status' => CoreStatus::EN_PRODUCCION])));
        $this->assertSame(SubtaskCategory::CLIENT_ADJUSTMENTS, SubtaskCategory::detectFromContext('x', new Order([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
        ])));
        $this->assertSame(SubtaskCategory::CAMILA_ADJUSTMENTS, SubtaskCategory::detectFromContext('x', new Order([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CAMILA,
        ])));
        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('x', new Order(['core_status' => CoreStatus::TO_DO_TODAY])));
    }

    public function test_origin_core_status_alone_does_not_drive_category_detection_because_it_is_an_enum_cast(): void
    {
        $order = new Order([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $this->assertSame(SubtaskCategory::NEW_DESIGN, SubtaskCategory::detectFromContext('x', $order));
    }

    public function test_task_without_stored_category_derives_it_from_title_and_order(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA]);
        $task = new RelatedTask(['title' => 'Cambios varios', 'order_id' => $order->id]);

        $this->assertSame(SubtaskCategory::CAMILA_ADJUSTMENTS, $task->category);
    }

    public function test_work_task_definition_excludes_follow_ups_system_and_management_tasks(): void
    {
        $this->assertTrue((new RelatedTask(['title' => 'Ajuste', 'is_work_task' => true, 'category' => SubtaskCategory::NEW_DESIGN]))->isWorkTask());
        $this->assertFalse((new RelatedTask(['title' => 'Ajuste', 'is_work_task' => true, 'category' => SubtaskCategory::MANAGEMENT]))->isWorkTask());
        $this->assertFalse((new RelatedTask(['title' => 'Ajuste', 'is_work_task' => true, 'category' => SubtaskCategory::NEW_DESIGN, 'trigger_type' => 'NEW_ORDER_CREATED']))->isWorkTask());
        $this->assertFalse((new RelatedTask(['title' => 'Ajuste', 'is_work_task' => false, 'category' => SubtaskCategory::NEW_DESIGN]))->isWorkTask());
        $this->assertFalse((new RelatedTask(['title' => 'Follow up', 'is_work_task' => true]))->isWorkTask());
    }

    public function test_completing_all_today_work_tasks_of_a_sent_to_client_order_auto_returns_it(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
        ]);

        $task = $this->makeTask($order, [
            'category' => SubtaskCategory::CLIENT_ADJUSTMENTS,
            'return_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->origin_core_status);
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);
        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('event_type', 'REOPENED_TO_WORKING_TODAY')->count());

        $task->update(['status' => 'done']);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertNull($order->origin_core_status);
        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('event_type', 'AUTO_RETURNED_AFTER_SUBTASK_COMPLETION')->count());
    }

    public function test_reopen_from_camila_moves_order_to_to_do_today_with_camila_substatus(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA, 'substatus' => Substatus::CAMBIOS_CAMILA]);

        $this->makeTask($order, ['category' => SubtaskCategory::CAMILA_ADJUSTMENTS]);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->origin_core_status);
        $this->assertSame(Substatus::CAMBIOS_CAMILA, $order->substatus);
        $this->assertFalse((bool) $order->done_today);
    }

    public function test_reopen_from_client_bumps_client_revision_count(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::WAITING_FOR_CLIENT]);

        $this->makeTask($order, ['category' => SubtaskCategory::CLIENT_ADJUSTMENTS]);

        $this->assertGreaterThanOrEqual(1, (int) $order->fresh()->client_revision_count);
    }

    public function test_future_dated_tasks_do_not_reopen_a_sent_order(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::WAITING_FOR_CLIENT]);

        $this->makeTask($order, ['scheduled_date' => today()->addDays(4)->toDateString()]);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertTrue((bool) $order->done_today);
    }

    public function test_management_tasks_do_not_count_as_today_work(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::WAITING_FOR_CLIENT]);

        $this->makeTask($order, ['title' => 'Follow up cliente', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE]);

        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    public function test_return_target_prefers_explicit_return_core_status(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE]);

        $task = $this->makeTask($order, [
            'category' => SubtaskCategory::NEW_DESIGN,
            'return_core_status' => CoreStatus::ENVIADO_A_CAMILA,
        ]);
        $task->update(['status' => 'done']);

        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->fresh()->core_status);
    }

    public function test_return_target_uses_alta_when_approved_and_skips_return_when_not_approved(): void
    {
        $notApproved = $this->makeOrder(['approved' => false]);
        $this->makeTask($notApproved, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA, 'category' => SubtaskCategory::NEW_DESIGN])
            ->update(['status' => 'done']);

        $notApproved->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $notApproved->core_status);
        $this->assertTrue((bool) $notApproved->done_today);

        $approved = $this->makeOrder(['approved' => true, 'wo_number' => 'WO 20001']);
        $this->makeTask($approved, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA, 'category' => SubtaskCategory::NEW_DESIGN])
            ->update(['status' => 'done']);

        $approved->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $approved->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $approved->substatus);
    }

    public function test_return_target_falls_back_to_category_default(): void
    {
        $order = $this->makeOrder();

        $this->makeTask($order, ['category' => SubtaskCategory::CAMILA_ADJUSTMENTS])->update(['status' => 'done']);

        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->fresh()->core_status);
    }

    public function test_return_target_falls_back_to_origin_when_task_has_no_destination(): void
    {
        $order = $this->makeOrder(['origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'origin_substatus' => Substatus::WAITING_FOR_CLIENT]);

        $this->makeTask($order, ['category' => SubtaskCategory::NEW_DESIGN])->update(['status' => 'done']);

        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    public function test_order_without_return_target_stays_in_to_do_today_marked_done(): void
    {
        $order = $this->makeOrder();

        $this->makeTask($order, ['category' => SubtaskCategory::NEW_DESIGN])->update(['status' => 'done']);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertTrue((bool) $order->done_today);
    }

    public function test_pending_future_work_routes_order_back_to_designer_queue(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = $this->makeOrder(['designer_id' => $designer->id]);

        $todayTask = $this->makeTask($order, ['category' => SubtaskCategory::NEW_DESIGN]);
        $this->makeTask($order, ['title' => 'Futura', 'category' => SubtaskCategory::NEW_DESIGN, 'scheduled_date' => today()->addDays(3)->toDateString()]);

        $todayTask->update(['status' => 'done']);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertTrue((bool) $order->done_today);
        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('event_type', 'ROUTED_TO_DESIGNER_FUTURE_SUBTASKS')->count());
    }

    public function test_urgent_order_is_not_routed_back_to_designer_queue(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = $this->makeOrder(['designer_id' => $designer->id, 'substatus' => Substatus::URGENTE]);

        $todayTask = $this->makeTask($order, ['category' => SubtaskCategory::NEW_DESIGN]);
        $this->makeTask($order, ['title' => 'Futura', 'category' => SubtaskCategory::NEW_DESIGN, 'scheduled_date' => today()->addDays(3)->toDateString()]);
        $todayTask->update(['status' => 'done']);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_rescheduling_all_today_work_to_the_future_routes_order_to_origin_or_designer_queue(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = $this->makeOrder(['designer_id' => $designer->id]);

        $task = $this->makeTask($order, ['category' => SubtaskCategory::NEW_DESIGN]);
        $task->update(['scheduled_date' => today()->addDays(2)->toDateString()]);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertFalse((bool) $order->done_today);
    }

    public function test_orders_in_designer_queue_track_done_today_without_moving(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        $task = $this->makeTask($order);
        $this->assertFalse((bool) $order->fresh()->done_today);

        $task->update(['status' => 'done']);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertTrue((bool) $order->done_today);
    }

    public function test_dismiss_triggered_subtasks_force_deletes_pending_system_tasks_only(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $future = today()->addDays(5)->toDateString();

        $welcome = $this->makeTask($order, ['type' => RelatedTaskType::BIENVENIDA, 'trigger_type' => 'NEW_ORDER_CREATED', 'scheduled_date' => $future]);
        $resolver = $this->makeTask($order, ['type' => RelatedTaskType::RESOLVER, 'scheduled_date' => $future]);
        $doneSystem = $this->makeTask($order, ['type' => RelatedTaskType::CORREO_ATRASO, 'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION', 'status' => 'done', 'scheduled_date' => $future]);
        $manual = $this->makeTask($order, ['scheduled_date' => $future]);
        $kept = $this->makeTask($order, ['type' => RelatedTaskType::FOLLOW_UP_CLIENTE, 'scheduled_date' => $future]);

        app(AutomationEngine::class)->dismissTriggeredSubtasks($order, [RelatedTaskType::FOLLOW_UP_CLIENTE]);

        $this->assertDatabaseMissing('related_tasks', ['id' => $welcome->id]);
        $this->assertDatabaseMissing('related_tasks', ['id' => $resolver->id]);
        $this->assertNull(RelatedTask::withTrashed()->find($welcome->id));
        $this->assertNotNull(RelatedTask::find($doneSystem->id));
        $this->assertNotNull(RelatedTask::find($manual->id));
        $this->assertNotNull(RelatedTask::find($kept->id));
    }

    public function test_welcome_email_is_scheduled_for_the_same_day_before_the_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-04 10:00:00')); // Wednesday

        $date = app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(now());

        $this->assertSame('2026-03-04', $date->toDateString());
    }

    public function test_welcome_email_moves_to_next_business_day_at_or_after_16_30(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-04 16:29:00'));
        $this->assertSame('2026-03-04', app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(now())->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-03-04 16:30:00'));
        $this->assertSame('2026-03-05', app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(now())->toDateString());
    }

    public function test_welcome_email_on_friday_after_cutoff_and_on_weekend_goes_to_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-06 17:00:00')); // Friday
        $this->assertSame('2026-03-09', app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(now())->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-03-07 09:00:00')); // Saturday
        $this->assertSame('2026-03-09', app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(now())->toDateString());
    }

    public function test_welcome_email_for_past_entry_is_evaluated_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-04 10:00:00'));

        $date = app(AutomationEngine::class)->calculateWelcomeEmailScheduledDate(Carbon::parse('2026-03-01 12:00:00'));

        $this->assertSame('2026-03-04', $date->toDateString());
    }

    public function test_order_created_schedules_welcome_task_for_recent_cards_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-04 10:00:00'));

        $recent = $this->makeOrder(['trello_created_at' => now()->subDays(6)]);
        app(AutomationEngine::class)->handleOrderCreated($recent);
        $this->assertSame(1, RelatedTask::where('order_id', $recent->id)->where('type', RelatedTaskType::BIENVENIDA->value)->count());

        $old = $this->makeOrder(['trello_created_at' => now()->subDays(8), 'wo_number' => 'WO 20002']);
        app(AutomationEngine::class)->handleOrderCreated($old);
        $this->assertSame(0, RelatedTask::where('order_id', $old->id)->count());
    }

    public function test_order_created_sets_start_and_due_dates_and_logs_event(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE]);

        app(AutomationEngine::class)->handleOrderCreated($order, 'trello', 'Trello');

        $order->refresh();
        $this->assertSame(now()->toDateString(), $order->start_date->toDateString());
        $this->assertNotNull($order->original_due_date);
        $this->assertSame(1, OrderEvent::where('order_id', $order->id)->where('event_type', 'ORDER_CREATED')->where('actor', 'Trello')->count());
    }
}
