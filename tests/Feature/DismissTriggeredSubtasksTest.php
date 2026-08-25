<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DismissTriggeredSubtasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_triggered_subtasks_are_dismissed_on_transition_to_camila(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-201',
            'company_name' => 'Test Co 1',
            'task_name' => 'Design Task 1',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $systemTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
        ]);

        $userTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'User custom scheduled subtask',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'trigger_type' => null,
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_A_CAMILA);

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $systemTask->id,
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $userTask->id,
            'title' => 'User custom scheduled subtask',
        ]);
    }

    public function test_system_triggered_subtasks_are_dismissed_on_transition_to_client(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-202',
            'company_name' => 'Test Co 2',
            'task_name' => 'Design Task 2',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $resolverTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'RESOLVER: Medidas pendientes',
            'type' => RelatedTaskType::RESOLVER,
            'status' => 'todo',
            'trigger_type' => 'MISSING_MEASURES_APPROVED',
        ]);

        $userTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Manual review notes',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_AL_CLIENTE);

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $resolverTask->id,
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $userTask->id,
        ]);
    }

    public function test_system_triggered_subtasks_are_dismissed_on_transition_to_on_hold(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-203',
            'company_name' => 'Test Co 3',
            'task_name' => 'Design Task 3',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $systemTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
        ]);

        $userTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Custom user reminder',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ON_HOLD);

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $systemTask->id,
        ]);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $userTask->id,
        ]);
    }

    public function test_system_triggered_subtasks_are_dismissed_on_transition_to_production(): void
    {
        $order = Order::create([
            'wo_number' => 'WO-204',
            'company_name' => 'Test Co 4',
            'task_name' => 'Design Task 4',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $systemTask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::EN_PRODUCCION);

        $this->assertDatabaseMissing('related_tasks', [
            'id' => $systemTask->id,
        ]);
    }
}
