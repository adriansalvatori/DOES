<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

class EntryOrderDetailTest extends EntryTestCase
{
    private function modal(Order $order, bool $edit = false)
    {
        return Livewire::test(OrderDetailModal::class)->call('openModal', $order->id, $edit);
    }

    public function test_change_core_status_to_client_flags_done_today_and_waits_for_client(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('changeCoreStatus', CoreStatus::ENVIADO_AL_CLIENTE->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_change_core_status_to_designer_queue_logs_event(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('changeCoreStatus', CoreStatus::CESAR_ORDERS_RECEIVED->value);

        $this->assertSame(CoreStatus::CESAR_ORDERS_RECEIVED, $order->fresh()->core_status);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_change_core_status_to_production_without_approval_opens_approval_modal(): void
    {
        $order = $this->makeOrder(['approved' => false]);

        $this->modal($order)
            ->call('changeCoreStatus', CoreStatus::EN_PRODUCCION->value)
            ->assertSet('showApprovalModal', true)
            ->assertSet('pendingProductionStatus', CoreStatus::EN_PRODUCCION->value);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_change_core_status_to_production_when_approved(): void
    {
        $order = $this->makeOrder(['approved' => true]);

        $this->modal($order)->call('changeCoreStatus', CoreStatus::EN_PRODUCCION->value);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_change_core_status_redirects_to_block_hold_archive_and_resume_modals(): void
    {
        $order = $this->makeOrder();
        $component = $this->modal($order);

        $component->call('changeCoreStatus', CoreStatus::ENTRANTE->value)->assertSet('showBlockModal', true);
        $component->call('changeCoreStatus', CoreStatus::ON_HOLD->value)->assertSet('showOnHoldModal', true);
        $component->call('changeCoreStatus', CoreStatus::ARCHIVED->value)->assertSet('showArchiveModal', true);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);

        $held = $this->makeOrder(['core_status' => CoreStatus::ON_HOLD]);
        $this->modal($held)
            ->call('changeCoreStatus', CoreStatus::TO_DO_TODAY->value)
            ->assertSet('showResumeModal', true)
            ->assertSet('pendingResumeStatus', CoreStatus::TO_DO_TODAY->value);
        $this->assertSame(CoreStatus::ON_HOLD, $held->fresh()->core_status);
    }

    public function test_change_core_status_ignores_unknown_values(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('changeCoreStatus', 'NOPE');

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_save_order_updates_fields_and_logs_substatus_change(): void
    {
        $order = $this->makeOrder(['company_name' => 'VIEJA', 'task_name' => 'VIEJO', 'wo_number' => 'WO 100']);

        $this->modal($order, true)
            ->set('editCompanyName', 'nueva empresa')
            ->set('editTaskName', 'nueva tarea')
            ->set('editWoNumber', '555')
            ->set('editSubstatus', Substatus::CAMBIOS_CLIENTE->value)
            ->call('saveOrder')
            ->assertSet('isEditing', false)
            ->assertDispatched('order-updated');

        $order->refresh();
        $this->assertSame('NUEVA TAREA', $order->task_name);
        $this->assertSame('WO 555', $order->wo_number);
        $this->assertStringContainsString('NUEVA EMPRESA', mb_strtoupper($order->company_name));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'SUBSTATUS_CHANGED',
            'new_value' => Substatus::CAMBIOS_CLIENTE->value,
        ]);
    }

    public function test_save_order_with_new_due_date_updates_it_and_writes_due_date_history_and_event(): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-03-10']);

        $this->modal($order, true)
            ->set('editDueDate', '2026-03-20')
            ->call('saveOrder');

        $this->assertSame('2026-03-20', $order->fresh()->current_due_date->toDateString());
        $this->assertDatabaseHas('due_date_histories', [
            'order_id' => $order->id,
            'new_due_date' => '2026-03-20 00:00:00',
            'trigger_event' => 'MANUAL_UPDATE',
            'created_by' => 'Entry Tester',
        ]);
        $this->assertContains('DUE_DATE_CHANGED', $this->eventTypes($order));
    }

    public function test_save_order_to_production_without_approval_opens_approval_and_saves_nothing(): void
    {
        $order = $this->makeOrder(['approved' => false, 'task_name' => 'ORIGINAL']);

        $this->modal($order, true)
            ->set('editCoreStatus', CoreStatus::EN_PRODUCCION->value)
            ->set('editTaskName', 'cambiado')
            ->call('saveOrder')
            ->assertSet('showApprovalModal', true);

        $order->refresh();
        $this->assertSame('ORIGINAL', $order->task_name);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
    }

    public function test_save_order_to_on_hold_needs_a_reason_first(): void
    {
        $order = $this->makeOrder();

        $this->modal($order, true)
            ->set('editCoreStatus', CoreStatus::ON_HOLD->value)
            ->call('saveOrder')
            ->assertSet('showOnHoldModal', true);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_save_order_with_designer_selection_syncs_designers(): void
    {
        $order = $this->makeOrder();

        $this->modal($order, true)
            ->set('editDesignerIds', [$this->cesar->id, $this->euraliz->id])
            ->call('saveOrder');

        $order->refresh();
        $this->assertSame($this->cesar->id, $order->designer_id);
        $this->assertEqualsCanonicalizing([$this->cesar->id, $this->euraliz->id], $order->designers()->pluck('designers.id')->all());
    }

    public function test_submit_approval_requires_support(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('openApprovalModal')->call('submitApproval')->assertHasErrors(['approvalSupport']);

        $this->assertFalse($order->fresh()->approved);
    }

    public function test_submit_approval_moves_order_to_designer_queue_with_alta_task(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)
            ->call('openApprovalModal')
            ->set('approvalComment', 'OK por cliente')
            ->call('submitApproval')
            ->assertSet('showModal', false);

        $order->refresh();
        $this->assertTrue($order->approved);
        $this->assertSame('cliente', $order->approval_type);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
        $this->assertSame('2026-03-05', $order->current_due_date->toDateString());
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::PONER_ALTA->value,
            'status' => 'todo',
        ]);
        $this->assertContains('ORDER_APPROVED', $this->eventTypes($order));
    }

    public function test_submit_approval_targeting_production_sends_order_to_production(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)
            ->call('openApprovalModal', CoreStatus::EN_PRODUCCION->value)
            ->set('approvalComment', 'Aprobado')
            ->call('submitApproval');

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertDatabaseHas('related_tasks', ['order_id' => $order->id, 'type' => RelatedTaskType::PONER_ALTA->value, 'status' => 'done']);
    }

    public function test_submit_approval_without_measures_blocks_order_with_resolver_task(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)
            ->call('openApprovalModal')
            ->set('approvalComment', 'Aprobado sin medidas')
            ->set('measuresConfirmed', false)
            ->call('submitApproval');

        $order->refresh();
        $this->assertTrue($order->approved);
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $this->assertSame(BlockingReason::FALTAN_MEDIDAS, $order->blocking_reason);
        $this->assertDatabaseHas('related_tasks', ['order_id' => $order->id, 'type' => RelatedTaskType::RESOLVER->value]);
    }

    public function test_confirm_block_and_unblock(): void
    {
        $order = $this->makeOrder();

        $component = $this->modal($order)
            ->call('openBlockModal')
            ->set('blockReason', 'FALTA LOGO')
            ->call('confirmBlock')
            ->assertSet('showBlockModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(BlockingReason::FALTA_LOGO, $order->blocking_reason);

        $component->call('openUnblockModal')->set('unblockReason', 'Llegó el logo')->call('confirmUnblock')->assertSet('showUnblockModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->blocking_reason);
    }

    public function test_confirm_unblock_requires_reason(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        $this->modal($order)->call('confirmUnblock')->assertHasErrors(['unblockReason']);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
    }

    public function test_confirm_on_hold_then_resume(): void
    {
        $order = $this->makeOrder(['current_due_date' => '2026-02-20']);

        $component = $this->modal($order)
            ->call('openOnHoldModal')
            ->set('onHoldReason', 'Cliente pausó')
            ->call('confirmOnHold');

        $order->refresh();
        $this->assertSame(CoreStatus::ON_HOLD, $order->core_status);
        $this->assertSame(Substatus::PAUSADO, $order->substatus);
        $this->assertContains('MOVED_TO_ON_HOLD', $this->eventTypes($order));

        $component->call('changeCoreStatus', CoreStatus::TO_DO_TODAY->value)
            ->set('resumeReason', 'Retomamos')
            ->call('confirmResume');

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertSame('2026-03-06', $order->current_due_date->toDateString());
        $this->assertContains('RESUMED_FROM_ON_HOLD', $this->eventTypes($order));
    }

    public function test_confirm_on_hold_requires_reason(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('openOnHoldModal')->call('confirmOnHold')->assertHasErrors(['onHoldReason']);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_confirm_archive_archives_with_substatus_and_closes_modal(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)
            ->call('openArchiveModal')
            ->set('archiveSubstatus', Substatus::FINALIZADA->value)
            ->call('confirmArchive')
            ->assertSet('showModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $order->core_status);
        $this->assertSame(Substatus::FINALIZADA, $order->substatus);
        $this->assertNotNull($order->archived_at);
    }

    public function test_move_to_backlog_and_add_to_workspace_directly(): void
    {
        $order = $this->makeOrder(['company_name' => 'ACME']);

        $this->modal($order)->call('moveToBacklog')->assertSet('showModal', false);
        $this->assertFalse($order->fresh()->in_workspace);

        $this->modal($order)->call('addToWorkspaceDirectly');
        $order->refresh();
        $this->assertTrue($order->in_workspace);
        $this->assertNotNull($order->client_id);
    }

    public function test_delete_order_soft_deletes_and_closes_modal(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->call('deleteOrder')->assertSet('showModal', false);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_delete_order_is_forbidden_without_permission(): void
    {
        $order = $this->makeOrder();
        $this->actingAs(User::factory()->sales()->create());

        $this->modal($order)->call('deleteOrder')->assertForbidden();

        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_toggle_done_today(): void
    {
        $order = $this->makeOrder(['done_today' => false]);

        $component = $this->modal($order)->call('toggleDoneToday');
        $this->assertTrue($order->fresh()->done_today);

        $component->call('toggleDoneToday');
        $this->assertFalse($order->fresh()->done_today);
    }

    public function test_add_task_creates_resolver_typed_subtask_and_pulls_order_into_working_today(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        $this->modal($order)
            ->set('newTaskTitle', 'Ajustar colores')
            ->set('newTaskDate', '2026-03-04')
            ->call('addTask')
            ->assertSet('newTaskTitle', '');

        $task = $order->relatedTasks()->first();
        $this->assertSame(RelatedTaskType::RESOLVER, $task->type);
        $this->assertSame('Ajustar colores', $task->title);
        $this->assertSame('todo', $task->status);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_add_task_with_empty_title_does_nothing(): void
    {
        $order = $this->makeOrder();

        $this->modal($order)->set('newTaskTitle', '  ')->call('addTask');

        $this->assertSame(0, $order->relatedTasks()->count());
    }

    public function test_add_task_on_blocked_order_prompts_before_creating(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        $this->modal($order)
            ->set('newTaskTitle', 'Rediseño')
            ->call('addTask')
            ->assertSet('showSubtaskWarningModal', true);

        $this->assertSame(0, $order->relatedTasks()->count());
    }

    public function test_toggle_task_status_marks_done_and_reopens(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order, ['scheduled_date' => '2026-03-05']);

        $component = $this->modal($order)->call('toggleTaskStatus', $task->id);
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $component->call('toggleTaskStatus', $task->id);
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_completing_the_last_task_of_an_order_returning_from_client_auto_returns_it(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);
        $task = $this->makeTask($order, ['scheduled_date' => today()]);

        $this->modal($order)->call('toggleTaskStatus', $task->id);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertNull($order->origin_core_status);
        $this->assertContains('AUTO_RETURNED_AFTER_SUBTASK_COMPLETION', $this->eventTypes($order));
    }

    public function test_toggle_poner_en_alta_task_on_unapproved_order_opens_approval(): void
    {
        $order = $this->makeOrder(['approved' => false]);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        $this->modal($order)->call('toggleTaskStatus', $alta->id)->assertSet('showApprovalModal', true);

        $this->assertSame('todo', $alta->fresh()->status);
    }

    public function test_duplicate_current_order_creates_a_copy_and_switches_to_it(): void
    {
        $order = $this->makeOrder(['task_name' => 'LOGO', 'wo_number' => 'WO 7']);

        $component = $this->modal($order)->call('duplicateCurrentOrder');

        $copy = Order::where('id', '!=', $order->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('LOGO (COPIA)', $copy->task_name);
        $this->assertSame('WO 7 (Copia)', $copy->wo_number);
        $this->assertSame($order->core_status, $copy->core_status);
        $this->assertTrue($copy->in_workspace);
        $this->assertContains('ORDER_DUPLICATED', $this->eventTypes($copy));
        $component->assertSet('orderId', $copy->id);
    }
}
