<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\RelatedTask;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes AutomationEngine::handleStatusChanged() for every (previous, new) pair of CoreStatus values.
 * Orders start at the previous status with no substatus and a due date of today, unless a test says otherwise.
 */
class AutomationStatusMatrixTest extends AutomationCharacterizationTestCase
{
    /** @return array<string, array{CoreStatus, CoreStatus}> */
    public static function allTransitions(): array
    {
        $pairs = [];
        foreach (CoreStatus::cases() as $previous) {
            foreach (CoreStatus::cases() as $new) {
                $pairs[$previous->name.' -> '.$new->name] = [$previous, $new];
            }
        }

        return $pairs;
    }

    /** @return array<string, array{CoreStatus}> */
    public static function allStatuses(): array
    {
        $cases = [];
        foreach (CoreStatus::cases() as $status) {
            $cases[$status->name] = [$status];
        }

        return $cases;
    }

    private function orderAt(CoreStatus $status, array $overrides = []): Order
    {
        return $this->makeOrder(array_merge([
            'core_status' => $status,
            'current_due_date' => now()->toDateString(),
        ], $overrides));
    }

    private function isDesignerQueueOrToday(CoreStatus $status): bool
    {
        return $status === CoreStatus::TO_DO_TODAY || CoreStatus::isPendingDesign($status);
    }

    /** True when the transition is a client reply that restarts the revision cycle. */
    private function isClientResponse(CoreStatus $previous, CoreStatus $new): bool
    {
        return $previous === CoreStatus::ENVIADO_AL_CLIENTE && $this->isDesignerQueueOrToday($new);
    }

    // ---- Every pair: status, event, done_today --------------------------------------------------------

    #[DataProvider('allTransitions')]
    public function test_every_transition_logs_a_core_status_changed_event(CoreStatus $previous, CoreStatus $new): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, $new);

        $event = $this->eventsOf($order, 'CORE_STATUS_CHANGED')->sole();
        $this->assertSame('Automation', $event->actor);
        $this->assertSame($previous->value, $event->previous_value);
        $this->assertSame($new->value, $event->new_value);
    }

    public function test_status_change_event_uses_the_given_actor(): void
    {
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ON_HOLD, 'Camila');

        $this->assertSame('Camila', $this->eventsOf($order, 'CORE_STATUS_CHANGED')->sole()->actor);
    }

    /**
     * The resulting core status is the new one, except when a client reply bounces the order back to
     * ENVIADO AL CLIENTE (handleClientResponse sets substatus CAMBIOS CLIENTE, which maps back to that status).
     */
    #[DataProvider('allTransitions')]
    public function test_resulting_core_status_and_substatus(CoreStatus $previous, CoreStatus $new): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, $new);

        $order->refresh();
        if ($this->isClientResponse($previous, $new)) {
            $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
            $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);

            return;
        }

        $this->assertSame($new, $order->core_status);
        $expectedSubstatus = match (true) {
            $new === CoreStatus::ENVIADO_AL_CLIENTE => Substatus::WAITING_FOR_CLIENT,
            $new === CoreStatus::ON_HOLD => Substatus::PAUSADO,
            $new === CoreStatus::EN_PRODUCCION => Substatus::ENVIADO_EN_ALTA,
            $previous === $new => null,
            default => $new->defaultSubstatus(),
        };
        $this->assertSame($expectedSubstatus, $order->substatus);
    }

    #[DataProvider('allTransitions')]
    public function test_done_today_is_true_only_when_entering_camila_client_or_production(CoreStatus $previous, CoreStatus $new): void
    {
        $order = $this->orderAt($previous, ['done_today' => ! in_array($new, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION], true)]);

        $this->engine()->handleStatusChanged($order, $previous, $new);

        $expected = in_array($new, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION], true);
        $this->assertSame($expected, $order->fresh()->done_today);
    }

    #[DataProvider('allTransitions')]
    public function test_due_date_and_revision_count(CoreStatus $previous, CoreStatus $new): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, $new);

        $order->refresh();
        $resumes = $previous === CoreStatus::ON_HOLD && ! in_array($new, [CoreStatus::ON_HOLD, CoreStatus::ARCHIVED], true);
        $reset = $resumes || $this->isClientResponse($previous, $new);

        $this->assertSame($reset ? '2026-10-02' : '2026-09-30', $order->current_due_date->toDateString());
        $this->assertSame($this->isClientResponse($previous, $new) ? 1 : 0, $order->client_revision_count);
    }

    // ---- Approval resets -------------------------------------------------------------------------------

    #[DataProvider('allTransitions')]
    public function test_approval_is_reset_only_when_entering_camila_or_client(CoreStatus $previous, CoreStatus $new): void
    {
        $order = $this->orderAt($previous, ['approved' => true, 'measures_confirmed' => true, 'estimate_approved' => true]);

        $this->engine()->handleStatusChanged($order, $previous, $new);

        $order->refresh();
        $resets = in_array($new, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE], true);
        $this->assertSame(! $resets, $order->approved);
        $this->assertSame(! $resets, $order->measures_confirmed);
        $this->assertSame(! $resets, $order->estimate_approved);

        $events = $this->eventsOf($order, 'APPROVAL_RESET');
        $this->assertCount($resets ? 1 : 0, $events);
        if ($resets) {
            $this->assertSame('approved: true', $events[0]->previous_value);
            $this->assertSame(
                $new === CoreStatus::ENVIADO_A_CAMILA
                    ? 'approved: false (Sent to Camila, requires new approval)'
                    : 'approved: false (Re-sent to client, requires new approval)',
                $events[0]->new_value
            );
        }
    }

    #[DataProvider('allStatuses')]
    public function test_no_approval_reset_event_when_the_order_was_not_approved(CoreStatus $previous): void
    {
        foreach ([CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE] as $new) {
            $order = $this->orderAt($previous, ['approved' => false, 'measures_confirmed' => true]);

            $this->engine()->handleStatusChanged($order, $previous, $new);

            $this->assertSame(0, $this->eventsOf($order, 'APPROVAL_RESET')->count());
            $this->assertFalse($order->fresh()->measures_confirmed);
        }
    }

    // ---- ENVIADO A CAMILA ----------------------------------------------------------------------------

    #[DataProvider('allStatuses')]
    public function test_entering_camila_creates_a_follow_up_task_for_next_weekday(CoreStatus $previous): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, CoreStatus::ENVIADO_A_CAMILA);

        $task = RelatedTask::where('order_id', $order->id)->where('trigger_type', 'CAMILA_TRANSITION')->sole();
        $this->assertSame('Follow Up Camila', $task->title);
        $this->assertSame(RelatedTaskType::FOLLOW_UP_CAMILA, $task->type);
        $this->assertSame(SubtaskCategory::MANAGEMENT, $task->category);
        $this->assertSame('todo', $task->status);
        $this->assertSame('normal', $task->priority);
        $this->assertFalse($task->is_work_task);
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertSame('2026-10-01', $task->scheduled_date->toDateString());
        $this->assertSame('2026-10-01', $task->due_date->toDateString());
    }

    public function test_follow_up_camila_task_skips_the_weekend(): void
    {
        $this->freezeAt('2026-10-02 09:00:00');
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_A_CAMILA);

        $this->assertSame('2026-10-05', RelatedTask::where('order_id', $order->id)->sole()->due_date->toDateString());
    }

    public function test_camila_task_is_always_follow_up_never_call_because_done_today_is_set_first(): void
    {
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY, ['current_due_date' => now()->subDays(3)->toDateString()]);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_A_CAMILA);

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('Follow Up Camila', $task->title);
        $this->assertSame('normal', $task->priority);
    }

    public function test_camila_to_working_today_marks_the_order_as_camila_changes_unless_a_proof_task_exists(): void
    {
        $withoutProof = $this->orderAt(CoreStatus::ENVIADO_A_CAMILA, ['substatus' => Substatus::AJUSTES_PRODUCCION]);
        $withProof = $this->orderAt(CoreStatus::ENVIADO_A_CAMILA, ['substatus' => Substatus::AJUSTES_PRODUCCION]);
        $this->makeTask($withProof, ['title' => 'Enviar proof al cliente']);

        $this->engine()->handleStatusChanged($withoutProof, CoreStatus::ENVIADO_A_CAMILA, CoreStatus::TO_DO_TODAY);
        $this->engine()->handleStatusChanged($withProof, CoreStatus::ENVIADO_A_CAMILA, CoreStatus::TO_DO_TODAY);

        $this->assertSame(Substatus::CAMBIOS_CAMILA, $withoutProof->fresh()->substatus);
        $this->assertSame(Substatus::AJUSTES_PRODUCCION, $withProof->fresh()->substatus);
    }

    // ---- ENVIADO AL CLIENTE ----------------------------------------------------------------------------

    #[DataProvider('allStatuses')]
    public function test_entering_client_stamps_the_send_date_and_logs_the_send(CoreStatus $previous): void
    {
        $order = $this->orderAt($previous, ['client_last_response' => now()->subDay(), 'client_revision_count' => 2]);

        $this->engine()->handleStatusChanged($order, $previous, CoreStatus::ENVIADO_AL_CLIENTE);

        $order->refresh();
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertNull($order->client_last_response);
        $this->assertSame(2, $order->client_revision_count);
        $this->assertTrue($order->last_meaningful_update->isSameMinute(now()));

        $sends = $this->eventsOf($order, 'ORDER_SENT_TO_CLIENT');
        if ($previous === CoreStatus::ENVIADO_AL_CLIENTE) {
            $this->assertNull($order->last_sent_to_client_at);
            $this->assertCount(0, $sends);

            return;
        }

        $this->assertTrue($order->last_sent_to_client_at->isSameMinute(now()));
        $this->assertCount(1, $sends);
        $this->assertSame($previous->value, $sends[0]->previous_value);
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE->value, $sends[0]->new_value);
        $this->assertSame(2, $sends[0]->metadata['revision_count']);
    }

    public function test_resending_to_client_keeps_the_original_send_date(): void
    {
        $sentAt = now()->subDays(4)->startOfMinute();
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE, ['last_sent_to_client_at' => $sentAt]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::ENVIADO_AL_CLIENTE);

        $this->assertTrue($order->fresh()->last_sent_to_client_at->equalTo($sentAt));
    }

    // ---- Client response (ENVIADO AL CLIENTE -> work) -------------------------------------------------

    /** @return array<string, array{CoreStatus}> */
    public static function clientResponseTargets(): array
    {
        return [
            'working today' => [CoreStatus::TO_DO_TODAY],
            'euraliz queue' => [CoreStatus::EURALIZ_ORDERS_RECEIVED],
            'adrian queue' => [CoreStatus::ADRIAN_ORDERS_RECEIVED],
            'cesar queue' => [CoreStatus::CESAR_ORDERS_RECEIVED],
        ];
    }

    #[DataProvider('clientResponseTargets')]
    public function test_client_reply_starts_a_revision_with_two_weekdays_sla(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE, ['flags' => [Substatus::OVERDUE->value, Substatus::ALMOST_OVERDUE->value, 'KEEP']]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, $new);

        $order->refresh();
        $this->assertSame(1, $order->client_revision_count);
        $this->assertSame('2026-10-02', $order->current_due_date->toDateString());
        $this->assertTrue($order->client_last_response->isSameMinute(now()));
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);
        $this->assertSame(['KEEP'], $order->flags);

        $history = DueDateHistory::where('order_id', $order->id)->sole();
        $this->assertSame('CLIENT_REVISION_RECEIVED', $history->trigger_event);
        $this->assertSame('2026-09-30', $history->previous_due_date->toDateString());
        $this->assertSame('2026-10-02', $history->new_due_date->toDateString());
        $this->assertSame('system', $history->created_by);

        $dueChange = $this->eventsOf($order, 'DUE_DATE_CHANGED')->sole();
        $this->assertSame('2026-09-30', $dueChange->previous_value);
        $this->assertSame('2026-10-02', $dueChange->new_value);
    }

    #[DataProvider('clientResponseTargets')]
    public function test_client_reply_deletes_pending_client_follow_ups_but_keeps_done_ones(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE);
        $pending = $this->makeTask($order, ['title' => 'Follow Up Cliente #1', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE]);
        $done = $this->makeTask($order, ['title' => 'Follow Up Cliente #2', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE, 'status' => 'done', 'completed_at' => now()]);
        $plain = $this->makeTask($order, ['title' => 'Plain work']);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, $new);

        $this->assertNull(RelatedTask::withTrashed()->find($pending->id));
        $this->assertNotNull(RelatedTask::find($done->id));
        $this->assertNotNull(RelatedTask::find($plain->id));
    }

    /** @return array<string, array{CoreStatus}> */
    public static function nonResponseTargetsFromClient(): array
    {
        return [
            'entrante' => [CoreStatus::ENTRANTE],
            'camila' => [CoreStatus::ENVIADO_A_CAMILA],
            'on hold' => [CoreStatus::ON_HOLD],
            'production' => [CoreStatus::EN_PRODUCCION],
            'archived' => [CoreStatus::ARCHIVED],
        ];
    }

    #[DataProvider('nonResponseTargetsFromClient')]
    public function test_leaving_client_for_other_statuses_is_not_a_client_reply(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE);
        $followUp = $this->makeTask($order, ['title' => 'Follow Up Cliente #1', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, $new);

        $this->assertSame(0, $order->fresh()->client_revision_count);
        $this->assertNull($order->fresh()->client_last_response);
        $this->assertSame(0, DueDateHistory::where('order_id', $order->id)->count());
    }

    // ---- ON HOLD -----------------------------------------------------------------------------------------

    #[DataProvider('allStatuses')]
    public function test_manual_hold_sets_paused_substatus(CoreStatus $previous): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, CoreStatus::ON_HOLD);

        $this->assertSame(Substatus::PAUSADO, $order->fresh()->substatus);
    }

    public function test_hold_keeps_customer_service_required_substatus(): void
    {
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE, ['substatus' => Substatus::CUSTOMER_SERVICE_REQUIRED]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::ON_HOLD);

        $this->assertSame(Substatus::CUSTOMER_SERVICE_REQUIRED, $order->fresh()->substatus);
    }

    public function test_hold_replaces_no_response_with_paused_because_the_observer_normalizes_it_first(): void
    {
        $order = $this->orderAt(CoreStatus::ENVIADO_AL_CLIENTE, ['substatus' => Substatus::NO_RESPUESTA]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::ON_HOLD);

        $this->assertSame(Substatus::PAUSADO, $order->fresh()->substatus);
    }

    /** @return array<string, array{CoreStatus}> */
    public static function resumeTargets(): array
    {
        $cases = [];
        foreach (CoreStatus::cases() as $status) {
            if (! in_array($status, [CoreStatus::ON_HOLD, CoreStatus::ARCHIVED], true)) {
                $cases[$status->name] = [$status];
            }
        }

        return $cases;
    }

    #[DataProvider('resumeTargets')]
    public function test_resuming_from_hold_resets_sla_to_two_weekdays_and_clears_overdue_flags(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::ON_HOLD, [
            'current_due_date' => now()->subDays(5)->toDateString(),
            'flags' => [Substatus::OVERDUE->value, Substatus::ALMOST_OVERDUE->value, 'KEEP'],
        ]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ON_HOLD, $new);

        $order->refresh();
        $this->assertSame('2026-10-02', $order->current_due_date->toDateString());
        $this->assertSame(['KEEP'], $order->flags);

        $history = DueDateHistory::where('order_id', $order->id)->sole();
        $this->assertSame('RESUMED_FROM_ON_HOLD', $history->trigger_event);
        $this->assertSame('Order resumed from ON HOLD - 2 day SLA reset', $history->reason);
    }

    /** @return array<string, array{Substatus}> */
    public static function holdSubstatuses(): array
    {
        return [
            'paused' => [Substatus::PAUSADO],
            'customer service required' => [Substatus::CUSTOMER_SERVICE_REQUIRED],
            'no response' => [Substatus::NO_RESPUESTA],
        ];
    }

    #[DataProvider('holdSubstatuses')]
    public function test_resuming_from_hold_to_working_today_clears_the_hold_substatus(Substatus $holdSubstatus): void
    {
        $order = $this->orderAt(CoreStatus::ON_HOLD, ['substatus' => $holdSubstatus, 'customer_service_required' => true]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ON_HOLD, CoreStatus::TO_DO_TODAY);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_resuming_from_hold_to_archived_does_not_reset_the_sla(): void
    {
        $order = $this->orderAt(CoreStatus::ON_HOLD);

        $this->engine()->handleStatusChanged($order, CoreStatus::ON_HOLD, CoreStatus::ARCHIVED);

        $this->assertSame('2026-09-30', $order->fresh()->current_due_date->toDateString());
        $this->assertSame(0, DueDateHistory::where('order_id', $order->id)->count());
    }

    // ---- EN PRODUCCION / ARCHIVED / TO DO TODAY ----------------------------------------------------------

    #[DataProvider('allStatuses')]
    public function test_entering_archived_stamps_archived_at_once(CoreStatus $previous): void
    {
        $order = $this->orderAt($previous);

        $this->engine()->handleStatusChanged($order, $previous, CoreStatus::ARCHIVED);

        $this->assertTrue($order->fresh()->archived_at->isSameMinute(now()));
    }

    public function test_entering_archived_keeps_an_existing_archived_at(): void
    {
        $archivedAt = now()->subWeek()->startOfMinute();
        $order = $this->orderAt(CoreStatus::EN_PRODUCCION, ['archived_at' => $archivedAt]);

        $this->engine()->handleStatusChanged($order, CoreStatus::EN_PRODUCCION, CoreStatus::ARCHIVED);

        $this->assertTrue($order->fresh()->archived_at->equalTo($archivedAt));
    }

    public function test_leaving_archived_with_a_substatus_clears_archived_at(): void
    {
        $order = $this->orderAt(CoreStatus::ARCHIVED, ['substatus' => Substatus::FINALIZADA, 'archived_at' => now()->subDay()]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ARCHIVED, CoreStatus::ADRIAN_ORDERS_RECEIVED);

        $this->assertNull($order->fresh()->archived_at);
    }

    public function test_leaving_archived_without_a_substatus_keeps_archived_at(): void
    {
        $order = $this->orderAt(CoreStatus::ARCHIVED, ['archived_at' => now()->subDay()]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ARCHIVED, CoreStatus::ADRIAN_ORDERS_RECEIVED);

        $this->assertNotNull($order->fresh()->archived_at);
    }

    #[DataProvider('allStatuses')]
    public function test_entering_production_sets_sent_in_alta_substatus(CoreStatus $previous): void
    {
        $order = $this->orderAt($previous, ['substatus' => Substatus::PONER_EN_ALTA]);

        $this->engine()->handleStatusChanged($order, $previous, CoreStatus::EN_PRODUCCION);

        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->fresh()->substatus);
    }

    public function test_entering_working_today_schedules_for_today_and_remembers_the_origin(): void
    {
        $order = $this->orderAt(CoreStatus::ADRIAN_ORDERS_RECEIVED, ['substatus' => Substatus::PONER_EN_ALTA]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ADRIAN_ORDERS_RECEIVED, CoreStatus::TO_DO_TODAY);

        $order->refresh();
        $this->assertSame('2026-09-30', $order->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->origin_core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->origin_substatus);
    }

    public function test_entering_working_today_keeps_a_future_scheduled_date_and_an_existing_origin(): void
    {
        $order = $this->orderAt(CoreStatus::ADRIAN_ORDERS_RECEIVED, [
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'origin_core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'origin_substatus' => Substatus::CAMBIOS_CLIENTE,
        ]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ADRIAN_ORDERS_RECEIVED, CoreStatus::TO_DO_TODAY);

        $order->refresh();
        $this->assertSame('2026-10-03', $order->scheduled_date->toDateString());
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->origin_core_status);
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->origin_substatus);
    }

    public function test_entering_working_today_moves_a_past_scheduled_date_to_today(): void
    {
        $order = $this->orderAt(CoreStatus::ADRIAN_ORDERS_RECEIVED, ['scheduled_date' => now()->subDays(4)->toDateString()]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ADRIAN_ORDERS_RECEIVED, CoreStatus::TO_DO_TODAY);

        $this->assertSame('2026-09-30', $order->fresh()->scheduled_date->toDateString());
    }

    public function test_staying_in_working_today_does_not_record_an_origin(): void
    {
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::TO_DO_TODAY);

        $this->assertNull($order->fresh()->origin_core_status);
    }

    // ---- Welcome / triggered task cleanup ------------------------------------------------------------------

    /** @return array<string, array{CoreStatus}> */
    public static function cleanupTargets(): array
    {
        return [
            'camila' => [CoreStatus::ENVIADO_A_CAMILA],
            'client' => [CoreStatus::ENVIADO_AL_CLIENTE],
            'production' => [CoreStatus::EN_PRODUCCION],
            'on hold' => [CoreStatus::ON_HOLD],
            'archived' => [CoreStatus::ARCHIVED],
        ];
    }

    /** @return array<string, array{CoreStatus}> */
    public static function nonCleanupTargets(): array
    {
        return [
            'entrante' => [CoreStatus::ENTRANTE],
            'euraliz queue' => [CoreStatus::EURALIZ_ORDERS_RECEIVED],
            'adrian queue' => [CoreStatus::ADRIAN_ORDERS_RECEIVED],
            'cesar queue' => [CoreStatus::CESAR_ORDERS_RECEIVED],
            'working today' => [CoreStatus::TO_DO_TODAY],
        ];
    }

    #[DataProvider('cleanupTargets')]
    public function test_completion_statuses_delete_open_welcome_and_system_triggered_tasks(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY);
        $welcome = $this->makeTask($order, ['title' => 'Enviar correo bienvenida/orden nueva', 'type' => RelatedTaskType::BIENVENIDA]);
        $welcomeDone = $this->makeTask($order, ['title' => 'Welcome done', 'type' => RelatedTaskType::BIENVENIDA, 'status' => 'done', 'completed_at' => now()]);
        $triggered = $this->makeTask($order, ['title' => 'Triggered', 'type' => RelatedTaskType::CORREO_ATRASO, 'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION']);
        $resolver = $this->makeTask($order, ['title' => 'RESOLVER x', 'type' => RelatedTaskType::RESOLVER]);
        $triggeredSubtask = $this->makeTask($order, ['title' => 'Triggered subtask', 'type' => RelatedTaskType::SUBTASK, 'trigger_type' => 'X']);
        $plain = $this->makeTask($order, ['title' => 'Plain work']);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, $new);

        $this->assertNull(RelatedTask::withTrashed()->find($welcome->id));
        $this->assertNull(RelatedTask::withTrashed()->find($triggered->id));
        $this->assertNull(RelatedTask::withTrashed()->find($resolver->id));
        $this->assertNotNull(RelatedTask::find($welcomeDone->id));
        $this->assertNotNull(RelatedTask::find($triggeredSubtask->id));
        $this->assertNotNull(RelatedTask::find($plain->id));
    }

    #[DataProvider('nonCleanupTargets')]
    public function test_other_statuses_keep_welcome_and_triggered_tasks(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::ENTRANTE);
        $welcome = $this->makeTask($order, ['title' => 'Enviar correo bienvenida/orden nueva', 'type' => RelatedTaskType::BIENVENIDA]);
        $triggered = $this->makeTask($order, ['title' => 'Triggered', 'type' => RelatedTaskType::CORREO_ATRASO, 'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION']);
        $alta = $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENTRANTE, $new);

        $this->assertNotNull(RelatedTask::find($welcome->id));
        $this->assertNotNull(RelatedTask::find($triggered->id));
        $this->assertNotNull(RelatedTask::find($alta->id));
    }

    #[DataProvider('cleanupTargets')]
    public function test_only_camila_and_client_delete_pending_poner_en_alta_tasks(CoreStatus $new): void
    {
        $order = $this->orderAt(CoreStatus::TO_DO_TODAY);
        $byType = $this->makeTask($order, ['title' => 'Alta typed', 'type' => RelatedTaskType::PONER_ALTA]);
        $byTitle = $this->makeTask($order, ['title' => 'Please poner en alta now']);
        $doneAlta = $this->makeTask($order, ['title' => 'Poner en alta done', 'type' => RelatedTaskType::PONER_ALTA, 'status' => 'done', 'completed_at' => now()]);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, $new);

        $deletes = in_array($new, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE], true);
        $this->assertSame(! $deletes, RelatedTask::find($byType->id) !== null);
        $this->assertSame(! $deletes, RelatedTask::find($byTitle->id) !== null);
        $this->assertNotNull(RelatedTask::find($doneAlta->id));
    }
}
