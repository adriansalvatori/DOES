<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedTaskOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_order_discards_unchecked_welcome_email_task(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-991',
            'company_name' => 'Acme Corp',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        $welcomeTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de bienvenida',
            'type' => RelatedTaskType::BIENVENIDA,
            'status' => 'todo',
            'due_date' => now()->toDateString(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::ENTRANTE, CoreStatus::ENVIADO_A_CAMILA);

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $welcomeTask->id,
        ]);
    }

    public function test_status_change_preserves_completed_system_tasks(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-992',
            'company_name' => 'Beta Corp',
            'task_name' => 'Logo Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $completedTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'done',
            'completed_at' => now(),
            'due_date' => now()->toDateString(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::EN_PRODUCCION);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $completedTask->id,
            'status' => 'done',
        ]);
    }

    public function test_status_change_preserves_manual_user_subtasks(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-993',
            'company_name' => 'Gamma Corp',
            'task_name' => 'Flyer Print',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $userSubtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Corte de vinil manual',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'due_date' => now()->toDateString(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ARCHIVED);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $userSubtask->id,
            'status' => 'todo',
        ]);
    }

    public function test_correo_atraso_not_created_for_archived_or_on_hold_orders(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-994',
            'company_name' => 'Delta Corp',
            'task_name' => 'Poster Design',
            'core_status' => CoreStatus::ARCHIVED,
            'current_due_date' => now()->subDay(),
            'in_workspace' => true,
        ]);

        app(AutomationEngine::class)->checkAndCreateOverdueTask($order);

        $this->assertDatabaseMissing('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::CORREO_ATRASO->value,
        ]);
    }
}
