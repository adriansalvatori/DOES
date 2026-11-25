<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Resolver\ResolverList;
use App\Models\User;
use Livewire\Livewire;

class EntryResolverTest extends EntryTestCase
{
    public function test_designers_are_redirected_to_kanban(): void
    {
        $this->actingAs(User::factory()->designer()->create());

        Livewire::test(ResolverList::class)->assertRedirect(route('kanban'));
    }

    public function test_send_to_camila_marks_done_and_creates_follow_up(): void
    {
        $order = $this->makeOrder();

        Livewire::test(ResolverList::class)
            ->call('sendToCamila', $order->id)
            ->assertDispatched('order-updated');

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_A_CAMILA, $order->core_status);
        $this->assertTrue($order->done_today);
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA->value,
        ]);
    }

    public function test_send_to_client_sets_waiting_for_client(): void
    {
        $order = $this->makeOrder();

        Livewire::test(ResolverList::class)->call('sendToClient', $order->id);

        $order->refresh();
        $this->assertSame(CoreStatus::ENVIADO_AL_CLIENTE, $order->core_status);
        $this->assertSame(Substatus::WAITING_FOR_CLIENT, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_send_to_production_requires_approval(): void
    {
        $order = $this->makeOrder(['approved' => false]);

        Livewire::test(ResolverList::class)
            ->call('sendToProduction', $order->id)
            ->assertDispatched('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);

        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_send_to_production_when_approved_moves_order(): void
    {
        $order = $this->makeOrder(['approved' => true]);

        Livewire::test(ResolverList::class)->call('sendToProduction', $order->id);

        $order->refresh();
        $this->assertSame(CoreStatus::EN_PRODUCCION, $order->core_status);
        $this->assertSame(Substatus::ENVIADO_EN_ALTA, $order->substatus);
        $this->assertTrue($order->done_today);
    }

    public function test_keep_on_pending_work_clears_done_today_only(): void
    {
        $order = $this->makeOrder(['done_today' => true]);

        Livewire::test(ResolverList::class)->call('keepOnPendingWork', $order->id);

        $order->refresh();
        $this->assertFalse($order->done_today);
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
    }

    public function test_confirm_unblock_unblocks_order(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(ResolverList::class)
            ->call('openUnblockModal', $order->id)
            ->assertSet('showUnblockModal', true)
            ->call('selectPresetReason', 'Cliente respondió')
            ->assertSet('unblockReason', 'Cliente respondió')
            ->call('confirmUnblock')
            ->assertSet('showUnblockModal', false);

        $order->refresh();
        $this->assertSame(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_confirm_unblock_requires_reason(): void
    {
        $order = $this->makeOrder();
        $order->block('FALTAN MEDIDAS', null, null, false, 'Usuario');

        Livewire::test(ResolverList::class)
            ->call('openUnblockModal', $order->id)
            ->call('confirmUnblock')
            ->assertHasErrors(['unblockReason']);

        $this->assertSame(CoreStatus::ENTRANTE, $order->fresh()->core_status);
    }
}
