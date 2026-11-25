<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Kanban\Board;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanReasonSheetTest extends TestCase
{
    use RefreshDatabase;

    private function order(CoreStatus $status): Order
    {
        return Order::create([
            'company_name' => 'Acme Corp',
            'task_name' => 'Logo Design',
            'core_status' => $status,
            'in_workspace' => true,
        ]);
    }

    public function test_on_hold_sheet_opens_validates_and_confirms(): void
    {
        $order = $this->order(CoreStatus::TO_DO_TODAY);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ON_HOLD->value)
            ->assertSet('showOnHoldModal', true)
            ->assertSee(__('Motivo para poner en On Hold'))
            ->call('confirmOnHold')
            ->assertHasErrors(['onHoldReason'])
            ->set('onHoldReason', 'Esperando presupuesto')
            ->call('confirmOnHold')
            ->assertSet('showOnHoldModal', false);

        $this->assertEquals(CoreStatus::ON_HOLD, $order->fresh()->core_status);
    }

    public function test_on_hold_sheet_can_be_cancelled(): void
    {
        $order = $this->order(CoreStatus::TO_DO_TODAY);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ON_HOLD->value)
            ->call('cancelOnHold')
            ->assertSet('showOnHoldModal', false)
            ->assertDontSee(__('Motivo para poner en On Hold'));

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_resume_sheet_opens_and_confirms(): void
    {
        $order = $this->order(CoreStatus::ON_HOLD);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::TO_DO_TODAY->value)
            ->assertSet('showResumeModal', true)
            ->assertSee(__('Motivo para reanudar la orden'))
            ->set('resumeReason', 'Cliente aprobó')
            ->call('confirmResume')
            ->assertSet('showResumeModal', false);

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->fresh()->core_status);
    }

    public function test_block_sheet_shows_presets_other_field_and_cs_checkbox(): void
    {
        $order = $this->order(CoreStatus::TO_DO_TODAY);

        Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ENTRANTE->value)
            ->assertSee(__('Bloquear Orden'))
            ->assertSee(__('Falta logo / arte'))
            ->assertSee(__('Requiere atención / seguimiento del cliente o responsable'))
            ->assertDontSee(__('Especificar otro motivo'))
            ->set('blockReason', 'OTROS')
            ->assertSee(__('Especificar otro motivo'))
            ->set('blockReasonOther', 'Material especial')
            ->set('requireCustomerService', true)
            ->call('confirmBlock')
            ->assertSet('showBlockModal', false);

        $fresh = $order->fresh();
        $this->assertEquals(CoreStatus::ENTRANTE, $fresh->core_status);
        $this->assertTrue((bool) $fresh->customer_service_required);
    }

    public function test_unblock_sheet_opens_and_confirms(): void
    {
        $order = $this->order(CoreStatus::TO_DO_TODAY);

        $board = Livewire::test(Board::class)
            ->call('moveOrder', $order->id, CoreStatus::ENTRANTE->value)
            ->set('blockReason', 'FALTAN MEDIDAS')
            ->call('confirmBlock');

        $board->call('openUnblockModal', $order->id)
            ->assertSet('showUnblockModal', true)
            ->assertSee(__('Desbloquear Orden'))
            ->assertSee(__('Medidas confirmadas'))
            ->call('confirmUnblock')
            ->assertHasErrors(['unblockReason'])
            ->set('unblockReason', 'Medidas confirmadas')
            ->call('confirmUnblock')
            ->assertSet('showUnblockModal', false);

        $this->assertFalse($order->fresh()->isBlocked());
    }

    public function test_active_filters_render_as_chips(): void
    {
        Livewire::test(Board::class)
            ->set('companyFilter', 'Acme Corp')
            ->assertSeeHtml('data-testid="kanban-active-filters"')
            ->assertSee('Acme Corp');
    }
}
