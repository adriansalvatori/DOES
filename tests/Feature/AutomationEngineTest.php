<?php

namespace Tests\Feature;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Services\AutomationEngine;
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
            'title' => 'Enviar correo de bienvenida',
        ]);

        $this->assertNotNull($order->fresh()->current_due_date);
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
}
