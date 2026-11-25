<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\RelatedTask;
use Livewire\Livewire;

class EntryPlannerTest extends EntryTestCase
{
    private const TODAY = '2026-03-04';

    private const TOMORROW = '2026-03-05';

    public function test_schedule_order_for_today_creates_subtask_and_moves_order_to_working_today(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'task_name' => 'Menu Board']);

        Livewire::test(WeeklyPlanner::class)->call('scheduleOrder', $order->id, self::TODAY);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(self::TODAY, $order->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->origin_core_status);
        $this->assertTrue($order->in_workspace);

        $task = $order->relatedTasks()->first();
        $this->assertSame(RelatedTaskType::SUBTASK, $task->type);
        $this->assertSame('todo', $task->status);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame(self::TODAY, $task->scheduled_date->toDateString());
        $this->assertTrue($task->is_work_task);

        $this->assertContains('SUBTASK_SCHEDULED', $this->eventTypes($order));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'previous_value' => 'ADRIAN ORDERS RECEIVED',
            'new_value' => 'TO DO TODAY',
        ]);
    }

    public function test_schedule_subtask_for_a_future_day_leaves_order_status_alone(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Modificar fonts', self::TOMORROW, $this->adrian->id);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->scheduled_date);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'Modificar fonts',
            'scheduled_date' => self::TOMORROW.' 00:00:00',
        ]);
    }

    public function test_schedule_subtask_for_today_on_order_sent_to_camila_reopens_it_as_camila_changes(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA, 'done_today' => true]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Ajustes Camila', self::TODAY, $this->adrian->id);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::CAMBIOS_CAMILA, $order->substatus);
        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->origin_core_status);
        $this->assertFalse($order->done_today);
    }

    public function test_schedule_subtask_on_archived_order_reopens_it(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Retoque', self::TODAY, $this->adrian->id);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::TICKET, $order->substatus);
        $this->assertNull($order->archived_at);
    }

    public function test_schedule_subtask_on_blocked_order_prompts_and_changes_nothing(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Rediseño', self::TODAY, $this->adrian->id)
            ->assertSet('showSubtaskWarningModal', true)
            ->assertSet('subtaskBlockResolution', 'unblock')
            ->assertSet('pendingSubtaskData.orderId', $order->id);

        $this->assertSame(0, $order->relatedTasks()->count());
        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
    }

    public function test_confirming_unblock_on_blocked_order_unblocks_confirms_measures_and_schedules(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Rediseño', self::TODAY, $this->adrian->id)
            ->call('confirmSubtaskWarning')
            ->assertSet('showSubtaskWarningModal', false)
            ->assertSet('pendingSubtaskData', []);

        $order->refresh();
        $this->assertNull($order->blocking_reason);
        $this->assertTrue($order->measures_confirmed);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertContains('ORDER_UNBLOCKED', $this->eventTypes($order));
        $this->assertDatabaseHas('related_tasks', ['order_id' => $order->id, 'title' => 'Rediseño']);
    }

    public function test_keeping_order_blocked_schedules_a_non_work_management_subtask(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTA LOGO', null, null, false, 'Usuario');

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Pedir logo', self::TODAY, $this->adrian->id)
            ->assertSet('subtaskBlockResolution', 'keep_blocked')
            ->call('confirmSubtaskWarning');

        $order->refresh();
        $this->assertSame(BlockingReason::FALTA_LOGO, $order->blocking_reason);
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $task = $order->relatedTasks()->first();
        $this->assertFalse($task->is_work_task);
    }

    public function test_cancel_subtask_warning_discards_pending_schedule(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Rediseño', self::TODAY, $this->adrian->id)
            ->call('cancelSubtaskWarning')
            ->assertSet('showSubtaskWarningModal', false)
            ->assertSet('pendingSubtaskData', []);

        $this->assertSame(0, $order->relatedTasks()->count());
    }

    public function test_design_work_on_approved_order_prompts_and_invalidating_resets_approval(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'approved' => true,
            'approved_at' => now()->subDay(),
            'measures_confirmed' => true,
        ]);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Cambios del cliente', self::TODAY, $this->adrian->id)
            ->assertSet('showSubtaskWarningModal', true)
            ->assertSet('subtaskApprovalImpact', 'invalidate')
            ->call('confirmSubtaskWarning');

        $order->refresh();
        $this->assertFalse($order->approved);
        $this->assertFalse($order->measures_confirmed);
        $this->assertContains('APPROVAL_RESET', $this->eventTypes($order));
        $this->assertDatabaseMissing('related_tasks', ['id' => $alta->id]);
        $this->assertDatabaseHas('related_tasks', ['order_id' => $order->id, 'title' => 'Cambios del cliente']);
    }

    public function test_scheduling_a_note_without_order_creates_orphan_subtask(): void
    {
        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', null, 'Llamar proveedor', self::TOMORROW, $this->adrian->id);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => null,
            'title' => 'Llamar proveedor',
            'assignee_id' => $this->adrian->id,
        ]);
    }

    public function test_scheduling_past_the_due_date_raises_sla_warning_but_still_schedules(): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-03-04']);

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', $order->id, 'Trabajo largo', '2026-03-09', $this->adrian->id)
            ->assertSet('showSlaWarningModal', true)
            ->assertSet('slaWarningDetails.order_id', $order->id);

        $this->assertDatabaseHas('related_tasks', ['order_id' => $order->id, 'title' => 'Trabajo largo']);
    }

    public function test_reschedule_subtask_changes_date_assignee_and_records_undo_state(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => self::TOMORROW, 'assignee_id' => $this->adrian->id]);

        $component = Livewire::test(WeeklyPlanner::class)
            ->call('rescheduleSubtask', $task->id, '2026-03-06', $this->cesar->id)
            ->assertDispatched('order-updated');

        $task->refresh();
        $this->assertSame('2026-03-06', $task->scheduled_date->toDateString());
        $this->assertSame($this->cesar->id, $task->assignee_id);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
        $this->assertCount(1, $component->get('actionHistory'));
        $this->assertSame('move', $component->get('actionHistory')[0]['type']);
    }

    public function test_reschedule_work_subtask_to_today_pulls_order_into_working_today(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => self::TOMORROW, 'assignee_id' => $this->adrian->id]);

        Livewire::test(WeeklyPlanner::class)->call('rescheduleSubtask', $task->id, self::TODAY);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(self::TODAY, $order->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->origin_core_status);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_reschedule_to_a_later_date_than_due_date_raises_sla_warning(): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-03-05']);
        $task = $this->makeTask($order, ['scheduled_date' => self::TODAY]);

        Livewire::test(WeeklyPlanner::class)
            ->call('rescheduleSubtask', $task->id, '2026-03-10')
            ->assertSet('showSlaWarningModal', true);

        $this->assertSame('2026-03-10', $task->fresh()->scheduled_date->toDateString());
    }

    public function test_move_multiple_subtasks_moves_all_and_pulls_orders_into_working_today_for_today(): void
    {
        $orderA = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $orderB = $this->makeOrder(['core_status' => CoreStatus::CESAR_ORDERS_RECEIVED]);
        $taskA = $this->makeTask($orderA, ['scheduled_date' => self::TOMORROW]);
        $taskB = $this->makeTask($orderB, ['scheduled_date' => self::TOMORROW]);

        $component = Livewire::test(WeeklyPlanner::class)
            ->call('moveMultipleSubtasks', [$taskA->id, $taskB->id], self::TODAY, $this->euraliz->id);

        foreach ([$taskA, $taskB] as $task) {
            $task->refresh();
            $this->assertSame(self::TODAY, $task->scheduled_date->toDateString());
            $this->assertSame($this->euraliz->id, $task->assignee_id);
        }
        $this->assertSame(CoreStatus::TO_DO_TODAY, $orderA->fresh()->core_status);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $orderB->fresh()->core_status);
        $this->assertCount(1, $component->get('actionHistory'));
    }

    public function test_move_multiple_subtasks_to_a_future_day_keeps_order_statuses(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => self::TODAY]);

        Livewire::test(WeeklyPlanner::class)->call('moveMultipleSubtasks', [$task->id], '2026-03-06');

        $this->assertSame('2026-03-06', $task->fresh()->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->fresh()->core_status);
    }

    public function test_move_multiple_subtasks_with_no_ids_does_nothing(): void
    {
        $component = Livewire::test(WeeklyPlanner::class)->call('moveMultipleSubtasks', [], self::TODAY);

        $this->assertCount(0, $component->get('actionHistory'));
    }

    public function test_toggle_subtask_complete_marks_done_and_reopens(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order, ['scheduled_date' => self::TOMORROW]);

        $component = Livewire::test(WeeklyPlanner::class)->call('toggleSubtaskComplete', $task->id);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertContains('SUBTASK_COMPLETED', $this->eventTypes($order));

        $component->call('toggleSubtaskComplete', $task->id);

        $task->refresh();
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->completed_at);
        $this->assertNotContains('SUBTASK_COMPLETED', $this->eventTypes($order));
    }

    public function test_toggle_note_complete_asks_to_link_it_instead(): void
    {
        $note = RelatedTask::create(['order_id' => null, 'title' => 'Nota', 'type' => RelatedTaskType::SUBTASK, 'status' => 'todo']);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $note->id)
            ->assertDispatched('open-link-note-modal', taskId: $note->id, noteTitle: 'Nota');

        $this->assertSame('todo', $note->fresh()->status);
    }

    public function test_completing_alta_subtask_on_unapproved_order_opens_approval_and_stays_todo(): void
    {
        $order = $this->makeOrder(['approved' => false]);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $alta->id)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertSame('todo', $alta->fresh()->status);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_completing_alta_subtask_after_new_work_requires_reapproval(): void
    {
        $order = $this->makeOrder(['approved' => true, 'approved_at' => now()->subDay()]);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);
        $work = $this->makeTask($order, ['title' => 'Cambio posterior']);
        RelatedTask::query()->whereKey($work->id)->update(['status' => 'done', 'completed_at' => now()]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $alta->id)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertSame('todo', $alta->fresh()->status);
    }

    public function test_completing_alta_subtask_on_approved_order_sends_it_to_production(): void
    {
        $order = $this->makeOrder(['approved' => true, 'approved_at' => now()->subDay()]);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        Livewire::test(WeeklyPlanner::class)->call('toggleSubtaskComplete', $alta->id);

        $order->refresh();
        $this->assertSame('done', $alta->fresh()->status);
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertTrue($order->done_today);
        $this->assertContains('MOVED_TO_PRODUCTION', $this->eventTypes($order));
    }

    public function test_bulk_toggle_complete_marks_subtasks_done_and_skips_notes(): void
    {
        $order = $this->makeOrder();
        $a = $this->makeTask($order, ['title' => 'A', 'scheduled_date' => self::TOMORROW]);
        $b = $this->makeTask($order, ['title' => 'B', 'scheduled_date' => self::TOMORROW]);
        $note = RelatedTask::create(['order_id' => null, 'title' => 'Nota', 'type' => RelatedTaskType::SUBTASK, 'status' => 'todo']);

        Livewire::test(WeeklyPlanner::class)->call('bulkToggleComplete', [$a->id, $b->id, $note->id], true);

        $this->assertSame('done', $a->fresh()->status);
        $this->assertSame('done', $b->fresh()->status);
        $this->assertSame('todo', $note->fresh()->status);
    }

    public function test_bulk_toggle_complete_false_reopens_subtasks(): void
    {
        $order = $this->makeOrder();
        $a = $this->makeTask($order, ['title' => 'A', 'scheduled_date' => self::TOMORROW]);
        RelatedTask::query()->whereKey($a->id)->update(['status' => 'done', 'completed_at' => now()]);

        Livewire::test(WeeklyPlanner::class)->call('bulkToggleComplete', [$a->id], false);

        $a->refresh();
        $this->assertSame('todo', $a->status);
        $this->assertNull($a->completed_at);
    }

    public function test_unschedule_order_in_working_today_returns_it_to_designer_queue(): void
    {
        $order = $this->makeOrder(['scheduled_date' => self::TODAY]);

        Livewire::test(WeeklyPlanner::class)->call('unscheduleOrder', $order->id);

        $order->refresh();
        $this->assertNull($order->scheduled_date);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
    }

    public function test_unschedule_order_in_other_status_only_clears_the_date(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'scheduled_date' => self::TODAY]);

        Livewire::test(WeeklyPlanner::class)->call('unscheduleOrder', $order->id);

        $order->refresh();
        $this->assertNull($order->scheduled_date);
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
    }

    public function test_toggle_done_today_flips_the_flag(): void
    {
        $order = $this->makeOrder(['done_today' => false]);

        $component = Livewire::test(WeeklyPlanner::class)->call('toggleDoneToday', $order->id);
        $this->assertTrue($order->fresh()->done_today);

        $component->call('toggleDoneToday', $order->id);
        $this->assertFalse($order->fresh()->done_today);
    }

    public function test_delete_subtask_soft_deletes_and_undo_restores_it(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);

        $component = Livewire::test(WeeklyPlanner::class)->call('deleteSubtask', $task->id);

        $this->assertSoftDeleted('related_tasks', ['id' => $task->id]);

        $component->call('undoLastAction');

        $this->assertNull($task->fresh()->deleted_at);
    }

    public function test_undo_last_action_restores_previous_schedule_after_a_move(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);
        $task = $this->makeTask($order, ['scheduled_date' => self::TOMORROW, 'assignee_id' => $this->adrian->id, 'sort_order' => 3]);

        Livewire::test(WeeklyPlanner::class)
            ->call('rescheduleSubtask', $task->id, '2026-03-06', $this->cesar->id)
            ->call('undoLastAction');

        $task->refresh();
        $this->assertSame(self::TOMORROW, $task->scheduled_date->toDateString());
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame(3, $task->sort_order);
    }

    public function test_undo_last_action_without_history_restores_latest_trashed_subtask(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);
        $task->delete();

        Livewire::test(WeeklyPlanner::class)->call('undoLastAction');

        $this->assertNull($task->fresh()->deleted_at);
    }

    public function test_link_note_to_order_attaches_it_and_logs_event(): void
    {
        $order = $this->makeOrder(['in_workspace' => false]);
        $note = RelatedTask::create(['order_id' => null, 'title' => 'Nota suelta', 'type' => RelatedTaskType::SUBTASK, 'status' => 'todo']);

        Livewire::test(WeeklyPlanner::class)->call('linkSubtaskToOrder', $note->id, $order->id);

        $this->assertSame($order->id, $note->fresh()->order_id);
        $this->assertTrue($order->fresh()->in_workspace);
        $this->assertContains('SUBTASK_SCHEDULED', $this->eventTypes($order));
    }

    public function test_update_task_category_and_type(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);

        Livewire::test(WeeklyPlanner::class)
            ->call('updateTaskCategory', $task->id, 'management')
            ->call('updateTaskType', $task->id, true);

        $task->refresh();
        $this->assertTrue($task->is_work_task);
        $this->assertSame('new_design', $task->category->value);
    }
}
