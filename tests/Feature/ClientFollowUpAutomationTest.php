<?php

namespace Tests\Feature;

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFollowUpAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_order_to_enviado_al_cliente_sets_last_sent_to_client_at_and_logs_event(): void
    {
        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $automation = app(AutomationEngine::class);
        $automation->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_AL_CLIENTE);

        $order->refresh();

        $this->assertNotNull($order->last_sent_to_client_at);
        $this->assertTrue($order->last_sent_to_client_at->isToday());
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_SENT_TO_CLIENT',
            'new_value' => CoreStatus::ENVIADO_AL_CLIENTE->value,
        ]);
    }

    public function test_daily_automation_creates_follow_up_1_task_at_3_business_days(): void
    {
        $threeDaysAgo = Carbon::now()->subWeekdays(3);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $threeDaysAgo,
            'in_workspace' => true,
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $task = RelatedTask::where('order_id', $order->id)
            ->where('title', 'Follow Up Cliente #1')
            ->first();

        $this->assertNotNull($task);
        $this->assertEquals(RelatedTaskType::FOLLOW_UP_CLIENTE, $task->type);
        $this->assertEquals(now()->toDateString(), $task->scheduled_date->toDateString());
    }

    public function test_daily_automation_creates_follow_up_2_task_at_6_business_days(): void
    {
        $sixDaysAgo = Carbon::now()->subWeekdays(6);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $sixDaysAgo,
            'in_workspace' => true,
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $task = RelatedTask::where('order_id', $order->id)
            ->where('title', 'Follow Up Cliente #2')
            ->first();

        $this->assertNotNull($task);
        $this->assertEquals(RelatedTaskType::FOLLOW_UP_CLIENTE, $task->type);
    }

    public function test_daily_automation_moves_order_to_on_hold_after_9_business_days(): void
    {
        $tenDaysAgo = Carbon::now()->subWeekdays(10);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $tenDaysAgo,
            'in_workspace' => true,
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $order->refresh();

        $this->assertEquals(CoreStatus::ON_HOLD, $order->core_status);
        $this->assertEquals(Substatus::CUSTOMER_SERVICE_REQUIRED, $order->substatus);
        $this->assertTrue($order->customer_service_required);
    }

    public function test_order_attribute_updates_do_not_reset_last_sent_to_client_at(): void
    {
        $fourDaysAgo = Carbon::now()->subWeekdays(4);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $fourDaysAgo,
            'in_workspace' => true,
        ]);

        // Simulating background Trello sync or user edit updating updated_at
        $order->update(['task_name' => 'Updated Task Name']);

        $order->refresh();

        $this->assertEquals($fourDaysAgo->toDateTimeString(), $order->last_sent_to_client_at->toDateTimeString());

        app(AutomationEngine::class)->runDailyAutomations();

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Follow Up Cliente #1',
        ]);
    }

    public function test_multi_revision_cycle_resets_follow_up_tasks(): void
    {
        $threeDaysAgo = Carbon::now()->subWeekdays(3);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $threeDaysAgo,
            'in_workspace' => true,
        ]);

        // Cycle 1 follow up created
        app(AutomationEngine::class)->runDailyAutomations();

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Follow Up Cliente #1',
        ]);

        // Client responds -> Order moved back to TO_DO_TODAY
        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::TO_DO_TODAY);

        // Pending follow-up task from Cycle 1 deleted
        $this->assertDatabaseMissing('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Follow Up Cliente #1',
            'deleted_at' => null,
        ]);

        // Cycle 2 -> Order sent to client again
        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_AL_CLIENTE);

        // Move last_sent_to_client_at back 3 weekdays
        $order->update(['last_sent_to_client_at' => Carbon::now()->subWeekdays(3)]);

        // Cycle 2 daily automation
        app(AutomationEngine::class)->runDailyAutomations();

        // Cycle 2 task created
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Follow Up Cliente #1',
            'deleted_at' => null,
        ]);
    }

    public function test_artisan_command_runs_daily_automations(): void
    {
        $threeDaysAgo = Carbon::now()->subWeekdays(3);

        $order = Order::create([
            'company_name' => 'Test Co',
            'task_name' => 'Test Task',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => $threeDaysAgo,
            'in_workspace' => true,
        ]);

        $this->artisan('orders:run-daily-automations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Follow Up Cliente #1',
        ]);
    }
}
