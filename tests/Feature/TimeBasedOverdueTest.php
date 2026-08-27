<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\SlaEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeBasedOverdueTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_today_before_230pm_sets_almost_overdue_without_subtask(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00'));

        $order = Order::create([
            'wo_number' => 'WO-101',
            'company_name' => 'Test Company',
            'task_name' => 'Task 101',
            'current_due_date' => '2026-08-24',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        $slaEngine = app(SlaEngine::class);
        $slaEngine->checkOverdue($order);

        $order->refresh();

        $this->assertEquals(Substatus::ALMOST_OVERDUE, $order->substatus);
        $this->assertDatabaseMissing('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
        ]);
    }

    public function test_due_today_past_230pm_triggers_urgent_subtask_and_remains_almost_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 15:00:00'));

        $order = Order::create([
            'wo_number' => 'WO-102',
            'company_name' => 'Test Company',
            'task_name' => 'Task 102',
            'current_due_date' => '2026-08-24',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        $slaEngine = app(SlaEngine::class);
        $slaEngine->checkOverdue($order);

        $order->refresh();

        $this->assertEquals(Substatus::ALMOST_OVERDUE, $order->substatus);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
            'priority' => 'urgent',
        ]);
    }

    public function test_due_today_past_400pm_sets_overdue_substatus_and_urgent_subtask(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 16:30:00'));

        $order = Order::create([
            'wo_number' => 'WO-103',
            'company_name' => 'Test Company',
            'task_name' => 'Task 103',
            'current_due_date' => '2026-08-24',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        $slaEngine = app(SlaEngine::class);
        $slaEngine->checkOverdue($order);

        $order->refresh();

        $this->assertEquals(Substatus::OVERDUE, $order->substatus);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
            'priority' => 'urgent',
        ]);
    }

    public function test_completing_order_dismisses_pending_delay_email_subtask(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 15:00:00'));

        $order = Order::create([
            'wo_number' => 'WO-104',
            'company_name' => 'Test Company',
            'task_name' => 'Task 104',
            'current_due_date' => '2026-08-24',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::ALMOST_OVERDUE,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'priority' => 'urgent',
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_A_CAMILA);

        $this->assertDatabaseMissing('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
        ]);
    }

    public function test_clearing_due_date_resets_overdue_substatus_and_dismisses_pending_overdue_tasks(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-105',
            'company_name' => 'Test Company',
            'task_name' => 'Task 105',
            'current_due_date' => '2026-08-20',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::OVERDUE,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
            'priority' => 'urgent',
        ]);

        $this->assertTrue($order->isOverdue());

        $order->update(['current_due_date' => null]);
        $order->refresh();

        $this->assertFalse($order->isOverdue());

        $slaEngine = app(SlaEngine::class);
        $slaEngine->checkOverdue($order);
        $order->refresh();

        $this->assertNull($order->substatus);
        $this->assertDatabaseMissing('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
        ]);
    }

    public function test_orders_sent_to_client_are_never_overdue(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-106',
            'company_name' => 'Test Company',
            'task_name' => 'Task 106',
            'current_due_date' => '2026-08-20',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::OVERDUE,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        $this->assertFalse($order->isOverdue());

        $slaEngine = app(SlaEngine::class);
        $slaEngine->checkOverdue($order);
        $order->refresh();

        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertFalse($order->isOverdue());
    }

    public function test_orders_sent_to_camila_can_be_overdue(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-107',
            'company_name' => 'Test Company',
            'task_name' => 'Task 107',
            'current_due_date' => '2026-08-20',
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'substatus' => null,
            'done_today' => false,
            'in_workspace' => true,
        ]);

        $this->assertTrue($order->isOverdue());
    }
}
