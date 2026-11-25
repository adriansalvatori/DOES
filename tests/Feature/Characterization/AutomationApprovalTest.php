<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\Setting;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes AutomationEngine::processApproval() (the Approve button).
 */
class AutomationApprovalTest extends AutomationCharacterizationTestCase
{
    private function pendingOrder(array $overrides = []): Order
    {
        return $this->makeOrder(array_merge([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => '2026-09-30',
        ], $overrides));
    }

    private function blockedOrder(array $overrides = []): Order
    {
        return $this->pendingOrder(array_merge([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
        ], $overrides));
    }

    // ---- Measures + estimate confirmed ---------------------------------------------------------------

    public function test_full_approval_moves_to_the_designer_queue_with_poner_en_alta(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true, 'cliente', 'Looks good');

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
        $this->assertTrue($order->approved);
        $this->assertTrue($order->approved_at->isSameMinute(now()));
        $this->assertSame('cliente', $order->approval_type);
        $this->assertSame('Looks good', $order->approval_note);
        $this->assertTrue($order->measures_confirmed);
        $this->assertTrue($order->estimate_approved);
        $this->assertSame('2026-10-01', $order->current_due_date->toDateString());
    }

    public function test_full_approval_creates_the_poner_en_alta_task_due_next_weekday(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->sole();
        $this->assertSame('Poner en alta', $task->title);
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, $task->category);
        $this->assertSame(CoreStatus::EN_PRODUCCION, $task->return_core_status);
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->completed_at);
        $this->assertSame('ORDER_APPROVED', $task->trigger_type);
        $this->assertSame('normal', $task->priority);
        $this->assertTrue($task->is_work_task);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame('2026-10-01', $task->scheduled_date->toDateString());
        $this->assertSame('2026-10-01', $task->due_date->toDateString());
    }

    public function test_approval_on_friday_sets_the_due_date_to_monday(): void
    {
        $this->freezeAt('2026-10-02 09:00:00');
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true);

        $this->assertSame('2026-10-05', $order->fresh()->current_due_date->toDateString());
    }

    public function test_urgent_order_is_approved_for_the_same_day(): void
    {
        $order = $this->pendingOrder(['flags' => [Substatus::URGENTE->value]]);

        $this->engine()->processApproval($order, true, true);

        $order->refresh();
        $this->assertSame('2026-09-30', $order->current_due_date->toDateString());
        $this->assertTrue($order->hasFlag(Substatus::URGENTE));
        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->sole();
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('2026-09-30', $task->due_date->toDateString());

        $event = $this->eventsOf($order, 'ORDER_APPROVED')->sole();
        $this->assertTrue($event->metadata['is_urgente']);
        $this->assertSame('Order approved (Approved by Client) - Urgent same-day SLA set', $event->metadata['sla_message']);
        $this->assertStringEndsWith(', URGENT)', $event->new_value);
    }

    public function test_approval_logs_the_event_and_due_date_history_without_a_due_date_changed_event(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true, 'camila', 'Ok', 'approvals/proof.png');

        $event = $this->eventsOf($order, 'ORDER_APPROVED')->sole();
        $this->assertSame('User', $event->actor);
        $this->assertSame('approved: false', $event->previous_value);
        $this->assertSame('Approved by Camila (Measures: YES, Estimate: YES)', $event->new_value);
        $this->assertSame('camila', $event->metadata['approval_type']);
        $this->assertSame('Approved by Camila', $event->metadata['approval_type_label']);
        $this->assertSame('Ok', $event->metadata['approval_note']);
        $this->assertSame('/storage/approvals/proof.png', $event->metadata['approval_image']);
        $this->assertSame('2026-10-01', $event->metadata['new_due_date']);
        $this->assertFalse($event->metadata['is_urgente']);
        $this->assertSame('Order approved (Approved by Camila) - 24 hour SLA set', $event->metadata['sla_message']);

        $history = DueDateHistory::where('order_id', $order->id)->sole();
        $this->assertSame('ORDER_APPROVED', $history->trigger_event);
        $this->assertSame('2026-10-01', $history->new_due_date->toDateString());
        $this->assertSame(0, $this->eventsOf($order, 'DUE_DATE_CHANGED')->count());
    }

    /** @return array<string, array{string, string}> */
    public static function approvalTypeLabels(): array
    {
        return [
            'cliente' => ['cliente', 'Approved by Client'],
            'camila' => ['camila', 'Approved by Camila'],
            'unknown falls back to client' => ['other', 'Approved by Client'],
        ];
    }

    #[DataProvider('approvalTypeLabels')]
    public function test_approval_type_label(string $type, string $label): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true, $type);

        $this->assertSame($label, $this->eventsOf($order, 'ORDER_APPROVED')->sole()->metadata['approval_type_label']);
    }

    public function test_manager_and_gabriela_approval_use_the_configured_manager_name(): void
    {
        $expected = 'Approved by '.Setting::getManagerName();
        foreach (['manager', 'gabriela'] as $type) {
            $order = $this->pendingOrder();

            $this->engine()->processApproval($order, true, true, $type);

            $this->assertSame($expected, $this->eventsOf($order, 'ORDER_APPROVED')->sole()->metadata['approval_type_label']);
        }
    }

    public function test_full_approval_removes_a_pending_delay_email_task_because_of_poner_en_alta(): void
    {
        $order = $this->pendingOrder(['current_due_date' => now()->subDays(2)->toDateString()]);
        $delay = $this->makeTask($order, ['title' => 'Enviar correo de atraso preventivo', 'type' => RelatedTaskType::CORREO_ATRASO]);

        $this->engine()->processApproval($order, true, true);

        $this->assertNull(RelatedTask::withTrashed()->find($delay->id));
    }

    // ---- targetStatus = production --------------------------------------------------------------------

    public function test_approval_with_production_target_creates_a_completed_poner_en_alta_task(): void
    {
        $order = $this->pendingOrder(['core_status' => CoreStatus::TO_DO_TODAY]);

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertTrue($order->approved);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->sole();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_approval_with_production_target_closes_an_existing_open_alta_task_instead_of_creating_one(): void
    {
        $order = $this->pendingOrder(['core_status' => CoreStatus::TO_DO_TODAY]);
        $existing = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->count());
        $existing->refresh();
        $this->assertSame('done', $existing->status);
        $this->assertSame(SubtaskCategory::PRODUCTION_ADJUSTMENTS, $existing->category);
        $this->assertSame(CoreStatus::EN_PRODUCCION, $existing->return_core_status);
    }

    public function test_approval_with_a_working_today_target_is_routed_back_to_the_queue_by_the_future_alta_task(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::TO_DO_TODAY);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertSame(1, $this->eventsOf($order, 'ROUTED_TO_DESIGNER_FUTURE_SUBTASKS')->count());
    }

    public function test_urgent_approval_with_a_working_today_target_stays_in_working_today(): void
    {
        $order = $this->pendingOrder(['flags' => [Substatus::URGENTE->value]]);

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::TO_DO_TODAY);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_approving_a_blocked_order_creates_two_poner_en_alta_tasks(): void
    {
        $order = $this->blockedOrder();

        $this->engine()->processApproval($order, true, true);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
        $this->assertSame(2, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->count());
        $this->assertSame(1, $this->eventsOf($order, 'ORDER_UNBLOCKED')->count());
    }

    public function test_second_approval_in_a_queue_creates_a_duplicate_poner_en_alta_task(): void
    {
        $order = $this->pendingOrder();
        $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        $this->engine()->processApproval($order, true, true);

        $this->assertSame(2, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->count());
    }

    // ---- Missing measures ---------------------------------------------------------------------------

    /** @return array<string, array{bool}> */
    public static function estimateFlags(): array
    {
        return ['estimate approved' => [true], 'estimate missing' => [false]];
    }

    #[DataProvider('estimateFlags')]
    public function test_missing_measures_block_the_order_in_entrante_with_a_resolver_task(bool $estimateApproved): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, false, $estimateApproved);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $this->assertSame(BlockingReason::FALTAN_MEDIDAS, $order->blocking_reason);
        $this->assertTrue($order->approved);
        $this->assertFalse($order->measures_confirmed);
        $this->assertSame($estimateApproved, $order->estimate_approved);
        $this->assertSame('2026-10-01', $order->current_due_date->toDateString());

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('RESOLVER: Medidas pendientes para orden aprobada', $task->title);
        $this->assertSame(RelatedTaskType::RESOLVER, $task->type);
        $this->assertSame('MISSING_MEASURES_APPROVED', $task->trigger_type);
        $this->assertSame('high', $task->priority);
        $this->assertSame('todo', $task->status);
        $this->assertSame('2026-10-01', $task->due_date->toDateString());
        $this->assertSame('2026-10-01', $task->scheduled_date->toDateString());
    }

    public function test_missing_measures_ignores_a_production_target(): void
    {
        $order = $this->pendingOrder(['core_status' => CoreStatus::TO_DO_TODAY]);

        $this->engine()->processApproval($order, false, true, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
    }

    // ---- Missing estimate ---------------------------------------------------------------------------

    public function test_missing_estimate_lands_in_entrante_because_the_substatus_maps_back_to_it(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, false);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::FALTA_APROBACION_ESTIMADO, $order->substatus);
        $this->assertTrue($order->approved);
        $this->assertTrue($order->measures_confirmed);
        $this->assertFalse($order->estimate_approved);
        $this->assertSame('2026-10-01', $order->current_due_date->toDateString());
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_missing_estimate_ignores_a_production_target(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, true, false, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
        $this->assertSame(Substatus::FALTA_APROBACION_ESTIMADO, $order->fresh()->substatus);
    }

    public function test_neither_measures_nor_estimate_follows_the_missing_measures_branch(): void
    {
        $order = $this->pendingOrder();

        $this->engine()->processApproval($order, false, false);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->fresh()->substatus);
    }

    // ---- Designer queue resolution -------------------------------------------------------------------

    public function test_approval_uses_the_queue_of_the_assigned_designer(): void
    {
        $order = $this->pendingOrder(['designer_id' => $this->cesar->id]);

        $this->engine()->processApproval($order, true, true);

        $this->assertSame(CoreStatus::CESAR_ORDERS_RECEIVED, $order->fresh()->core_status);
    }
}
