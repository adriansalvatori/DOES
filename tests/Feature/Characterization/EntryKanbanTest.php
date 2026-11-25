<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Kanban\Board;
use App\Livewire\Kanban\Column;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

class EntryKanbanTest extends EntryTestCase
{
    public function test_move_to_designer_queue_resets_done_today_and_logs_status_change(): void
    {
        $order = $this->makeOrder(['done_today' => true]);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ADRIAN_ORDERS_RECEIVED->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertFalse($order->done_today);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'previous_value' => 'TO DO TODAY',
            'new_value' => 'ADRIAN ORDERS RECEIVED',
            'actor' => 'Entry Tester',
        ]);
    }

    public function test_move_to_working_today_schedules_for_today_and_remembers_origin(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::TO_DO_TODAY->value);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame('2026-03-04', $order->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->origin_core_status);
        $this->assertFalse($order->done_today);
    }

    public function test_move_to_camila_flags_done_today_resets_approval_and_creates_follow_up(): void
    {
        $order = $this->makeOrder(['approved' => true, 'measures_confirmed' => true, 'estimate_approved' => true]);
        $welcome = $this->makeTask($order, ['type' => RelatedTaskType::BIENVENIDA, 'title' => 'Bienvenida']);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ENVIADO_A_CAMILA->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->core_status);
        $this->assertTrue($order->done_today);
        $this->assertFalse($order->approved);
        $this->assertFalse($order->measures_confirmed);
        $this->assertFalse($order->estimate_approved);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA->value,
            'trigger_type' => 'CAMILA_TRANSITION',
        ]);
        $this->assertDatabaseMissing('related_tasks', ['id' => $welcome->id]);
        $this->assertContains('APPROVAL_RESET', $this->eventTypes($order));
    }

    public function test_move_to_client_sets_waiting_substatus_and_sent_timestamp(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ENVIADO_AL_CLIENTE->value);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
        $this->assertNotNull($order->last_sent_to_client_at);
        $this->assertNull($order->client_last_response);
        $this->assertContains('ORDER_SENT_TO_CLIENT', $this->eventTypes($order));
    }

    public function test_move_approved_order_to_production_sets_sent_in_alta(): void
    {
        $order = $this->makeOrder(['approved' => true]);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_move_unapproved_order_to_production_redirects_to_approval_and_changes_nothing(): void
    {
        $order = $this->makeOrder(['approved' => false]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertSame([], $this->eventTypes($order));
    }

    public function test_move_to_archived_opens_archive_modal_without_changing_state(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ARCHIVED->value)
            ->assertSet('showArchiveModal', true)
            ->assertSet('pendingArchiveOrderId', $order->id)
            ->assertSet('archiveSubstatus', Substatus::FINALIZADA->value);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_move_to_on_hold_opens_on_hold_modal_without_changing_state(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ON_HOLD->value)
            ->assertSet('showOnHoldModal', true)
            ->assertSet('pendingOnHoldOrderId', $order->id);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_move_out_of_on_hold_opens_resume_modal_without_changing_state(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ON_HOLD]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::TO_DO_TODAY->value)
            ->assertSet('showResumeModal', true)
            ->assertSet('pendingResumeOrderId', $order->id)
            ->assertSet('pendingResumeNewStatus', CoreStatus::TO_DO_TODAY->value);

        $this->assertSame(CoreStatus::ON_HOLD, $order->fresh()->core_status);
    }

    public function test_move_to_blocked_column_opens_block_modal_without_changing_state(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ENTRANTE->value)
            ->assertSet('showBlockModal', true)
            ->assertSet('pendingBlockOrderId', $order->id);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_confirm_block_blocks_order_and_requests_customer_service_task(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ENTRANTE->value)
            ->set('blockReason', 'FALTA LOGO')
            ->set('blockComment', 'Sin logo')
            ->set('requireCustomerService', true)
            ->call('confirmBlock')
            ->assertSet('showBlockModal', false)
            ->assertSet('pendingBlockOrderId', null);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $this->assertSame(BlockingReason::FALTA_LOGO, $order->blocking_reason);
        $this->assertTrue((bool) $order->customer_service_required);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::SOLICITAR_INFO->value,
            'status' => 'todo',
        ]);
        $this->assertContains('ORDER_BLOCKED', $this->eventTypes($order));
    }

    public function test_confirm_block_without_pending_order_does_nothing(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)->call('confirmBlock');

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_confirm_unblock_returns_order_to_designer_queue_and_completes_resolver_tasks(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');
        $resolver = $this->makeTask($order, ['type' => RelatedTaskType::RESOLVER, 'title' => 'Resolver medidas']);

        Livewire::test(Board::class)
            ->call('openUnblockModal', $order->id)
            ->assertSet('showUnblockModal', true)
            ->set('unblockReason', 'Cliente envió medidas')
            ->call('confirmUnblock')
            ->assertSet('showUnblockModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertNull($order->blocking_reason);
        $this->assertSame('done', $resolver->fresh()->status);
        $this->assertContains('ORDER_UNBLOCKED', $this->eventTypes($order));
    }

    public function test_confirm_unblock_requires_a_reason(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(Board::class)
            ->call('openUnblockModal', $order->id)
            ->set('unblockReason', 'x')
            ->call('confirmUnblock')
            ->assertHasErrors(['unblockReason']);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
    }

    public function test_confirm_on_hold_pauses_order_and_logs_reason(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ON_HOLD->value)
            ->set('onHoldReason', 'Cliente en vacaciones')
            ->call('confirmOnHold')
            ->assertSet('showOnHoldModal', false)
            ->assertSet('pendingOnHoldOrderId', null);

        $order->refresh();
        $this->assertSame(CoreStatus::ON_HOLD, $order->core_status);
        $this->assertSame(Substatus::PAUSADO, $order->substatus);
        $this->assertFalse($order->done_today);
        $event = $order->events()->where('event_type', 'MOVED_TO_ON_HOLD')->first();
        $this->assertNotNull($event);
        $this->assertSame('Cliente en vacaciones', $event->metadata['reason']);
    }

    public function test_confirm_on_hold_validates_reason(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ON_HOLD->value)
            ->set('onHoldReason', 'a')
            ->call('confirmOnHold')
            ->assertHasErrors(['onHoldReason']);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_confirm_resume_restarts_two_weekday_sla_and_logs_event(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::ON_HOLD,
            'substatus' => Substatus::PAUSADO,
            'current_due_date' => '2026-02-20',
        ]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::TO_DO_TODAY->value)
            ->set('resumeReason', 'Cliente respondió')
            ->call('confirmResume')
            ->assertSet('showResumeModal', false)
            ->assertSet('pendingResumeOrderId', null);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertSame('2026-03-06', $order->current_due_date->toDateString());
        $this->assertDatabaseHas('due_date_histories', [
            'order_id' => $order->id,
            'previous_due_date' => '2026-02-20 00:00:00',
            'new_due_date' => '2026-03-06 00:00:00',
            'trigger_event' => 'RESUMED_FROM_ON_HOLD',
        ]);
        $this->assertContains('RESUMED_FROM_ON_HOLD', $this->eventTypes($order));
    }

    public function test_confirm_resume_to_production_without_approval_redirects_to_approval(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ON_HOLD, 'approved' => false]);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::EN_PRODUCCION->value)
            ->set('resumeReason', 'Reanudar')
            ->call('confirmResume')
            ->assertSet('showResumeModal', false)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertSame(CoreStatus::ON_HOLD, $order->fresh()->core_status);
    }

    public function test_confirm_archive_archives_with_chosen_substatus(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ARCHIVED->value)
            ->set('archiveSubstatus', Substatus::CANCELADA->value)
            ->call('confirmArchive')
            ->assertSet('showArchiveModal', false)
            ->assertSet('pendingArchiveOrderId', null);

        $order->refresh();
        $this->assertSame(CoreStatus::ARCHIVED, $order->core_status);
        $this->assertSame(Substatus::CANCELADA, $order->substatus);
        $this->assertNotNull($order->archived_at);
        $this->assertContains('CORE_STATUS_CHANGED', $this->eventTypes($order));
    }

    public function test_toggle_done_today_flips_flag_and_dismisses_triggered_tasks_when_set(): void
    {
        $order = $this->makeOrder(['done_today' => false]);
        $triggered = $this->makeTask($order, ['type' => RelatedTaskType::CORREO_ATRASO, 'title' => 'Correo atraso', 'trigger_type' => 'OVERDUE']);
        $manual = $this->makeTask($order, ['title' => 'Trabajo manual']);

        $this->toggleDoneToday($order);

        $this->assertTrue($order->fresh()->done_today);
        $this->assertDatabaseMissing('related_tasks', ['id' => $triggered->id]);
        $this->assertDatabaseHas('related_tasks', ['id' => $manual->id]);

        $this->toggleDoneToday($order);
        $this->assertFalse($order->fresh()->done_today);
    }

    /**
     * The toggle lives on the board or, after the column split, on a column component.
     */
    private function toggleDoneToday(Order $order): void
    {
        if (method_exists(Board::class, 'toggleDoneToday')) {
            Livewire::test(Board::class)->call('toggleDoneToday', $order->id);

            return;
        }

        Livewire::test(Column::class, ['status' => $order->fresh()->core_status->value])
            ->call('toggleDoneToday', $order->id);
    }

    public function test_toggle_task_complete_marks_done_and_back_to_todo(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);

        $component = Livewire::test(Board::class)->call('toggleTaskComplete', $task->id);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $component->call('toggleTaskComplete', $task->id);

        $task->refresh();
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_delete_task_soft_deletes(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);

        Livewire::test(Board::class)->call('deleteTask', $task->id);

        $this->assertSoftDeleted('related_tasks', ['id' => $task->id]);
    }

    public function test_duplicate_order_only_dispatches_event_and_creates_nothing(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)
            ->call('duplicateOrder', $order->id)
            ->assertDispatched('open-duplicate-order', orderId: $order->id);

        $this->assertSame(1, Order::count());
    }

    public function test_trash_order_soft_deletes_and_logs_event(): void
    {
        $order = $this->makeOrder();

        Livewire::test(Board::class)->call('trashOrder', $order->id);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'ORDER_TRASHED',
            'previous_value' => 'TO DO TODAY',
            'new_value' => 'TRASHED',
        ]);
    }

    public function test_trash_order_is_forbidden_for_users_who_cannot_delete(): void
    {
        $order = $this->makeOrder();
        $this->actingAs(User::factory()->sales()->create());

        Livewire::test(Board::class)->call('trashOrder', $order->id)->assertForbidden();

        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_move_pushes_card_to_trello_when_linked(): void
    {
        config(['services.trello.token' => 'test-token']);
        Http::fake(['api.trello.com/*' => Http::response(['id' => 'card-1'], 200)]);
        $order = $this->makeOrder(['trello_card_id' => 'card-1']);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ENVIADO_AL_CLIENTE->value);

        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/cards/card-1'));
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    public function test_trello_failure_does_not_roll_back_the_move(): void
    {
        config(['services.trello.token' => 'test-token']);
        Http::fake(['api.trello.com/*' => Http::response('boom', 500)]);
        $order = $this->makeOrder(['trello_card_id' => 'card-2']);

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ENVIADO_A_CAMILA->value);

        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->fresh()->core_status);
    }

    public function test_orders_without_trello_card_make_no_http_calls(): void
    {
        Http::fake();
        $order = $this->makeOrder();

        Livewire::test(Board::class)->call('moveOrder', $order->id, CoreStatus::ENVIADO_A_CAMILA->value);

        Http::assertNothingSent();
    }
}
