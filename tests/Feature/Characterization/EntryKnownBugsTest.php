<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index;
use App\Livewire\Orders\CreateOrderModal;
use App\Livewire\Orders\OrderDetailModal;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each test asserts the CORRECT behavior and is skipped until the rebuild fixes it.
 */
class EntryKnownBugsTest extends EntryTestCase
{
    /**
     * @return array<string, array{0: CoreStatus}>
     */
    public static function terminalStatuses(): array
    {
        return [
            'camila' => [CoreStatus::ENVIADO_A_CAMILA],
            'client' => [CoreStatus::ENVIADO_AL_CLIENTE],
            'production' => [CoreStatus::EN_PRODUCCION],
            'on hold' => [CoreStatus::ON_HOLD],
            'archived' => [CoreStatus::ARCHIVED],
        ];
    }

    #[DataProvider('terminalStatuses')]
    public function test_modal_created_subtask_survives_the_order_changing_status(CoreStatus $target): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: subtasks added in the order modal are typed RESOLVER and get force-deleted by handleStatusChanged.');

        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newTaskTitle', 'Ajustar colores')
            ->set('newTaskDate', '2026-03-05')
            ->call('addTask');

        $task = $order->relatedTasks()->where('title', 'Ajustar colores')->first();
        $this->assertNotNull($task);

        app(AutomationEngine::class)->handleStatusChanged($order->fresh(), CoreStatus::ADRIAN_ORDERS_RECEIVED, $target);

        $this->assertNotNull(RelatedTask::withTrashed()->find($task->id));
        $this->assertDatabaseHas('related_tasks', ['id' => $task->id]);
    }

    public function test_save_order_due_date_history_records_the_previous_date(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: saveOrder updates the order before SlaEngine reads previous, so previous_due_date equals new_due_date.');

        $order = $this->makeOrder(['current_due_date' => '2026-03-10']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id, true)
            ->set('editDueDate', '2026-03-20')
            ->call('saveOrder');

        $this->assertDatabaseHas('due_date_histories', [
            'order_id' => $order->id,
            'previous_due_date' => '2026-03-10 00:00:00',
            'new_due_date' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_duplicate_current_order_runs_created_automations(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: duplicateCurrentOrder skips handleOrderCreated, so no SLA dates, welcome task or ORDER_CREATED event.');

        $order = $this->makeOrder();

        Livewire::test(OrderDetailModal::class)->call('openModal', $order->id)->call('duplicateCurrentOrder');

        $copy = Order::where('id', '!=', $order->id)->first();
        $this->assertNotNull($copy->original_due_date);
        $this->assertDatabaseHas('related_tasks', ['order_id' => $copy->id, 'type' => RelatedTaskType::BIENVENIDA->value]);
        $this->assertContains('ORDER_CREATED', $this->eventTypes($copy));
    }

    public function test_create_order_keeps_the_due_date_the_user_chose(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: handleOrderCreated overwrites the chosen due date with the SLA default.');

        Livewire::test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'ACME')
            ->set('taskName', 'LOGO')
            ->set('dueDate', '2026-03-20')
            ->call('save');

        $this->assertSame('2026-03-20', Order::first()->current_due_date->toDateString());
    }

    public function test_hoy_mark_done_today_does_not_reach_production_without_approval(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: Hoy markDoneToday moves a PONER EN ALTA order to production without the approval gate.');

        $order = $this->makeOrder(['substatus' => Substatus::PONER_EN_ALTA, 'approved' => false]);

        Livewire::test(Index::class)->call('markDoneToday', $order->id);

        $this->assertNotSame(CoreStatus::EN_PRODUCCION, $order->fresh()->core_status);
    }

    public function test_planner_move_multiple_subtasks_goes_through_status_automations(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: moveMultipleSubtasks changes core_status without handleStatusChanged (no event, no origin handling).');

        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => '2026-03-05']);

        Livewire::test(WeeklyPlanner::class)->call('moveMultipleSubtasks', [$task->id], '2026-03-04');

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_planner_reschedule_of_archived_order_goes_through_status_automations(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: rescheduleSubtask reopens an archived order without handleStatusChanged.');

        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()]);
        $task = $this->makeTask($order, ['scheduled_date' => '2026-03-05']);

        Livewire::test(WeeklyPlanner::class)->call('rescheduleSubtask', $task->id, '2026-03-04');

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_planner_unschedule_order_goes_through_status_automations(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: unscheduleOrder moves the order to the designer queue without handleStatusChanged.');

        $order = $this->makeOrder(['scheduled_date' => '2026-03-04']);

        Livewire::test(WeeklyPlanner::class)->call('unscheduleOrder', $order->id);

        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_planner_bulk_complete_logs_each_completion_once(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: bulkToggleComplete logs SUBTASK_COMPLETED twice (observer plus the component).');

        $order = $this->makeOrder();
        $task = $this->makeTask($order, ['scheduled_date' => '2026-03-05']);

        Livewire::test(WeeklyPlanner::class)->call('bulkToggleComplete', [$task->id], true);

        $this->assertSame(1, $order->events()->where('event_type', 'SUBTASK_COMPLETED')->count());
    }

    public function test_approval_without_estimate_completes_and_waits_for_estimate(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: submitApproval with estimateApproved=false recurses until memory is exhausted (FALTA APROBACIÓN DE ESTIMADO observer loop).');

        $order = $this->makeOrder();

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->set('approvalComment', 'Falta estimado')
            ->set('estimateApproved', false)
            ->call('submitApproval');

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::FALTA_APROBACION_ESTIMADO, $order->substatus);
    }
}
