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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSet('viewMode', 'by_day')
            ->assertSeeHtml('Por Días')
            ->assertSeeHtml('Por Diseñador')
            ->call('changeViewMode', 'by_designer')
            ->assertSet('viewMode', 'by_designer')
            ->assertSessionHas('weekly_planner_view_mode', 'by_designer')
            ->assertSeeHtml('RESTAURANTE EL TACO')
            ->assertSeeHtml('Ajuste de colores');

        // Verify next component mount initializes with persisted viewMode
        Livewire::test(WeeklyPlanner::class)
            ->assertSet('viewMode', 'by_designer');
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
}
