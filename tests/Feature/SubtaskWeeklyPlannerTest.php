<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Orders\OrderDetailModal;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\SubtaskPreset;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SubtaskWeeklyPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_schedule_preset_and_custom_subtasks_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);

        $order = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'task_name' => 'Menu Board',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Ajustes Camila', $todayStr, $designer->id)
            ->call('scheduleSubtask', $order->id, 'Modificar fonts', $todayStr, $designer->id);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Ajustes Camila',
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Modificar fonts',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_SCHEDULED',
            'new_value' => 'Modificar fonts',
        ]);
    }

    public function test_completing_subtask_logs_subtask_completed_timeline_event(): void
    {
        $order = Order::create([
            'company_name' => 'GLOSSY SIGNS',
            'task_name' => 'Acrylic Sign',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisiones cliente',
            'type' => RelatedTaskType::SUBTASK->value,
            'scheduled_date' => now()->toDateString(),
            'status' => 'todo',
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $subtask->id);

        $this->assertEquals('done', $subtask->fresh()->status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_COMPLETED',
            'new_value' => 'Revisiones cliente',
        ]);
    }

    public function test_subtasks_scheduled_for_today_appear_in_dashboard_working_today(): void
    {
        $order = Order::create([
            'company_name' => 'KUDOS MEDIA',
            'task_name' => 'Wall Graphics',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        $subtaskToday = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Confirmar medidas',
            'type' => RelatedTaskType::SUBTASK->value,
            'scheduled_date' => now()->toDateString(),
            'status' => 'todo',
        ]);

        Livewire::test(DashboardIndex::class)
            ->assertViewHas('toDoTodayTasks', fn ($tasks) => $tasks->pluck('id')->contains($subtaskToday->id));
    }

    public function test_subtask_scheduling_does_not_change_parent_order_scheduled_date(): void
    {
        $this->travelTo(Carbon::parse('2026-08-24'));
        $designer = Designer::create(['name' => 'Camila', 'active' => true]);
        $mondayStr = now()->startOfWeek()->toDateString();
        $wednesdayStr = now()->startOfWeek()->addDays(2)->toDateString();

        $order = Order::create([
            'company_name' => 'BURGER KING',
            'task_name' => 'Flyer Promo',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'scheduled_date' => $mondayStr,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Revision cliente', $wednesdayStr, $designer->id);

        $this->assertEquals($mondayStr, $order->fresh()->scheduled_date->toDateString());

        $subtask = RelatedTask::where('order_id', $order->id)->where('title', 'Revision cliente')->firstOrFail();
        $this->assertEquals($wednesdayStr, $subtask->scheduled_date->toDateString());
    }

    public function test_can_reschedule_subtask_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);
        $order = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'task_name' => 'Menu Board',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajustes finales',
            'type' => RelatedTaskType::SUBTASK->value,
            'scheduled_date' => now()->toDateString(),
            'assignee_id' => $designer->id,
            'status' => 'todo',
        ]);

        $newDateStr = now()->addDays(2)->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->call('rescheduleSubtask', $subtask->id, $newDateStr);

        $this->assertEquals($newDateStr, $subtask->fresh()->scheduled_date->toDateString());
    }

    public function test_can_search_backlog_orders_in_weekly_planner(): void
    {
        $backlogOrder = Order::create([
            'company_name' => 'TACOS AL PASTOR',
            'task_name' => 'Luminoso exterior',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => false,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->set('backlogSearch', 'TACOS')
            ->assertViewHas('backlogOrders', fn ($orders) => $orders->pluck('id')->contains($backlogOrder->id));
    }

    public function test_subtask_creation_for_past_date_leaves_order_status_and_schedule_alone(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $dueDateStr = now()->addDays(5)->toDateString();
        $pastDateStr = now()->subWeek()->toDateString();

        $order = Order::create([
            'company_name' => 'LOGGING PAST WORK CO',
            'task_name' => 'Banner Sign',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'scheduled_date' => null,
            'original_due_date' => $dueDateStr,
            'current_due_date' => $dueDateStr,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Diseno previo', $pastDateStr, $designer->id);

        $order->refresh();

        // Core status and order main scheduled_date must be completely untouched
        $this->assertEquals(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertNull($order->scheduled_date);
        $this->assertEquals($dueDateStr, $order->current_due_date->toDateString());

        // Subtask is created under the past date
        $subtask = RelatedTask::where('order_id', $order->id)->where('title', 'Diseno previo')->firstOrFail();
        $this->assertEquals($pastDateStr, $subtask->scheduled_date->toDateString());
    }

    public function test_subtask_creation_never_modifies_order_due_date(): void
    {
        $designer = Designer::create(['name' => 'César', 'active' => true]);
        $dueDateStr = now()->addDays(2)->toDateString();
        $futurePastDueStr = now()->addDays(10)->toDateString();

        $order = Order::create([
            'company_name' => 'LATE SUBTASK CLIENT',
            'task_name' => 'Vinyl Print',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'original_due_date' => $dueDateStr,
            'current_due_date' => $dueDateStr,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Instalacion tardia', $futurePastDueStr, $designer->id);

        $order->refresh();

        // Due date remains locked
        $this->assertEquals($dueDateStr, $order->current_due_date->toDateString());
    }

    public function test_past_uncompleted_subtasks_roll_over_to_current_week_monday(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = Order::create([
            'company_name' => 'PORKYS REAL MEXICAN FOOD',
            'task_name' => 'Revisiones cliente',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $pastDate = now()->startOfWeek()->subDays(5)->toDateString(); // Last week's date

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Revisiones cliente',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => $pastDate,
            'assignee_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertSeeHtml($subtask->title);
    }

    public function test_can_toggle_view_mode_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Carlos', 'active' => true]);

        $order = Order::create([
            'company_name' => 'RESTAURANTE EL TACO',
            'task_name' => 'Menú digital',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajuste de colores',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => now()->startOfWeek()->toDateString(),
            'assignee_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertSet('viewMode', 'by_designer')
            ->assertSeeHtml(__('Por Días'))
            ->assertSeeHtml(__('Por Diseñador'))
            ->assertSeeHtml('RESTAURANTE EL TACO')
            ->assertSeeHtml('Ajuste de colores')
            ->call('changeViewMode', 'by_day')
            ->assertSet('viewMode', 'by_day')
            ->assertSessionHas('weekly_planner_view_mode', 'by_day');

        // Verify next component mount initializes with persisted viewMode
        Livewire::test(WeeklyPlanner::class)
            ->assertSet('viewMode', 'by_day');
    }

    public function test_working_subtask_promotes_order_to_working_today(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = Order::create([
            'company_name' => 'TAQUERIA LA WORK',
            'task_name' => 'Logo Design',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Ajustes Camila', now()->toDateString(), $designer->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(now()->toDateString(), $order->scheduled_date->toDateString());
    }

    public function test_managing_subtask_does_not_promote_order_to_working_today(): void
    {
        $designer = Designer::create(['name' => 'César', 'active' => true]);
        SubtaskPreset::create([
            'title' => 'Confirmar medidas',
            'is_work_task' => false,
        ]);
        $order = Order::create([
            'company_name' => 'TAQUERIA LA GESTION',
            'task_name' => 'Flyer Print',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Confirmar medidas', now()->toDateString(), $designer->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::CESAR_ORDERS_RECEIVED, $order->core_status);
    }

    public function test_archived_order_scheduled_with_working_subtask_is_unarchived_and_tagged_as_ticket(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $order = Order::create([
            'company_name' => 'ARCHIVED TICKET BIZ',
            'task_name' => 'Signage Repair',
            'core_status' => CoreStatus::ARCHIVED,
            'in_workspace' => false,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Ajustes Camila', now()->toDateString(), $designer->id);

        $order->refresh();
        $this->assertTrue($order->in_workspace);
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(Substatus::TICKET, $order->substatus);
    }

    public function test_en_produccion_order_retains_core_status_when_scheduled_with_working_subtask(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = Order::create([
            'company_name' => 'PRODUCCION BIZ',
            'task_name' => 'Banner Print',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Nueva propuesta', now()->toDateString(), $designer->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $order->core_status);
    }

    public function test_order_detail_modal_add_task_with_calendar_date_and_working_type(): void
    {
        $designer = Designer::create(['name' => 'César', 'active' => true]);
        $order = Order::create([
            'company_name' => 'DETAIL MODAL BIZ',
            'task_name' => 'Decal Print',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(OrderDetailModal::class, ['orderId' => $order->id])
            ->set('newTaskTitle', 'Diseno de prototipo')
            ->set('newTaskDate', now()->toDateString())
            ->set('newTaskIsWork', true)
            ->call('addTask');

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Diseno de prototipo',
            'is_work_task' => true,
        ]);
    }

    public function test_weekly_planner_passes_active_subtask_presets_to_view(): void
    {
        SubtaskPreset::create([
            'title' => 'Arte Final',
            'emoji' => 'check-circle',
            'color_theme' => 'emerald',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SubtaskPreset::create([
            'title' => 'Cambios Cliente',
            'emoji' => 'message-square',
            'color_theme' => 'amber',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertViewHas('subtaskPresets', function ($presets) {
                return $presets->pluck('title')->contains('Arte Final')
                    && $presets->pluck('title')->contains('Cambios Cliente');
            });
    }

    public function test_can_delete_and_undo_subtask_deletion_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);
        $order = Order::create([
            'company_name' => 'TAQUERIA UNDO TEST',
            'task_name' => 'Menu Sign',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Subtarea borrable',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => now()->startOfWeek()->toDateString(),
            'assignee_id' => $designer->id,
        ]);

        $component = Livewire::test(WeeklyPlanner::class);

        $component->call('deleteSubtask', $subtask->id)
            ->assertSet('recentlyDeletedSubtaskIds', [(int) $subtask->id])
            ->assertSeeHtml('Subtarea');

        $this->assertSoftDeleted('related_tasks', ['id' => $subtask->id]);

        $component->call('undoDeleteSubtask');

        $this->assertDatabaseHas('related_tasks', [
            'id' => $subtask->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_toggle_system_tasks_visibility_and_persists_session(): void
    {
        $designer = Designer::create(['name' => 'Carla', 'active' => true]);

        $order = Order::create([
            'company_name' => 'EMPRESA TEST',
            'task_name' => 'Diseño Web',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->startOfWeek()->toDateString();

        $workTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Diseño Banner',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => $todayStr,
            'assignee_id' => $designer->id,
            'is_work_task' => true,
        ]);

        $systemTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Confirmar medidas',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => $todayStr,
            'assignee_id' => $designer->id,
            'is_work_task' => false,
        ]);

        $triggeredSystemTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => $todayStr,
            'assignee_id' => $designer->id,
            'is_work_task' => true,
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertSet('showSystemTasks', true)
            ->assertSee('Confirmar medidas')
            ->assertSee('Enviar correo de atraso preventivo')
            ->call('toggleShowSystemTasks')
            ->assertSet('showSystemTasks', false)
            ->assertDontSee('Confirmar medidas')
            ->assertDontSee('Enviar correo de atraso preventivo')
            ->assertSee('Diseño Banner');

        $this->assertEquals(false, session('weekly_planner_show_system_tasks'));
    }

    public function test_weekly_planner_displays_system_tasks_generated_by_automation_engine(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00')); // Monday morning
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'Auto Engine Corp',
            'task_name' => 'Branding Kit',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
            'current_due_date' => '2026-09-28',
        ]);

        // Triggers welcome email
        app(AutomationEngine::class)->handleOrderCreated($order);

        // Advance to 14:35 and evaluate overdue alert with due date today
        Carbon::setTestNow(Carbon::parse('2026-09-28 14:35:00'));
        $order->update(['current_due_date' => '2026-09-28']);
        app(AutomationEngine::class)->checkAndCreateOverdueTask($order);

        session(['weekly_planner_show_system_tasks' => true]);

        Livewire::test(WeeklyPlanner::class)
            ->set('selectedWeekStart', '2026-09-28')
            ->assertSet('showSystemTasks', true)
            ->assertSee('Enviar correo de bienvenida')
            ->assertSee('Enviar correo de atraso preventivo')
            ->call('toggleShowSystemTasks')
            ->assertSet('showSystemTasks', false)
            ->assertDontSee('Enviar correo de bienvenida')
            ->assertDontSee('Enviar correo de atraso preventivo');
    }

    public function test_weekly_planner_workspace_orders_list_includes_and_searches_locations(): void
    {
        $orderWithLocation = Order::create([
            'company_name' => 'SUCURSAL PRINCIPAL COMPANY',
            'location_name' => 'SEDE NORTE CANCUN',
            'task_name' => 'Instalación Letrero',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertViewHas('workspaceOrdersList', function ($list) use ($orderWithLocation) {
                $item = collect($list)->firstWhere('id', (string) $orderWithLocation->id);

                return $item !== null
                    && $item['location'] === 'SEDE NORTE CANCUN'
                    && str_contains($item['text'], 'SEDE NORTE CANCUN');
            })
            ->set('unscheduledSearch', 'SEDE NORTE CANCUN')
            ->assertViewHas('workspaceSearchResults', function ($results) use ($orderWithLocation) {
                return $results->pluck('id')->contains($orderWithLocation->id);
            });
    }

    public function test_can_reorder_subtasks_custom_and_persist_sort_order(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $order = Order::create([
            'company_name' => 'REORDER TEST COMPANY',
            'task_name' => 'Poster',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $dateStr = now()->startOfWeek()->toDateString();

        $subtask1 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Task One',
            'type' => RelatedTaskType::SUBTASK,
            'scheduled_date' => $dateStr,
            'assignee_id' => $designer->id,
            'sort_order' => 0,
        ]);

        $subtask2 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Task Two',
            'type' => RelatedTaskType::SUBTASK,
            'scheduled_date' => $dateStr,
            'assignee_id' => $designer->id,
            'sort_order' => 1,
        ]);

        // Reorder subtask2 before subtask1 - should automatically switch sort to custom (manual)
        Livewire::test(WeeklyPlanner::class)
            ->assertSet('plannerSortBy', 'priority')
            ->call('reorderSubtasks', [$subtask2->id, $subtask1->id], $dateStr)
            ->assertSet('plannerSortBy', 'custom')
            ->assertSessionHas('weekly_planner_sort_by', 'custom');

        $this->assertEquals(0, $subtask2->fresh()->sort_order);
        $this->assertEquals(1, $subtask1->fresh()->sort_order);
    }

    public function test_can_change_planner_sort_by_mode(): void
    {
        Livewire::test(WeeklyPlanner::class)
            ->assertSet('plannerSortBy', 'priority')
            ->call('changePlannerSortBy', 'custom')
            ->assertSet('plannerSortBy', 'custom')
            ->assertSessionHas('weekly_planner_sort_by', 'custom')
            ->call('changePlannerSortBy', 'priority')
            ->assertSet('plannerSortBy', 'priority')
            ->assertSessionHas('weekly_planner_sort_by', 'priority')
            ->call('changePlannerSortBy', 'client')
            ->assertSet('plannerSortBy', 'priority');
    }

    public function test_weekly_planner_includes_subtasks_for_archived_orders(): void
    {
        $designer = Designer::create(['name' => 'Carla', 'active' => true]);

        $archivedOrder = Order::create([
            'company_name' => 'ARCHIVED TAQUERIA',
            'task_name' => 'Flyer Design',
            'core_status' => CoreStatus::ARCHIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $subtask = RelatedTask::create([
            'order_id' => $archivedOrder->id,
            'title' => 'Revision Final Archivo',
            'scheduled_date' => $todayStr,
            'assignee_id' => $designer->id,
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertSee('Revision Final Archivo')
            ->assertSee('Archivada');
    }

    public function test_weekly_planner_synchronizes_past_pending_subtasks_in_both_view_modes(): void
    {
        $designer = Designer::create(['name' => 'Marcos', 'active' => true]);

        $pastDateStr = now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();

        $subtaskOverdue = RelatedTask::create([
            'order_id' => null,
            'title' => 'Subtarea Pendiente Pasada',
            'scheduled_date' => $pastDateStr,
            'assignee_id' => $designer->id,
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        // Assert it appears in by_day view mode
        Livewire::test(WeeklyPlanner::class)
            ->call('changeViewMode', 'by_day')
            ->assertSee('Subtarea Pendiente Pasada');

        // Assert it appears in by_designer view mode
        Livewire::test(WeeklyPlanner::class)
            ->call('changeViewMode', 'by_designer')
            ->assertSee('Subtarea Pendiente Pasada');
    }

    public function test_weekly_planner_renders_past_scheduled_pending_orders_without_error(): void
    {
        $designer = Designer::create(['name' => 'Sara', 'active' => true]);
        $pastDateStr = now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();

        $pastOrder = Order::create([
            'company_name' => 'PAST PENDING COMPANY',
            'task_name' => 'Old Pending Task',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'scheduled_date' => $pastDateStr,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->assertOk()
            ->assertSee('PAST PENDING COMPANY');
    }

    public function test_unapproved_order_with_poner_en_alta_subtask_triggers_approval_modal_and_cannot_be_completed_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'UNAPPROVED ALTA CORP',
            'task_name' => 'Brochure Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'approved' => false,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
            'assignee_id' => $designer->id,
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'is_work_task' => true,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $subtask->id)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        // Subtask must remain todo because approval is required
        $this->assertEquals('todo', $subtask->fresh()->status);
        $this->assertNull($subtask->fresh()->completed_at);

        // Order must not be in production
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_approved_order_with_poner_en_alta_subtask_moves_to_production_when_completed_in_weekly_planner(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'APPROVED ALTA CORP',
            'task_name' => 'Banner High Res',
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'substatus' => Substatus::PONER_EN_ALTA,
            'approved' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
            'assignee_id' => $designer->id,
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'is_work_task' => true,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $subtask->id)
            ->assertDispatched('order-updated');

        // Subtask must be completed
        $this->assertEquals('done', $subtask->fresh()->status);
        $this->assertNotNull($subtask->fresh()->completed_at);

        // Order must transition to EN_PRODUCCION with ENVIADO_EN_ALTA
        $freshOrder = $order->fresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $freshOrder->core_status);
        $this->assertEquals(Substatus::ENVIADO_EN_ALTA, $freshOrder->substatus);

        // Event must be logged
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'MOVED_TO_PRODUCTION',
            'new_value' => CoreStatus::EN_PRODUCCION->value,
        ]);
    }

    public function test_unapproved_order_with_poner_en_alta_subtask_triggers_approval_modal_in_order_detail_modal(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'UNAPPROVED MODAL CORP',
            'task_name' => 'Catalog Prep',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'approved' => false,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
            'assignee_id' => $designer->id,
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'is_work_task' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $subtask->id)
            ->assertSet('showApprovalModal', true)
            ->assertSet('pendingProductionStatus', CoreStatus::EN_PRODUCCION->value);

        $this->assertEquals('todo', $subtask->fresh()->status);
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_approved_order_with_poner_en_alta_subtask_moves_to_production_in_order_detail_modal(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'APPROVED MODAL CORP',
            'task_name' => 'Catalog Prep Approved',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'substatus' => Substatus::PONER_EN_ALTA,
            'approved' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
            'assignee_id' => $designer->id,
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'is_work_task' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $subtask->id)
            ->assertDispatched('order-updated');

        $this->assertEquals('done', $subtask->fresh()->status);
        $freshOrder = $order->fresh();
        $this->assertEquals(CoreStatus::EN_PRODUCCION, $freshOrder->core_status);
        $this->assertEquals(Substatus::ENVIADO_EN_ALTA, $freshOrder->substatus);
    }

    public function test_newly_created_subtask_is_placed_at_the_end_of_tasks_for_the_day(): void
    {
        $designer = Designer::create(['name' => 'Sara', 'active' => true]);
        $dateStr = now()->addDays(2)->toDateString();

        $order1 = Order::create([
            'company_name' => 'ORDER ONE',
            'task_name' => 'Design One',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->addDays(10),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $order2 = Order::create([
            'company_name' => 'ORDER TWO',
            'task_name' => 'Design Two',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->addDays(5),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // Create first subtask on that date
        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order1->id, 'First Task', $dateStr, $designer->id);

        $task1 = RelatedTask::where('title', 'First Task')->first();
        $this->assertEquals(0, $task1->sort_order);

        // Create second subtask on the same date (even with earlier due date on order2)
        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order2->id, 'Second Task', $dateStr, $designer->id);

        $task2 = RelatedTask::where('title', 'Second Task')->first();
        $this->assertGreaterThan($task1->sort_order, $task2->sort_order);

        // In WeeklyPlanner, sortSubtaskCollection must place task2 after task1
        $component = Livewire::test(WeeklyPlanner::class);
        $sorted = $component->instance()->sortSubtaskCollection(collect([$task1, $task2]));
        $this->assertEquals($task1->id, $sorted->first()->id);
        $this->assertEquals($task2->id, $sorted->last()->id);
    }

    public function test_newly_added_subtask_is_added_to_bottom_of_day_list_even_with_done_and_urgent_tasks(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $dateStr = now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString(); // Tuesday

        $completedOrder = Order::create([
            'company_name' => 'MOVIDAGRAFICA',
            'task_name' => 'Website',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $existingTask1 = RelatedTask::create([
            'order_id' => $completedOrder->id,
            'title' => 'Enviar correo de bienvenida',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'done',
            'scheduled_date' => $dateStr,
            'assignee_id' => $designer->id,
            'sort_order' => 0,
        ]);

        $inProgressOrder = Order::create([
            'company_name' => 'KUDOS PRINT MEDIA',
            'task_name' => 'Signs',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $existingTask2 = RelatedTask::create([
            'order_id' => $inProgressOrder->id,
            'title' => 'First Proposal',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => $dateStr,
            'assignee_id' => $designer->id,
            'sort_order' => 1,
        ]);

        $urgentOrder = Order::create([
            'company_name' => 'ALTITUDE DISTRIBUTIONS LLC',
            'task_name' => 'POLOS DTF',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => Carbon::parse($dateStr), // SLA HOY
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // Add a new subtask (Poner en alta) for ALTITUDE DISTRIBUTIONS LLC
        $planner = Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $urgentOrder->id, 'Poner en alta', $dateStr, $designer->id)
            ->assertSet('plannerSortBy', 'custom')
            ->assertSessionHas('weekly_planner_sort_by', 'custom');

        $newTask = RelatedTask::where('title', 'Poner en alta')->first();
        $this->assertNotNull($newTask);

        // It must have a sort_order greater than all existing tasks
        $this->assertEquals(2, $newTask->sort_order);

        // When retrieved for the day list, the new task must be at the very bottom
        $subtasksForDay = $planner->instance()->getExistingSubtasksForDay(Carbon::parse($dateStr), $designer->id);
        $this->assertCount(3, $subtasksForDay);
        $this->assertEquals($existingTask2->id, $subtasksForDay->values()[0]->id);
        $this->assertEquals($existingTask1->id, $subtasksForDay->values()[1]->id);
        $this->assertEquals($newTask->id, $subtasksForDay->values()[2]->id);
    }

    public function test_checked_subtask_does_not_trigger_sla_alerts_banner(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06'));
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);

        $order = Order::create([
            'company_name' => 'SUPERMERCADOS TALPA',
            'task_name' => 'PROPUESTA GIMNASIO, AJUSTES',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => Carbon::parse('2026-09-21'),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'PROPUESTA GIMNASIO, AJUSTES',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => Carbon::parse('2026-10-06'),
            'assignee_id' => $designer->id,
        ]);

        // When subtask is pending, it should be in slaBreachedList and trigger the SLA alerts banner
        Livewire::test(WeeklyPlanner::class)
            ->assertViewHas('slaBreachedList', fn ($list) => $list->count() === 1 && $list->first()['task_name'] === 'PROPUESTA GIMNASIO, AJUSTES')
            ->assertSee('Alertas SLA');

        // Check off the subtask
        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $subtask->id);

        $this->assertTrue($subtask->fresh()->isDone());

        // When subtask is checked, it must NOT appear in slaBreachedList and must NOT show SLA alerts banner
        Livewire::test(WeeklyPlanner::class)
            ->assertViewHas('slaBreachedList', fn ($list) => $list->isEmpty())
            ->assertDontSee('Alertas SLA');
    }
}
