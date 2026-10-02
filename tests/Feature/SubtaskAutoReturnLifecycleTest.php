<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubtaskAutoReturnLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_adjustment_subtask_moves_to_working_today_and_returns_to_enviado_al_cliente_on_completion(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'CLIENT CORP',
            'task_name' => 'Brochure Design',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        // Add client adjustment subtask via OrderDetailModal
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newTaskTitle', 'Ajustes de logo según feedback cliente')
            ->set('newTaskDate', $todayStr)
            ->set('newTaskCategory', SubtaskCategory::CLIENT_ADJUSTMENTS->value)
            ->call('addTask');

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CLIENTE, $order->substatus);
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->origin_core_status);

        $task = $order->relatedTasks()->first();
        $this->assertNotNull($task);
        $this->assertEquals(SubtaskCategory::CLIENT_ADJUSTMENTS, $task->category);
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $task->return_core_status);

        // Complete the subtask
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $task->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);

        // Uncheck the subtask -> should re-open into TO_DO_TODAY
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $task->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CLIENTE, $order->substatus);
    }

    public function test_camila_adjustment_subtask_returns_to_enviado_a_camila_on_completion(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'CAMILA CORP',
            'task_name' => 'Flyer Redesign',
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'substatus' => Substatus::CAMBIOS_CAMILA,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newTaskTitle', 'Ajustes Camila para impresión')
            ->set('newTaskDate', $todayStr)
            ->set('newTaskCategory', SubtaskCategory::CAMILA_ADJUSTMENTS->value)
            ->call('addTask');

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CAMILA, $order->substatus);
        $this->assertEquals(CoreStatus::ENVIADO_A_CAMILA, $order->origin_core_status);

        $task = $order->relatedTasks()->first();
        $this->assertNotNull($task);

        // Complete task
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $task->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_A_CAMILA, $order->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CAMILA, $order->substatus);
    }

    public function test_order_with_multiple_today_subtasks_only_returns_when_all_are_completed(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'MULTI TASK CO',
            'task_name' => 'Packaging Design',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        // Create 2 tasks
        $task1 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajuste color logo',
            'scheduled_date' => $todayStr,
            'status' => 'todo',
            'is_work_task' => true,
            'category' => SubtaskCategory::CLIENT_ADJUSTMENTS,
            'return_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $task2 = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajuste texto trasero',
            'scheduled_date' => $todayStr,
            'status' => 'todo',
            'is_work_task' => true,
            'category' => SubtaskCategory::CLIENT_ADJUSTMENTS,
            'return_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $order->update([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CLIENTE,
            'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        // Complete first task only
        $task1->update(['status' => 'done', 'completed_at' => now()]);
        app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($order);

        $order->refresh();
        // Still has 1 incomplete task for today, so must remain in TO_DO_TODAY
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);

        // Complete second task
        $task2->update(['status' => 'done', 'completed_at' => now()]);
        app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($order);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);
    }

    public function test_order_from_designer_queue_moves_to_enviado_al_cliente_when_client_adjustment_task_completed(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        $order = Order::create([
            'company_name' => 'PROTOUCH RENOVATION',
            'task_name' => 'Business Cards',
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        // Add client adjustment subtask
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newTaskTitle', 'Ajustes cliente')
            ->set('newTaskDate', $todayStr)
            ->set('newTaskCategory', SubtaskCategory::CLIENT_ADJUSTMENTS->value)
            ->call('addTask');

        $order->refresh();
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertEquals(Substatus::CAMBIOS_CLIENTE, $order->substatus);
        $this->assertEquals(CoreStatus::EURALIZ_ORDERS_RECEIVED, $order->origin_core_status);

        $task = $order->relatedTasks()->where('title', 'Ajustes cliente')->first();
        $this->assertNotNull($task);

        // Complete the task
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $task->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_automated_client_follow_up_is_management_task_and_does_not_change_order_core_status(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'SUPERMERCADOS TALPA',
            'task_name' => 'Brochure Design',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'last_sent_to_client_at' => now()->subWeekdays(3),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $task = $order->relatedTasks()->where('title', 'Follow Up Cliente #1')->first();
        $this->assertNotNull($task);
        $this->assertTrue($task->isFollowUp());
        $this->assertFalse($task->is_work_task);
        $this->assertFalse($task->isWorkTask());
        $this->assertEquals(SubtaskCategory::MANAGEMENT, $task->category);

        $order->refresh();
        // The order core status MUST NOT change to TO_DO_TODAY
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);

        // Marking the follow-up task done must also keep the order in ENVIADO_AL_CLIENTE
        $task->update(['status' => 'done', 'completed_at' => now()]);
        app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($order);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
    }

    public function test_automated_camila_follow_up_is_management_task_and_does_not_change_order_core_status(): void
    {
        $designer = Designer::create(['name' => 'Camila', 'active' => true]);

        $order = Order::create([
            'company_name' => 'CLIENT CORP',
            'task_name' => 'Logo Review',
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'substatus' => Substatus::CAMBIOS_CAMILA,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Follow Up Camila',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'status' => 'todo',
            'assignee_id' => $designer->id,
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        $this->assertTrue($task->isFollowUp());
        $this->assertFalse($task->is_work_task);
        $this->assertFalse($task->isWorkTask());
        $this->assertEquals(SubtaskCategory::MANAGEMENT, $task->category);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_A_CAMILA, $order->core_status);
    }
}
