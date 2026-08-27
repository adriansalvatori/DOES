<?php

namespace App\Livewire\Resolver;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\TrelloSyncService;
use Livewire\Attributes\On;
use Livewire\Component;

class ResolverList extends Component
{
    public bool $showUnblockModal = false;

    public ?int $unblockOrderId = null;

    public string $unblockReason = '';

    public ?Order $unblockingOrder = null;

    #[On('order-updated')]
    public function refreshList(): void
    {
        // Re-renders the Action Required view automatically when orders or tasks are updated in card flyout
    }

    public function openUnblockModal($orderId)
    {
        $this->unblockOrderId = $orderId;
        $this->unblockingOrder = Order::find($orderId);
        $this->unblockReason = '';
        $this->showUnblockModal = true;
    }

    public function closeUnblockModal()
    {
        $this->showUnblockModal = false;
        $this->unblockOrderId = null;
        $this->unblockingOrder = null;
        $this->unblockReason = '';
    }

    public function selectPresetReason($preset)
    {
        $this->unblockReason = $preset;
    }

    public function confirmUnblock()
    {
        if (! $this->unblockOrderId) {
            return;
        }

        $this->validate([
            'unblockReason' => 'required|string|min:3',
        ], [
            'unblockReason.required' => __('Ingresa el motivo o forma en que se resolvió el bloqueo.'),
            'unblockReason.min' => __('El motivo debe contener al menos 3 caracteres.'),
        ]);

        $order = Order::findOrFail($this->unblockOrderId);
        $order->unblock($this->unblockReason);

        session()->flash('message', __('Orden :company desbloqueada y devuelta a la lista del diseñador.', ['company' => $order->company_name]));

        $this->closeUnblockModal();
        $this->dispatch('order-updated');
    }

    public function sendToCamila($orderId)
    {
        $order = Order::findOrFail($orderId);
        $prev = $order->core_status;
        $order->update([
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'done_today' => true,
        ]);
        app(AutomationEngine::class)->handleStatusChanged($order, $prev, CoreStatus::ENVIADO_A_CAMILA);
        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
            }
        }
        session()->flash('message', __('Orden :company enviada a Camila.', ['company' => $order->company_name]));
        $this->dispatch('order-updated');
    }

    public function sendToClient($orderId)
    {
        $order = Order::findOrFail($orderId);
        $prev = $order->core_status;
        $order->update([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'done_today' => true,
        ]);
        app(AutomationEngine::class)->handleStatusChanged($order, $prev, CoreStatus::ENVIADO_AL_CLIENTE);
        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
            }
        }
        session()->flash('message', __('Orden :company enviada al Cliente.', ['company' => $order->company_name]));
        $this->dispatch('order-updated');
    }

    public function sendToProduction($orderId)
    {
        $order = Order::findOrFail($orderId);
        $prev = $order->core_status;
        $order->update([
            'core_status' => CoreStatus::EN_PRODUCCION,
            'substatus' => Substatus::ENVIADO_EN_ALTA,
            'done_today' => true,
        ]);
        app(AutomationEngine::class)->handleStatusChanged($order, $prev, CoreStatus::EN_PRODUCCION);
        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
            }
        }
        session()->flash('message', __('Orden :company enviada a Producción.', ['company' => $order->company_name]));
        $this->dispatch('order-updated');
    }

    public function keepOnPendingWork($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['done_today' => false]);
        session()->flash('message', __('Orden :company conservada en trabajo pendiente del diseñador.', ['company' => $order->company_name]));
        $this->dispatch('order-updated');
    }

    public function render()
    {
        $blockedOrders = Order::inWorkspace()->with(['designer', 'relatedTasks'])
            ->where(function ($q) {
                $q->where('substatus', Substatus::BLOQUEADA)
                    ->orWhere('substatus', Substatus::FALTA_APROBACION_ESTIMADO)
                    ->orWhere('customer_service_required', true)
                    ->orWhere(function ($dt) {
                        $dt->where('done_today', true)
                            ->whereNotIn('core_status', [
                                CoreStatus::ENVIADO_A_CAMILA,
                                CoreStatus::ENVIADO_AL_CLIENTE,
                                CoreStatus::EN_PRODUCCION,
                                CoreStatus::ON_HOLD,
                                CoreStatus::ARCHIVED,
                            ]);
                    })
                    ->orWhere(function ($m) {
                        $m->where('approved', true)->where('measures_confirmed', false);
                    });
            })->get();

        $resolverTasks = RelatedTask::whereHas('order', function ($q) {
            $q->inWorkspace()->whereNotIn('core_status', [
                CoreStatus::ENVIADO_A_CAMILA,
                CoreStatus::ENVIADO_AL_CLIENTE,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ON_HOLD,
                CoreStatus::ARCHIVED,
            ]);
        })
            ->with(['order', 'assignee'])
            ->where('status', 'todo')
            ->whereIn('type', [
                RelatedTaskType::RESOLVER,
                RelatedTaskType::SOLICITAR_INFO,
                RelatedTaskType::CORREO_ATRASO,
            ])->get();

        return view('livewire.resolver.resolver-list', [
            'blockedOrders' => $blockedOrders,
            'resolverTasks' => $resolverTasks,
        ])->layout('components.layouts.app', ['title' => 'Action Required - Kudos Design Ops']);
    }
}
