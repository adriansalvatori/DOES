<?php

namespace Tests\Feature;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Livewire\Orders\OrderDetailModal;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderApprovalSubtaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_second_subtask_to_future_date_auto_returns_order_after_first_completed(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'ACME TRUCKS',
            'task_name' => 'Partial Wrap',
            'wo_number' => 'WO 16289',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
            'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
            'done_today' => false,
        ]);

        $todayStr = now()->toDateString();
        $tomorrowStr = now()->addWeekdays(1)->toDateString();

        // 2 tasks for today: Task 1 sends to client, Task 2 is production
        $task1 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajustes cliente',
            'type' => RelatedTaskType::RESOLVER,
            'category' => SubtaskCategory::CLIENT_ADJUSTMENTS,
            'return_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'scheduled_date' => $todayStr,
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        $task2 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS,
            'return_core_status' => CoreStatus::EN_PRODUCCION,
            'scheduled_date' => $todayStr,
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        // Complete task 1
        $task1->update(['status' => 'done', 'completed_at' => now()]);

        // Order remains in TO_DO_TODAY because task 2 is still scheduled for today
        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);

        // Now move task 2 to tomorrow
        $task2->update(['scheduled_date' => $tomorrowStr]);

        // Observer should automatically evaluate order completion and return it to ENVIADO_AL_CLIENTE!
        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_approved_order_with_missing_measures_generates_poner_en_alta_when_unblocked_or_measures_confirmed(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'PRINT SHOP',
            'task_name' => 'Storefront Sign',
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // Process approval without measures
        app(AutomationEngine::class)->processApproval(
            order: $order,
            measuresConfirmed: false,
            estimateApproved: true,
            approvalType: 'cliente',
            approvalNote: 'Arte aprobado por cliente, pendientes medidas físicas'
        );

        $order->refresh();
        $this->assertTrue($order->approved);
        $this->assertFalse($order->measures_confirmed);
        $this->assertEquals(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertEquals(Substatus::BLOQUEADA, $order->substatus);
        $this->assertEquals(BlockingReason::FALTAN_MEDIDAS, $order->blocking_reason);

        // Find the automatic resolver task
        $measureTask = $order->relatedTasks()->where('type', RelatedTaskType::RESOLVER->value)->first();
        $this->assertNotNull($measureTask);
        $this->assertStringContainsString('Medidas pendientes', $measureTask->title);

        // Now, complete the resolver task (measures arrived!)
        $measureTask->update(['status' => 'done', 'completed_at' => now()]);

        $order->refresh();
        $this->assertTrue($order->measures_confirmed);
        $this->assertNotEquals(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertEquals(Substatus::PONER_EN_ALTA, $order->substatus);

        // The "Poner en alta" task should have been automatically generated!
        $altaTask = $order->relatedTasks()->where('type', RelatedTaskType::PONER_ALTA->value)->first();
        $this->assertNotNull($altaTask);
        $this->assertEquals('Poner en alta', $altaTask->title);
        $this->assertEquals('todo', $altaTask->status);
    }

    public function test_adding_client_adjustment_subtask_to_approved_order_with_invalidation_resets_approval_and_removes_alta_task(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'MEGA STORE',
            'task_name' => 'Banner Display',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'substatus' => Substatus::PONER_EN_ALTA,
            'approved' => true,
            'approved_at' => now()->subDay(),
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // There is already a pending Poner en alta task
        $altaTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS,
            'return_core_status' => CoreStatus::EN_PRODUCCION,
            'scheduled_date' => now()->toDateString(),
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        // User adds a client adjustments subtask and confirms approval invalidation
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newTaskTitle', 'Ajustes cliente: cambiar número de teléfono')
            ->set('newTaskCategory', SubtaskCategory::CLIENT_ADJUSTMENTS->value)
            ->set('newTaskDate', now()->toDateString())
            ->call('addTask')
            ->assertSet('showSubtaskWarningModal', true)
            ->set('subtaskApprovalImpact', 'invalidate')
            ->call('confirmSubtaskWarning');

        $order->refresh();
        $this->assertFalse($order->approved);
        $this->assertFalse($order->measures_confirmed);

        // The old Poner en alta task must be deleted!
        $this->assertDatabaseMissing('related_tasks', ['id' => $altaTask->id]);

        // The new client subtask must be present
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Ajustes cliente: cambiar número de teléfono',
        ]);
    }

    public function test_completing_poner_en_alta_requires_reapproval_if_work_was_completed_after_approval_date(): void
    {
        $designer = Designer::create(['name' => 'César', 'active' => true]);

        $approvalDate = now()->subHours(5);

        $order = Order::create([
            'company_name' => 'AUTO CLINIC',
            'task_name' => 'Vehicle Graphics',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'approved' => true,
            'approved_at' => $approvalDate,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // A work task was completed AFTER the approval date (e.g. 2 hours ago)
        $workTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Cambio de diseño urgente',
            'type' => RelatedTaskType::SUBTASK,
            'category' => SubtaskCategory::NEW_DESIGN,
            'scheduled_date' => now()->toDateString(),
            'status' => 'done',
            'completed_at' => now()->subHours(2),
            'is_work_task' => true,
        ]);

        $altaTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA,
            'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS,
            'scheduled_date' => now()->toDateString(),
            'status' => 'todo',
            'is_work_task' => true,
        ]);

        // Trying to complete "Poner en alta" in OrderDetailModal must block and request re-approval
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $altaTask->id)
            ->assertSet('showApprovalModal', true);

        $order->refresh();
        // The order MUST NOT have been moved to EN_PRODUCCION!
        $this->assertNotEquals(CoreStatus::EN_PRODUCCION, $order->core_status);

        // Also test in WeeklyPlanner
        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $altaTask->id)
            ->assertDispatched('open-order-detail');

        $order->refresh();
        $this->assertNotEquals(CoreStatus::EN_PRODUCCION, $order->core_status);
    }

    public function test_weekly_planner_subtask_warning_handles_unblock_and_keep_blocked_choices(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'BLOCKED MOTORS',
            'task_name' => 'Decals',
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // 1. Scheduling subtask triggers the warning modal
        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Consultar al cliente medidas', now()->toDateString(), $designer->id, true)
            ->assertSet('showSubtaskWarningModal', true)
            ->set('subtaskBlockResolution', 'keep_blocked')
            ->call('confirmSubtaskWarning');

        $order->refresh();
        // Since user selected keep_blocked, the order MUST stay in ENTRANTE with BLOQUEADA
        $this->assertEquals(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertEquals(Substatus::BLOQUEADA, $order->substatus);

        // 2. Now schedule a subtask with unblock resolution
        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Medidas recibidas de camioneta', now()->toDateString(), $designer->id, true)
            ->assertSet('showSubtaskWarningModal', true)
            ->set('subtaskBlockResolution', 'unblock')
            ->call('confirmSubtaskWarning');

        $order->refresh();
        $this->assertTrue($order->measures_confirmed);
        $this->assertNotEquals(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertNotEquals(Substatus::BLOQUEADA, $order->substatus);
    }
}
