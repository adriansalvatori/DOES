<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index;
use App\Models\RelatedTask;
use Livewire\Livewire;

class EntryHoyTest extends EntryTestCase
{
    public function test_mark_done_today_sets_flag_without_moving_a_regular_order(): void
    {
        $order = $this->makeOrder(['done_today' => false]);

        Livewire::test(Index::class)->call('markDoneToday', $order->id);

        $order->refresh();
        $this->assertTrue($order->done_today);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
    }

    public function test_mark_done_today_again_clears_the_flag(): void
    {
        $order = $this->makeOrder(['done_today' => true]);

        Livewire::test(Index::class)->call('markDoneToday', $order->id);

        $this->assertFalse($order->fresh()->done_today);
    }

    public function test_mark_done_today_with_completed_proof_task_sends_order_to_client(): void
    {
        $order = $this->makeOrder();
        $proof = $this->makeTask($order, ['title' => 'enviar proof al cliente']);
        RelatedTask::query()->whereKey($proof->id)->update(['status' => 'done', 'completed_at' => now()]);

        Livewire::test(Index::class)->call('markDoneToday', $order->id);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
        $this->assertNotNull($order->last_sent_to_client_at);
        $this->assertContains('ORDER_SENT_TO_CLIENT', $this->eventTypes($order));
    }

    public function test_mark_done_today_on_ready_for_alta_order_moves_it_to_production(): void
    {
        $order = $this->makeOrder(['substatus' => Substatus::PONER_EN_ALTA, 'approved' => true]);

        Livewire::test(Index::class)->call('markDoneToday', $order->id);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertFalse($order->done_today);
    }

    public function test_complete_task_marks_done_without_touching_the_order(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order);

        Livewire::test(Index::class)->call('completeTask', $task->id);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
        $this->assertContains('SUBTASK_COMPLETED', $this->eventTypes($order));
    }

    public function test_complete_proof_task_sends_done_today_order_to_client(): void
    {
        $order = $this->makeOrder(['done_today' => true]);
        $task = $this->makeTask($order, ['title' => 'Enviar proof al cliente']);

        Livewire::test(Index::class)->call('completeTask', $task->id);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertNotNull($order->last_sent_to_client_at);
    }

    public function test_complete_proof_task_keeps_order_while_other_work_is_pending_today(): void
    {
        $order = $this->makeOrder(['done_today' => false]);
        $task = $this->makeTask($order, ['title' => 'Enviar proof al cliente', 'scheduled_date' => today()]);
        $this->makeTask($order, ['title' => 'Otro trabajo', 'scheduled_date' => today()]);

        Livewire::test(Index::class)->call('completeTask', $task->id);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertFalse($order->done_today);
    }

    public function test_completing_the_only_proof_task_auto_flags_done_today_and_sends_to_client(): void
    {
        $order = $this->makeOrder(['done_today' => false]);
        $task = $this->makeTask($order, ['title' => 'Enviar proof al cliente']);

        Livewire::test(Index::class)->call('completeTask', $task->id);

        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
    }

    public function test_camila_request_changes_returns_order_to_working_today_with_adjustment_subtask(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA, 'done_today' => true, 'internal_revision_count' => 1]);
        $followUp = $this->makeTask($order, ['type' => RelatedTaskType::FOLLOW_UP_CAMILA, 'title' => 'Follow Up Camila']);

        Livewire::test(Index::class)
            ->call('openCamilaModal', $followUp->id)
            ->assertSet('showCamilaModal', true)
            ->set('camilaComment', 'Cambiar logo')
            ->call('camilaRequestChanges')
            ->assertSet('showCamilaModal', false)
            ->assertDispatched('order-updated');

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::CAMBIOS_CAMILA, $order->substatus);
        $this->assertSame(2, $order->internal_revision_count);
        $this->assertFalse($order->done_today);
        $this->assertSame('done', $followUp->fresh()->status);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => __('Camila\'s changes: :comment', ['comment' => 'Cambiar logo']),
            'status' => 'todo',
            'priority' => 'high',
        ]);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => 'CAMILA_CHANGES_REQUESTED']);
    }

    public function test_camila_pre_approve_returns_order_to_working_today_with_proof_subtask(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA, 'done_today' => true]);
        $followUp = $this->makeTask($order, ['type' => RelatedTaskType::FOLLOW_UP_CAMILA, 'title' => 'Follow Up Camila']);

        Livewire::test(Index::class)
            ->call('openCamilaModal', $followUp->id)
            ->call('camilaPreApprove')
            ->assertSet('showCamilaModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertFalse($order->done_today);
        $this->assertSame('done', $followUp->fresh()->status);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'title' => 'enviar proof al cliente (camila pre-approves)',
            'status' => 'todo',
        ]);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => 'CAMILA_PRE_APPROVED']);
    }

    public function test_camila_actions_without_a_selected_task_do_nothing(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA]);

        Livewire::test(Index::class)->call('camilaRequestChanges')->call('camilaPreApprove');

        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->fresh()->core_status);
    }

    public function test_unblock_order_returns_it_to_the_designer_queue(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(Index::class)
            ->call('openResolveModal', $order->id)
            ->assertSet('showResolveModal', true)
            ->set('resolveComment', 'Medidas recibidas')
            ->call('unblockOrder')
            ->assertSet('showResolveModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->blocking_reason);
        $unblock = $order->events()->where('event_type', 'ORDER_UNBLOCKED')->first();
        $this->assertSame('Medidas recibidas', $unblock->metadata['reason']);
        $this->assertSame('Entry Tester', $unblock->actor);
    }

    public function test_keep_order_blocked_logs_review_and_leaves_status(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(Index::class)
            ->call('openResolveModal', $order->id)
            ->set('resolveComment', 'Esperando al cliente')
            ->call('keepOrderBlocked')
            ->assertSet('showResolveModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $review = $order->events()->where('event_type', 'ORDER_BLOCK_REVIEWED')->first();
        $this->assertNotNull($review);
        $this->assertSame('Esperando al cliente', $review->metadata['comment']);
    }

    public function test_keep_order_blocked_requires_a_comment(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(Index::class)
            ->call('openResolveModal', $order->id)
            ->set('resolveComment', '')
            ->call('keepOrderBlocked')
            ->assertHasErrors(['resolveComment']);

        $this->assertDatabaseMissing('order_events', ['order_id' => $order->id, 'event_type' => 'ORDER_BLOCK_REVIEWED']);
    }

    public function test_move_to_workspace_activates_backlog_order(): void
    {
        $order = $this->makeOrder(['in_workspace' => false, 'is_new_from_trello' => true]);

        Livewire::test(Index::class)->call('moveToWorkspace', $order->id);

        $order->refresh();
        $this->assertTrue($order->in_workspace);
        $this->assertFalse($order->is_new_from_trello);
    }
}
