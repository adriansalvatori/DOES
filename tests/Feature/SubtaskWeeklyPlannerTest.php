<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
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
}
