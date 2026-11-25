<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterizes OrderObserver: substatus <-> core status normalization on update, and its notifications.
 */
class AutomationObserverNormalizationTest extends AutomationCharacterizationTestCase
{
    // ---- core status changes alone: substatus is normalized -----------------------------------------------

    /** @return array<string, array{CoreStatus, ?Substatus}> */
    public static function defaultSubstatusPerStatus(): array
    {
        $cases = [];
        foreach (CoreStatus::cases() as $status) {
            $cases[$status->name] = [$status, $status->defaultSubstatus()];
        }

        return $cases;
    }

    #[DataProvider('defaultSubstatusPerStatus')]
    public function test_moving_an_order_without_substatus_assigns_the_default_of_the_new_status(CoreStatus $new, ?Substatus $default): void
    {
        $order = $this->makeOrder(['core_status' => $new === CoreStatus::TO_DO_TODAY ? CoreStatus::ENTRANTE : CoreStatus::TO_DO_TODAY, 'substatus' => null]);

        $order->update(['core_status' => $new]);

        $this->assertSame($default, $order->fresh()->substatus);
    }

    /** @return array<string, array{CoreStatus, Substatus, ?Substatus}> */
    public static function substatusAcrossStatusChange(): array
    {
        return [
            'valid substatus is kept (queue to working today)' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, Substatus::PONER_EN_ALTA, Substatus::PONER_EN_ALTA],
            'valid substatus is kept (working today to production)' => [CoreStatus::EN_PRODUCCION, Substatus::AJUSTES_PRODUCCION, Substatus::AJUSTES_PRODUCCION],
            'invalid substatus replaced by default (client to camila)' => [CoreStatus::ENVIADO_A_CAMILA, Substatus::CAMBIOS_CLIENTE, Substatus::CAMBIOS_CAMILA],
            'invalid substatus replaced by default (to client)' => [CoreStatus::ENVIADO_AL_CLIENTE, Substatus::PONER_EN_ALTA, Substatus::WAITING_FOR_CLIENT],
            'invalid substatus cleared (to designer queue)' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, Substatus::CAMBIOS_CLIENTE, null],
            'invalid substatus replaced by default (to entrante)' => [CoreStatus::ENTRANTE, Substatus::CAMBIOS_CLIENTE, Substatus::BLOQUEADA],
            'invalid substatus replaced by default (to hold)' => [CoreStatus::ON_HOLD, Substatus::WAITING_FOR_CLIENT, Substatus::PAUSADO],
            'invalid substatus replaced by default (to archived)' => [CoreStatus::ARCHIVED, Substatus::WAITING_FOR_CLIENT, Substatus::FINALIZADA],
        ];
    }

    #[DataProvider('substatusAcrossStatusChange')]
    public function test_substatus_is_kept_or_replaced_when_only_the_core_status_changes(CoreStatus $new, Substatus $current, ?Substatus $expected): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'substatus' => $current]);

        $order->update(['core_status' => $new]);

        $this->assertSame($expected, $order->fresh()->substatus);
    }

    /** @return array<string, array{Substatus}> */
    public static function globalSubstatuses(): array
    {
        return [
            'urgente' => [Substatus::URGENTE],
            'ticket' => [Substatus::TICKET],
            'potential customer' => [Substatus::POTENTIAL_CUSTOMER],
            'externo' => [Substatus::EXTERNO],
            'overdue' => [Substatus::OVERDUE],
            'almost overdue' => [Substatus::ALMOST_OVERDUE],
        ];
    }

    #[DataProvider('globalSubstatuses')]
    public function test_global_substatuses_survive_a_core_status_change(Substatus $substatus): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'substatus' => $substatus]);

        $order->update(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE]);

        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
        $this->assertSame($substatus, $order->fresh()->substatus);
    }

    public function test_moving_to_archived_stamps_archived_at_only_when_a_substatus_exists(): void
    {
        $withSubstatus = $this->makeOrder(['core_status' => CoreStatus::EN_PRODUCCION, 'substatus' => Substatus::ENVIADO_EN_ALTA]);
        $withoutSubstatus = $this->makeOrder(['core_status' => CoreStatus::EN_PRODUCCION, 'substatus' => null]);

        $withSubstatus->update(['core_status' => CoreStatus::ARCHIVED]);
        $withoutSubstatus->update(['core_status' => CoreStatus::ARCHIVED]);

        $this->assertNotNull($withSubstatus->fresh()->archived_at);
        $this->assertSame(Substatus::FINALIZADA, $withSubstatus->fresh()->substatus);
        $this->assertNull($withoutSubstatus->fresh()->archived_at);
        $this->assertSame(Substatus::FINALIZADA, $withoutSubstatus->fresh()->substatus);
    }

    public function test_leaving_archived_clears_archived_at(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()->subDay()]);

        $order->update(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED]);

        $order->refresh();
        $this->assertNull($order->archived_at);
        $this->assertNull($order->substatus);
    }

    // ---- substatus changes alone: core status follows -------------------------------------------------------

    /** @return array<string, array{CoreStatus, Substatus, CoreStatus}> */
    public static function substatusDrivesStatus(): array
    {
        return [
            'bloqueada' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, Substatus::BLOQUEADA, CoreStatus::ENTRANTE],
            'falta aprobacion estimado' => [CoreStatus::TO_DO_TODAY, Substatus::FALTA_APROBACION_ESTIMADO, CoreStatus::ENTRANTE],
            'cambios camila from entrante' => [CoreStatus::ENTRANTE, Substatus::CAMBIOS_CAMILA, CoreStatus::ENVIADO_A_CAMILA],
            'cambios camila from client' => [CoreStatus::ENVIADO_AL_CLIENTE, Substatus::CAMBIOS_CAMILA, CoreStatus::ENVIADO_A_CAMILA],
            'waiting for client' => [CoreStatus::TO_DO_TODAY, Substatus::WAITING_FOR_CLIENT, CoreStatus::ENVIADO_AL_CLIENTE],
            'cambios cliente' => [CoreStatus::ADRIAN_ORDERS_RECEIVED, Substatus::CAMBIOS_CLIENTE, CoreStatus::ENVIADO_AL_CLIENTE],
            'no respuesta' => [CoreStatus::TO_DO_TODAY, Substatus::NO_RESPUESTA, CoreStatus::ENVIADO_AL_CLIENTE],
            'pausado' => [CoreStatus::TO_DO_TODAY, Substatus::PAUSADO, CoreStatus::ON_HOLD],
            'esperando permiso' => [CoreStatus::TO_DO_TODAY, Substatus::ESPERANDO_PERMISO, CoreStatus::ON_HOLD],
            'customer service required' => [CoreStatus::ENVIADO_AL_CLIENTE, Substatus::CUSTOMER_SERVICE_REQUIRED, CoreStatus::ON_HOLD],
            'enviado en alta' => [CoreStatus::TO_DO_TODAY, Substatus::ENVIADO_EN_ALTA, CoreStatus::EN_PRODUCCION],
            'finalizada' => [CoreStatus::EN_PRODUCCION, Substatus::FINALIZADA, CoreStatus::ARCHIVED],
            'cancelada' => [CoreStatus::TO_DO_TODAY, Substatus::CANCELADA, CoreStatus::ARCHIVED],
            'cancelada por cliente' => [CoreStatus::TO_DO_TODAY, Substatus::CANCELADA_POR_CLIENTE, CoreStatus::ARCHIVED],
            'cancelada por camila' => [CoreStatus::TO_DO_TODAY, Substatus::CANCELADA_POR_CAMILA, CoreStatus::ARCHIVED],
            'no realizada' => [CoreStatus::TO_DO_TODAY, Substatus::NO_REALIZADA_TRANSFERIDA, CoreStatus::ARCHIVED],
            'cliente no responsive' => [CoreStatus::TO_DO_TODAY, Substatus::CLIENTE_NO_RESPONSIVE, CoreStatus::ARCHIVED],
        ];
    }

    #[DataProvider('substatusDrivesStatus')]
    public function test_changing_only_the_substatus_moves_the_core_status(CoreStatus $from, Substatus $substatus, CoreStatus $expected): void
    {
        $order = $this->makeOrder(['core_status' => $from, 'substatus' => null]);

        $order->update(['substatus' => $substatus]);

        $order->refresh();
        $this->assertSame($expected, $order->core_status);
        $this->assertSame($substatus, $order->substatus);
        $this->assertSame($expected === CoreStatus::ARCHIVED, $order->archived_at !== null);
    }

    /** @return array<string, array{CoreStatus, Substatus}> */
    public static function substatusesThatKeepTheStatus(): array
    {
        $cases = [];
        foreach ([CoreStatus::EURALIZ_ORDERS_RECEIVED, CoreStatus::ADRIAN_ORDERS_RECEIVED, CoreStatus::CESAR_ORDERS_RECEIVED, CoreStatus::TO_DO_TODAY] as $status) {
            foreach ([Substatus::PONER_EN_ALTA, Substatus::AJUSTES_PRODUCCION, Substatus::CAMBIOS_CAMILA] as $substatus) {
                $cases[$status->name.' + '.$substatus->name] = [$status, $substatus];
            }
        }

        return $cases;
    }

    #[DataProvider('substatusesThatKeepTheStatus')]
    public function test_alta_adjustment_and_camila_substatuses_keep_a_designer_queue_or_working_today_status(CoreStatus $status, Substatus $substatus): void
    {
        $order = $this->makeOrder(['core_status' => $status, 'substatus' => null]);

        $order->update(['substatus' => $substatus]);

        $order->refresh();
        $this->assertSame($status, $order->core_status);
        $this->assertSame($substatus, $order->substatus);
    }

    #[DataProvider('globalSubstatuses')]
    public function test_global_substatuses_never_move_the_core_status(Substatus $substatus): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_A_CAMILA, 'substatus' => null]);

        $order->update(['substatus' => $substatus]);

        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->fresh()->core_status);
    }

    public function test_poner_en_alta_and_ajustes_never_map_to_a_status_outside_designer_queues(): void
    {
        foreach ([Substatus::PONER_EN_ALTA, Substatus::AJUSTES_PRODUCCION] as $substatus) {
            $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => null]);

            $order->update(['substatus' => $substatus]);

            $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->fresh()->core_status);
        }
    }

    public function test_moving_a_substatus_out_of_archived_clears_archived_at(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => now()->subDay()]);

        $order->update(['substatus' => Substatus::BLOQUEADA]);

        $order->refresh();
        $this->assertSame(CoreStatus::ENTRANTE, $order->core_status);
        $this->assertNull($order->archived_at);
    }

    public function test_changing_to_another_archived_substatus_keeps_the_archive_date(): void
    {
        $archivedAt = now()->subDay()->startOfMinute();
        $order = $this->makeOrder(['core_status' => CoreStatus::ARCHIVED, 'substatus' => Substatus::FINALIZADA, 'archived_at' => $archivedAt]);

        $order->update(['substatus' => Substatus::CANCELADA]);

        $this->assertTrue($order->fresh()->archived_at->equalTo($archivedAt));
    }

    // ---- both or neither change ---------------------------------------------------------------------------

    public function test_changing_both_core_status_and_substatus_skips_normalization(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY, 'substatus' => null]);

        $order->update(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'substatus' => Substatus::CAMBIOS_CLIENTE]);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame(Substatus::CAMBIOS_CLIENTE, $order->substatus);
    }

    public function test_changing_other_attributes_does_not_touch_status_or_substatus(): void
    {
        $order = $this->makeOrder(['core_status' => CoreStatus::ENVIADO_AL_CLIENTE, 'substatus' => Substatus::PONER_EN_ALTA]);

        $order->update(['task_name' => 'Renamed']);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::PONER_EN_ALTA, $order->substatus);
    }

    // ---- notifications -------------------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationsOf(User $user, string $eventType, Order $order): array
    {
        return $user->notifications()->get()
            ->map(fn ($n) => $n->data)
            ->filter(fn ($d) => $d['event_type'] === $eventType && $d['order_id'] === $order->id)
            ->values()
            ->all();
    }

    public function test_creating_an_order_notifies_managers(): void
    {
        $admin = User::factory()->create();

        $order = $this->makeOrder();

        $notes = $this->notificationsOf($admin, 'new_order', $order);
        $this->assertCount(1, $notes);
        $this->assertSame('New Order', $notes[0]['label']);
        $this->assertSame([], $this->notificationsOf($admin, 'order_urgent', $order));
    }

    public function test_creating_an_urgent_order_also_sends_an_urgent_notification(): void
    {
        $admin = User::factory()->create();

        $order = $this->makeOrder(['flags' => [Substatus::URGENTE->value]]);

        $notes = $this->notificationsOf($admin, 'order_urgent', $order);
        $this->assertCount(1, $notes);
        $this->assertTrue($notes[0]['is_urgent']);
    }

    public function test_a_status_change_notification_carries_the_transition_text(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(['core_status' => CoreStatus::TO_DO_TODAY]);

        $order->update(['core_status' => CoreStatus::ON_HOLD]);

        $notes = $this->notificationsOf($admin, 'status_changed', $order);
        $this->assertCount(1, $notes);
        $this->assertSame(CoreStatus::TO_DO_TODAY->label().' ➔ '.CoreStatus::ON_HOLD->label(), $notes[0]['detail_text']);
    }

    public function test_blocking_and_unblocking_substatus_changes_notify(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(['core_status' => CoreStatus::ENTRANTE, 'substatus' => null]);

        $order->update(['substatus' => Substatus::BLOQUEADA]);
        $this->assertCount(1, $this->notificationsOf($admin, 'order_blocked', $order));
        $this->assertTrue($this->notificationsOf($admin, 'order_blocked', $order)[0]['is_urgent']);

        $order->update(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'substatus' => null]);
        $this->assertCount(1, $this->notificationsOf($admin, 'order_unblocked', $order));
    }

    public function test_alta_substatuses_notify_approval(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'substatus' => null]);

        $order->update(['substatus' => Substatus::PONER_EN_ALTA]);

        $notes = $this->notificationsOf($admin, 'order_approved_alta', $order);
        $this->assertCount(1, $notes);
        $this->assertSame('Pending ALTA', $notes[0]['detail_text']);
    }

    public function test_urgent_substatus_notifies(): void
    {
        $admin = User::factory()->create();
        $order = $this->makeOrder(['core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED, 'substatus' => null]);

        $order->update(['substatus' => Substatus::URGENTE]);

        $this->assertCount(1, $this->notificationsOf($admin, 'order_urgent', $order));
    }
}
