<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes AutomationEngine::checkAndCreateOverdueTask() (the preventive delay email task).
 */
class AutomationDelayTaskTest extends AutomationCharacterizationTestCase
{
    private function dueOrder(CoreStatus $status, string $due, array $overrides = []): Order
    {
        return $this->makeOrder(array_merge(['core_status' => $status, 'current_due_date' => $due], $overrides));
    }

    private function delayTasks(Order $order): int
    {
        return RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::CORREO_ATRASO)->count();
    }

    public function test_overdue_order_gets_an_urgent_delay_task_at_any_time_of_day(): void
    {
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->subDay()->toDateString());

        $this->engine()->checkAndCreateOverdueTask($order);

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('Enviar correo de atraso preventivo', $task->title);
        $this->assertSame(RelatedTaskType::CORREO_ATRASO, $task->type);
        $this->assertSame('todo', $task->status);
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('AUTOMATIC_OVERDUE_DETECTION', $task->trigger_type);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame('2026-09-30', $task->scheduled_date->toDateString());
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
    }

    /** @return array<string, array{string, bool}> */
    public static function dueTodayClock(): array
    {
        return [
            '14:29' => ['2026-09-30 14:29:59', false],
            '14:30' => ['2026-09-30 14:30:00', true],
            '15:59' => ['2026-09-30 15:59:00', true],
            '16:00 (now overdue)' => ['2026-09-30 16:00:00', true],
        ];
    }

    #[DataProvider('dueTodayClock')]
    public function test_order_due_today_gets_a_delay_task_from_two_thirty(string $moment, bool $creates): void
    {
        $this->freezeAt($moment);
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->toDateString());

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame($creates ? 1 : 0, $this->delayTasks($order));
    }

    public function test_order_due_in_the_future_never_gets_a_delay_task(): void
    {
        $this->freezeAt('2026-09-30 15:30:00');
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->addDay()->toDateString());

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame(0, $this->delayTasks($order));
    }

    /** @return array<string, array{CoreStatus, bool}> */
    public static function statusGate(): array
    {
        return [
            'adrian queue' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, true],
            'euraliz queue' => [CoreStatus::EURALIZ_ORDERS_RECEIVED, true],
            'cesar queue' => [CoreStatus::CESAR_ORDERS_RECEIVED, true],
            'working today' => [CoreStatus::TO_DO_TODAY, true],
            'entrante' => [CoreStatus::ENTRANTE, false],
            'camila' => [CoreStatus::ENVIADO_A_CAMILA, false],
            'client' => [CoreStatus::ENVIADO_AL_CLIENTE, false],
            'on hold' => [CoreStatus::ON_HOLD, false],
            'production' => [CoreStatus::EN_PRODUCCION, false],
            'archived' => [CoreStatus::ARCHIVED, false],
        ];
    }

    #[DataProvider('statusGate')]
    public function test_only_designer_queues_and_working_today_get_delay_tasks(CoreStatus $status, bool $creates): void
    {
        $order = $this->dueOrder($status, now()->subDays(2)->toDateString());

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame($creates ? 1 : 0, $this->delayTasks($order));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function exemptOrders(): array
    {
        return [
            'outside workspace' => [['in_workspace' => false]],
            'done today' => [['done_today' => true]],
            'archived at set' => [['archived_at' => '2026-09-01 10:00:00']],
            'paused substatus' => [['substatus' => Substatus::PAUSADO]],
        ];
    }

    #[DataProvider('exemptOrders')]
    public function test_exempt_orders_get_no_delay_task(array $overrides): void
    {
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->subDays(2)->toDateString(), $overrides);

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame(0, $this->delayTasks($order));
    }

    public function test_existing_open_delay_task_is_made_urgent_instead_of_duplicated(): void
    {
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->subDay()->toDateString());
        $existing = $this->makeTask($order, [
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'priority' => 'normal',
            'scheduled_date' => '2026-09-25',
        ]);

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame(1, $this->delayTasks($order));
        $existing->refresh();
        $this->assertSame('urgent', $existing->priority);
        $this->assertSame('2026-09-25', $existing->scheduled_date->toDateString());
    }

    public function test_a_completed_delay_task_does_not_prevent_a_new_one(): void
    {
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->subDay()->toDateString());
        $this->makeTask($order, [
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertSame(2, $this->delayTasks($order));
    }

    public function test_poner_en_alta_orders_never_notify_delays_and_lose_pending_delay_tasks(): void
    {
        $order = $this->dueOrder(CoreStatus::ADRIAN_ORDERS_RECEIVED, now()->subDays(2)->toDateString(), ['substatus' => Substatus::PONER_EN_ALTA]);
        $pending = $this->makeTask($order, ['title' => 'Enviar correo de atraso preventivo', 'type' => RelatedTaskType::CORREO_ATRASO]);
        $done = $this->makeTask($order, ['title' => 'Old delay mail', 'type' => RelatedTaskType::CORREO_ATRASO, 'status' => 'done', 'completed_at' => now()]);

        $this->engine()->checkAndCreateOverdueTask($order);

        $this->assertNull(RelatedTask::withTrashed()->find($pending->id));
        $this->assertNotNull(RelatedTask::find($done->id));
        $this->assertSame(1, $this->delayTasks($order));
    }

    public function test_dismiss_triggered_subtasks_only_removes_open_system_tasks(): void
    {
        $order = $this->dueOrder(CoreStatus::TO_DO_TODAY, now()->toDateString());
        $welcome = $this->makeTask($order, ['title' => 'w', 'type' => RelatedTaskType::BIENVENIDA]);
        $followClient = $this->makeTask($order, ['title' => 'fc', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE]);
        $followCamila = $this->makeTask($order, ['title' => 'fcam', 'type' => RelatedTaskType::FOLLOW_UP_CAMILA]);
        $resolver = $this->makeTask($order, ['title' => 'r', 'type' => RelatedTaskType::RESOLVER]);
        $triggered = $this->makeTask($order, ['title' => 't', 'type' => RelatedTaskType::PONER_ALTA, 'trigger_type' => 'ORDER_APPROVED']);
        $requestInfo = $this->makeTask($order, ['title' => 'si', 'type' => RelatedTaskType::SOLICITAR_INFO]);
        $plain = $this->makeTask($order, ['title' => 'p']);
        $done = $this->makeTask($order, ['title' => 'd', 'type' => RelatedTaskType::RESOLVER, 'status' => 'done', 'completed_at' => now()]);

        $this->engine()->dismissTriggeredSubtasks($order, [RelatedTaskType::FOLLOW_UP_CAMILA]);

        foreach ([$welcome, $followClient, $resolver, $triggered] as $removed) {
            $this->assertNull(RelatedTask::withTrashed()->find($removed->id), $removed->title);
        }
        foreach ([$followCamila, $requestInfo, $plain, $done] as $kept) {
            $this->assertNotNull(RelatedTask::find($kept->id), $kept->title);
        }
    }
}
