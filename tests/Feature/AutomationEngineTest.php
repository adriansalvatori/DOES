<?php

namespace Tests\Feature;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Designer::create(['name' => 'Adrián', 'active' => true]);
        Designer::create(['name' => 'Euralíz', 'active' => true]);
        Designer::create(['name' => 'César', 'active' => true]);
    }

    public function test_new_order_creation_generates_welcome_email_task()
    {
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Test Company',
            'task_name' => 'Test Design Task',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->handleOrderCreated($order);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Enviar correo bienvenida/orden nueva',
        ]);

        $this->assertNotNull($order->fresh()->current_due_date);
    }

    public function test_welcome_email_scheduled_for_today_if_created_before_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 14:00:00')); // Wednesday 2:00 PM
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Before Cutoff Corp',
            'task_name' => 'Web Banner',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->handleOrderCreated($order);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::BIENVENIDA)->first();
        $this->assertNotNull($task);
        $this->assertEquals('2026-09-30', $task->scheduled_date->toDateString());
        $this->assertEquals('2026-09-30', $task->due_date->toDateString());
    }

    public function test_welcome_email_scheduled_for_next_weekday_if_created_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 16:35:00')); // Wednesday 4:35 PM
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'After Cutoff Corp',
            'task_name' => 'Web Banner',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->handleOrderCreated($order);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::BIENVENIDA)->first();
        $this->assertNotNull($task);
        $this->assertEquals('2026-10-01', $task->scheduled_date->toDateString()); // Thursday
        $this->assertEquals('2026-10-01', $task->due_date->toDateString());
    }

    public function test_welcome_email_scheduled_for_monday_if_created_friday_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 17:00:00')); // Friday 5:00 PM
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Friday Evening Corp',
            'task_name' => 'Brochure',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->handleOrderCreated($order);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::BIENVENIDA)->first();
        $this->assertNotNull($task);
        $this->assertEquals('2026-10-05', $task->scheduled_date->toDateString()); // Monday
        $this->assertEquals('2026-10-05', $task->due_date->toDateString());
    }

    public function test_welcome_email_scheduled_for_monday_if_created_on_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 11:00:00')); // Saturday 11:00 AM
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Weekend Corp',
            'task_name' => 'Flyer',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->handleOrderCreated($order);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::BIENVENIDA)->first();
        $this->assertNotNull($task);
        $this->assertEquals('2026-10-05', $task->scheduled_date->toDateString()); // Monday
        $this->assertEquals('2026-10-05', $task->due_date->toDateString());
    }

    public function test_approval_flow_with_missing_measures_moves_to_entrante_and_creates_resolver_task()
    {
        $designer = Designer::where('name', 'Euralíz')->first();
        $order = Order::create([
            'company_name' => 'Glossy Signs',
            'task_name' => 'Acrylic Sign',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
        ]);

        app(AutomationEngine::class)->processApproval($order, measuresConfirmed: false, estimateApproved: true);

        $freshOrder = $order->fresh();
        $this->assertTrue($freshOrder->approved);
        $this->assertFalse($freshOrder->measures_confirmed);
        $this::assertEquals(CoreStatus::ENTRANTE, $freshOrder->core_status);
        $this->assertEquals(Substatus::BLOQUEADA, $freshOrder->substatus);
        $this->assertEquals(BlockingReason::FALTAN_MEDIDAS, $freshOrder->blocking_reason);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::RESOLVER->value,
        ]);
    }

    public function test_fully_approved_flow_moves_to_designer_orders_received_and_poner_en_alta()
    {
        $designer = Designer::where('name', 'César')->first();
        $order = Order::create([
            'company_name' => 'Fleet Logistics',
            'task_name' => 'Vehicle Branding',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        app(AutomationEngine::class)->processApproval($order, measuresConfirmed: true, estimateApproved: true);

        $freshOrder = $order->fresh();
        $this->assertTrue($freshOrder->approved);
        $this->assertTrue($freshOrder->measures_confirmed);
        $this->assertEquals(CoreStatus::CESAR_ORDERS_RECEIVED, $freshOrder->core_status);
        $this->assertEquals(Substatus::PONER_EN_ALTA, $freshOrder->substatus);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Poner en alta',
            'type' => RelatedTaskType::PONER_ALTA->value,
            'status' => 'todo',
        ]);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->first();
        $this->assertNotNull($task);
        $this->assertEquals(now()->addWeekdays(1)->toDateString(), $task->due_date?->toDateString());
        $this->assertEquals(now()->addWeekdays(1)->toDateString(), $task->scheduled_date?->toDateString());
        $this->assertTrue($task->is_work_task);
    }

    public function test_delay_resolution_clears_overdue_and_saves_due_date_history()
    {
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Delayed Corp',
            'task_name' => 'Banner Design',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::OVERDUE,
            'current_due_date' => now()->subDays(2)->toDateString(),
        ]);

        $promisedDate = now()->addDays(3);
        app(AutomationEngine::class)->resolveDelay($order, $promisedDate, 'Retraso por cliente acordado');

        $freshOrder = $order->fresh();
        $this->assertNull($freshOrder->substatus);
        $this->assertEquals($promisedDate->toDateString(), $freshOrder->current_due_date->toDateString());

        $this->assertEquals($promisedDate->toDateString(), DueDateHistory::first()->new_due_date->toDateString());
    }

    public function test_moving_approved_order_back_to_enviado_al_cliente_resets_approval_state()
    {
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Approved Corp',
            'task_name' => 'Signage Design',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
        ]);

        // Move to ENVIADO AL CLIENTE
        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::EURALIZ_ORDERS_RECEIVED, CoreStatus::ENVIADO_AL_CLIENTE);

        $freshOrder = $order->fresh();
        $this->assertFalse($freshOrder->approved);
        $this->assertFalse($freshOrder->measures_confirmed);
        $this->assertFalse($freshOrder->estimate_approved);
        $this->assertEquals(Substatus::WAITING_FOR_CLIENT, $freshOrder->substatus);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'APPROVAL_RESET',
        ]);
    }

    public function test_moving_approved_order_to_enviado_a_camila_resets_approval_state()
    {
        $designer = Designer::first();
        $order = Order::create([
            'company_name' => 'Camila Review Corp',
            'task_name' => 'Brochure Design',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
        ]);

        // Move to ENVIADO A CAMILA
        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::EURALIZ_ORDERS_RECEIVED, CoreStatus::ENVIADO_A_CAMILA);

        $freshOrder = $order->fresh();
        $this->assertFalse($freshOrder->approved);
        $this->assertFalse($freshOrder->measures_confirmed);
        $this->assertFalse($freshOrder->estimate_approved);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'APPROVAL_RESET',
        ]);
    }

    public function test_urgent_order_approval_assigns_same_day_sla_and_subtask(): void
    {
        $designer = Designer::where('name', 'César')->first();
        $order = Order::create([
            'company_name' => 'Urgent Corp',
            'task_name' => 'Fast Banner',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::URGENTE,
        ]);

        $this->assertTrue($order->isUrgente());

        app(AutomationEngine::class)->processApproval(
            $order,
            measuresConfirmed: true,
            estimateApproved: true,
            approvalType: 'cliente',
            approvalNote: 'Aprobación urgente directa'
        );

        $freshOrder = $order->fresh();
        $this->assertTrue($freshOrder->approved);
        $this->assertTrue($freshOrder->isUrgente());
        $this->assertEquals(today()->toDateString(), $freshOrder->current_due_date?->toDateString());
        $this->assertEquals(Substatus::PONER_EN_ALTA, $freshOrder->substatus);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->first();
        $this->assertNotNull($task);
        $this->assertEquals('Poner en alta', $task->title);
        $this->assertEquals(today()->toDateString(), $task->due_date?->toDateString());
        $this->assertEquals(today()->toDateString(), $task->scheduled_date?->toDateString());
        $this->assertEquals('urgent', $task->priority);
        $this->assertTrue($task->is_work_task);
    }
}
