<?php

namespace Tests\Feature\Characterization;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes Order::block() and Order::unblock().
 */
class AutomationBlockUnblockTest extends AutomationCharacterizationTestCase
{
    private function blockedOrder(array $overrides = []): Order
    {
        return $this->makeOrder(array_merge([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
            'customer_service_required' => true,
        ], $overrides));
    }

    // ---- block() -----------------------------------------------------------------------------------

    public function test_block_moves_the_order_to_entrante_as_blocked(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'done_today' => true]);

        $order->block('FALTA LOGO', null, 'Customer has no logo', false, 'Adrián');

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $this->assertSame(BlockingReason::FALTA_LOGO, $order->blocking_reason);
        $this->assertNull($order->blocking_reason_other);
        $this->assertFalse($order->customer_service_required);
        $this->assertFalse($order->done_today);
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());

        $blocked = $this->eventsOf($order, 'ORDER_BLOCKED')->sole();
        $this->assertSame('Adrián', $blocked->actor);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $blocked->previous_value);
        $this->assertSame(CoreStatus::ENTRANTE->value, $blocked->new_value);
        $this->assertSame(Substatus::BLOQUEADA->value, $blocked->metadata['substatus']);
        $this->assertSame('FALTA LOGO', $blocked->metadata['reason']);
        $this->assertSame('Customer has no logo', $blocked->metadata['comment']);
        $this->assertFalse($blocked->metadata['customer_service_required']);

        $changed = $this->eventsOf($order, 'CORE_STATUS_CHANGED')->sole();
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $changed->previous_value);
        $this->assertSame(CoreStatus::ENTRANTE->value, $changed->new_value);
    }

    public function test_block_without_a_comment_uses_the_default_comment(): void
    {
        $order = $this->makeOrder();

        $order->block('FALTAN MEDIDAS');

        $this->assertSame('Order marked as Blocked.', $this->eventsOf($order, 'ORDER_BLOCKED')->sole()->metadata['comment']);
        $this->assertSame(BlockingReason::FALTAN_MEDIDAS, $order->fresh()->blocking_reason);
    }

    public function test_block_with_an_unknown_reason_is_stored_as_other(): void
    {
        $order = $this->makeOrder();

        $order->block('SOMETHING ELSE', 'Waiting on landlord');

        $order->refresh();
        $this->assertSame(BlockingReason::OTROS, $order->blocking_reason);
        $this->assertSame('Waiting on landlord', $order->blocking_reason_other);
    }

    public function test_block_with_other_reason_stores_the_comment_as_the_other_text(): void
    {
        $order = $this->makeOrder();

        $order->block('OTROS', null, 'Needs permit');

        $this->assertSame('Needs permit', $order->fresh()->blocking_reason_other);
    }

    public function test_block_without_a_reason_leaves_the_blocking_reason_empty(): void
    {
        $order = $this->makeOrder();

        $order->block();

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::BLOQUEADA, $order->substatus);
        $this->assertNull($order->blocking_reason);
    }

    /** @return array<string, array{string}> */
    public static function estimateReasons(): array
    {
        return ['with accents' => ['FALTA APROBACIÓN DE ESTIMADO'], 'with underscores' => ['FALTA_APROBACION_ESTIMADO']];
    }

    #[DataProvider('estimateReasons')]
    public function test_block_for_missing_estimate_uses_the_estimate_substatus(string $reason): void
    {
        $order = $this->makeOrder();

        $order->block($reason);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertSame(Substatus::FALTA_APROBACION_ESTIMADO, $order->substatus);
        $this->assertSame(BlockingReason::OTROS, $order->blocking_reason);
    }

    public function test_block_requiring_customer_service_creates_a_request_info_task(): void
    {
        $order = $this->makeOrder(['company_name' => 'Taco Bell', 'task_name' => 'Banner']);

        $order->block('FALTAN MEDIDAS', null, 'Need width', true);

        $order->refresh();
        $this->assertTrue($order->customer_service_required);

        $task = RelatedTask::where('order_id', $order->id)->sole();
        $this->assertSame('Resolver bloqueo: TACO BELL - BANNER (Need width)', $task->title);
        $this->assertSame(RelatedTaskType::SOLICITAR_INFO, $task->type);
        $this->assertSame('todo', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
        $this->assertSame($this->adrian->id, $task->assignee_id);
        $this->assertNull($task->trigger_type);
    }

    public function test_block_requiring_customer_service_without_a_comment_has_a_plain_task_title(): void
    {
        $order = $this->makeOrder(['company_name' => 'Taco Bell', 'task_name' => 'Banner']);

        $order->block('FALTAN MEDIDAS', null, null, true);

        $this->assertSame('Resolver bloqueo: TACO BELL - BANNER', RelatedTask::where('order_id', $order->id)->sole()->title);
    }

    public function test_block_uses_the_default_actor_when_nobody_is_logged_in(): void
    {
        $order = $this->makeOrder();

        $order->block('FALTAN MEDIDAS');

        $this->assertSame('User', $this->eventsOf($order, 'ORDER_BLOCKED')->sole()->actor);
    }

    // ---- unblock() ---------------------------------------------------------------------------------

    public function test_unblock_returns_a_blocked_order_to_its_designer_queue_and_clears_the_block(): void
    {
        $order = $this->blockedOrder();
        $resolver = $this->makeTask($order, ['title' => 'RESOLVER x', 'type' => RelatedTaskType::RESOLVER]);
        $blocker = $this->makeTask($order, ['title' => 'Bloqueado', 'type' => RelatedTaskType::BLOCKED]);
        $other = $this->makeTask($order, ['title' => 'Plain work']);

        $order->unblock('Measures arrived', 'Camila');

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertNull($order->blocking_reason);
        $this->assertNull($order->blocking_reason_other);
        $this->assertFalse($order->customer_service_required);

        $this->assertSame('done', $resolver->fresh()->status);
        $this->assertSame('done', $blocker->fresh()->status);
        $this->assertSame('todo', $other->fresh()->status);

        $event = $this->eventsOf($order, 'ORDER_UNBLOCKED')->sole();
        $this->assertSame('Camila', $event->actor);
        $this->assertSame(CoreStatus::ENTRANTE->value, $event->previous_value);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED->value, $event->new_value);
        $this->assertSame('Measures arrived', $event->metadata['reason']);

        $changed = $this->eventsOf($order, 'CORE_STATUS_CHANGED')->sole();
        $this->assertSame(CoreStatus::ENTRANTE->value, $changed->previous_value);
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED->value, $changed->new_value);
    }

    /** @return array<string, array{string, int, string, int}> */
    public static function blockedDurations(): array
    {
        return [
            'under an hour' => ['-30 minutes', 1, 'less than 1 hour', 1],
            'a few hours' => ['-5 hours', 5, '5 hours', 1],
            'one day' => ['-1 day', 24, '1 day', 1],
            'several days' => ['-3 days', 72, '3 days', 3],
        ];
    }

    #[DataProvider('blockedDurations')]
    public function test_unblock_reports_how_long_the_order_was_blocked(string $blockedAgo, int $hours, string $text, int $days): void
    {
        $order = $this->blockedOrder();
        $blockedEvent = OrderEvent::create(['order_id' => $order->id, 'event_type' => 'ORDER_BLOCKED']);
        $blockedEvent->forceFill(['created_at' => now()->modify($blockedAgo)])->save();

        $order->unblock('Done');

        $event = $this->eventsOf($order, 'ORDER_UNBLOCKED')->sole();
        $this->assertSame($days, $event->metadata['blocked_days']);
        $this->assertSame($text, $event->metadata['blocked_duration']);
        $this->assertSame("Desbloqueado tras {$text}: Done", $event->metadata['comment']);
    }

    public function test_unblock_of_an_approved_order_with_measures_and_estimate_goes_to_poner_en_alta(): void
    {
        $order = $this->blockedOrder(['approved' => true, 'measures_confirmed' => true, 'estimate_approved' => true]);

        $order->unblock('All set');

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->sole();
        $this->assertSame('Poner en alta', $task->title);
        $this->assertSame('ORDER_APPROVED', $task->trigger_type);
        $this->assertSame('normal', $task->priority);
        $this->assertSame(CoreStatus::EN_PRODUCCION, $task->return_core_status);
        $this->assertSame('2026-10-01', $task->due_date->toDateString());
        $this->assertGreaterThanOrEqual(1, $this->eventsOf($order, 'AUTOMATIC_TASK_TRIGGERED')->count());
    }

    public function test_unblock_of_an_urgent_approved_order_schedules_alta_for_today(): void
    {
        $order = $this->blockedOrder([
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'flags' => [Substatus::URGENTE->value],
        ]);

        $order->unblock('All set');

        $task = RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->sole();
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('2026-09-30', $task->due_date->toDateString());
    }

    public function test_unblock_does_not_duplicate_an_open_alta_task(): void
    {
        $order = $this->blockedOrder(['approved' => true, 'measures_confirmed' => true, 'estimate_approved' => true]);
        $this->makeTask($order, ['title' => 'Poner en alta', 'type' => RelatedTaskType::PONER_ALTA]);

        $order->unblock('All set');

        $this->assertSame(1, RelatedTask::where('order_id', $order->id)->where('type', RelatedTaskType::PONER_ALTA)->count());
    }

    public function test_unblock_of_an_unapproved_order_does_not_add_poner_en_alta(): void
    {
        $order = $this->blockedOrder(['approved' => false, 'measures_confirmed' => true, 'estimate_approved' => true]);

        $order->unblock('Ok');

        $this->assertNull($order->fresh()->substatus);
        $this->assertSame(0, RelatedTask::where('order_id', $order->id)->count());
    }

    public function test_unblock_keeps_the_status_of_an_order_that_is_already_out_of_entrante(): void
    {
        $order = $this->makeOrder([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::AJUSTES_PRODUCCION,
            'customer_service_required' => true,
        ]);

        $order->unblock('Resolved');

        $order->refresh();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertSame(Substatus::AJUSTES_PRODUCCION, $order->substatus);
        $this->assertFalse($order->customer_service_required);
        $this->assertSame(CoreStatus::TO_DO_TODAY->value, $this->eventsOf($order, 'ORDER_UNBLOCKED')->sole()->new_value);
    }

    public function test_unblock_uses_the_lead_designer_queue_for_an_order_without_a_designer(): void
    {
        $order = $this->blockedOrder(['designer_id' => $this->euraliz->id]);

        $order->unblock('Ok');

        $this->assertSame(CoreStatus::EURALIZ_ORDERS_RECEIVED, $order->fresh()->core_status);
    }
}
