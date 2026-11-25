<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\RelatedTask;
use App\Models\User;
use Livewire\Livewire;

/**
 * Documents known defects in the automation rules. Each test asserts the CORRECT behavior and is marked
 * incomplete so it does not fail today; the rebuild phase that fixes the defect removes the marker.
 */
class AutomationKnownBugsTest extends AutomationCharacterizationTestCase
{
    public function test_follow_up_three_is_created_on_the_ninth_weekday(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: AutomationEngine follow-up #3 branch `=== 9` is unreachable because `>= 9` already moves the order to ON HOLD.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'last_sent_to_client_at' => now()->subWeekdays(9),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('title', 'Follow Up Cliente #3')->count());
    }

    public function test_scheduled_promotion_event_records_the_real_previous_status(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: 07:00 step 3 reads previous_value from $order->core_status after the update, so SCHEDULED_TODAY_PROMOTED always logs TO DO TODAY as previous.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(
            CoreStatus::ADRIAN_ORDERS_RECEIVED->value,
            $this->eventsOf($order, 'SCHEDULED_TODAY_PROMOTED')->sole()->previous_value
        );
    }

    public function test_daily_automation_does_not_send_an_unapproved_order_to_production(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: 07:00 step 4 moves TO DO TODAY + PONER EN ALTA + done_today to EN PRODUCCIÓN without checking order approval.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => true,
            'approved' => false,
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_hoy_mark_done_does_not_send_an_unapproved_order_to_production(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: Hoy markDoneToday moves TO DO TODAY + PONER EN ALTA to EN PRODUCCIÓN without checking order approval.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::PONER_EN_ALTA,
            'done_today' => false,
            'approved' => false,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardIndex::class)
            ->call('markDoneToday', $order->id);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_approval_with_a_production_target_logs_a_core_status_changed_event(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: processApproval with targetStatus moves the order to EN PRODUCCIÓN without a CORE_STATUS_CHANGED event (and without handleStatusChanged side effects).');

        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'current_due_date' => now()->toDateString()]);

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $event = $this->eventsOf($order, 'CORE_STATUS_CHANGED')->sole();
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $event->previous_value);
        $this->assertSame(CoreStatus::EN_PRODUCCION->value, $event->new_value);
    }

    public function test_approval_to_production_does_not_close_unrelated_tasks_whose_title_contains_alta_inside_falta(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: the `%alta%` title match (AutomationEngine ~388) treats "FALTA ..." tasks as Poner en alta tasks.');

        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'current_due_date' => now()->toDateString()]);
        $falta = $this->makeTask($order, ['title' => 'Falta logo del cliente']);

        $this->engine()->processApproval($order, true, true, 'cliente', null, null, CoreStatus::EN_PRODUCCION);

        $this->assertSame('todo', $falta->fresh()->status);
    }

    public function test_completed_falta_task_does_not_auto_return_an_approved_order_to_production(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: the `%alta%` / "ALTA" title match (AutomationEngine ~889/916, RelatedTask::isPonerEnAlta) treats "FALTA ..." tasks as Poner en alta and auto-returns the order to EN PRODUCCIÓN.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'approved' => true,
            'current_due_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->makeTask($order, [
            'title' => 'Falta logo del cliente',
            'status' => 'done',
            'completed_at' => now(),
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->engine()->evaluateSubtaskCompletionAutoDone($order->fresh());

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_overdue_flags_are_refreshed_by_the_daily_automations(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: SlaEngine::checkOverdue is only called from the Trello sync, so overdue flags are never refreshed by the daily job.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'current_due_date' => now()->subDay()->toDateString(),
        ]);

        $this->engine()->runDailyAutomations();

        $this->assertTrue($order->fresh()->hasFlag(Substatus::OVERDUE));
    }

    public function test_entering_camila_when_due_today_creates_a_call_task(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: handleStatusChanged sets done_today before checking isDueToday/isOverdue, so the "Llamar a Camila" urgent branch is unreachable.');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->engine()->handleStatusChanged($order, CoreStatus::TO_DO_TODAY, CoreStatus::ENVIADO_A_CAMILA);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::FOLLOW_UP_CAMILA)->sole();
        $this->assertSame('Llamar a Camila', $task->title);
        $this->assertSame('urgent', $task->priority);
    }

    public function test_client_reply_leaves_the_order_in_the_requested_work_status(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: handleClientResponse sets substatus CAMBIOS CLIENTE, which the observer maps back to ENVIADO AL CLIENTE, undoing the move to the work status.');

        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::WAITING_FOR_CLIENT]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::TO_DO_TODAY);

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);
    }

    public function test_hold_keeps_the_no_response_substatus(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: handleStatusChanged tries to keep NO RESPUESTA on hold, but the observer normalizes it to PAUSADO first.');

        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::NO_RESPUESTA]);

        $this->engine()->handleStatusChanged($order, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::ON_HOLD);

        $this->assertSame(Substatus::NO_RESPUESTA, $order->fresh()->substatus);
    }

    public function test_approval_missing_only_the_estimate_from_working_today_completes_without_recursing(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: processApproval(measures yes, estimate no) from a different status recurses forever (observer -> ActionRequiredResolver -> Order::unblock -> observer) and exhausts memory; Order::unblock of an approved order with measures but no estimate does the same. Not executed here for that reason.');

        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'current_due_date' => now()->toDateString()]);

        $this->engine()->processApproval($order, true, false);

        $order->refresh();
        $this->assertSame(Substatus::FALTA_APROBACION_ESTIMADO, $order->substatus);
        $this->assertTrue($order->approved);
    }

    public function test_approving_a_blocked_order_creates_a_single_poner_en_alta_task(): void
    {
        $this->markTestIncomplete('Known bug, fixed in rebuild phase 4: approving a BLOQUEADA order creates two "Poner en alta" tasks (Order::unblock via the resolver, then processApproval).');

        $order = $this->makeOrder([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'current_due_date' => now()->toDateString(),
        ]);

        $this->engine()->processApproval($order, true, true);

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->count());
    }
}
