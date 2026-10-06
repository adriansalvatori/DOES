<?php

namespace App\Observers;

use App\Enums\Substatus;
use App\Models\Order;
use App\Services\ActionRequiredResolverService;
use App\Services\NotificationDispatcher;
use App\Services\StatusTransitionService;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        NotificationDispatcher::dispatch(
            eventType: 'new_order',
            title: __('Nueva Orden Creada'),
            message: __('Se registró la orden #:id: :task (:company)', [
                'id' => $order->id,
                'task' => $order->task_name ?? __('Sin Nombre'),
                'company' => $order->company_name ?? __('Cliente General'),
            ]),
            order: $order
        );
    }

    /**
     * Handle the Order "updating" event.
     */
    public function updating(Order $order): void
    {
        if ($order->isDirty('core_status') && ! $order->isDirty('substatus')) {
            app(StatusTransitionService::class)->normalizeSubstatusOnCoreStatusChange($order, $order->core_status);
        } elseif ($order->isDirty('substatus') && ! $order->isDirty('core_status')) {
            app(StatusTransitionService::class)->updateCoreStatusFromSubstatus($order, $order->substatus);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // 1. Notification triggers
        if ($order->wasChanged('core_status')) {
            NotificationDispatcher::dispatch(
                eventType: 'status_changed',
                title: __('Cambio de Estatus'),
                message: __('La orden #:id cambió a :status', [
                    'id' => $order->id,
                    'status' => $order->core_status?->label() ?? $order->core_status,
                ]),
                order: $order
            );
        }

        if ($order->wasChanged('substatus')) {
            $blockedSubstatuses = [Substatus::BLOQUEADA->value, Substatus::FALTA_INFORMACION->value];
            $newSubValue = $order->substatus?->value;
            $originalSubValue = $order->getOriginal('substatus');

            $originalSubString = $originalSubValue instanceof Substatus ? $originalSubValue->value : (string) $originalSubValue;

            if (in_array($newSubValue, $blockedSubstatuses, true)) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_blocked',
                    title: __('Orden Bloqueada'),
                    message: __('La orden #:id entró en estado de bloqueo (:substatus)', [
                        'id' => $order->id,
                        'substatus' => $order->substatus?->label() ?? $newSubValue,
                    ]),
                    order: $order,
                    isUrgent: true
                );
            } elseif (in_array($originalSubString, $blockedSubstatuses, true) && ! in_array($newSubValue, $blockedSubstatuses, true)) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_unblocked',
                    title: __('Orden Desbloqueada'),
                    message: __('La orden #:id ha sido desbloqueada', ['id' => $order->id]),
                    order: $order
                );
            }

            if (in_array($newSubValue, [Substatus::PONER_EN_ALTA->value, Substatus::ENVIADO_EN_ALTA->value], true)) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_approved_alta',
                    title: __('Orden Aprobada - Pendiente ALTA'),
                    message: __('La orden #:id ha sido aprobada y pasa a pendiente ALTA', ['id' => $order->id]),
                    order: $order
                );
            }
        }

        // 2. Auto-evaluate Action Required & Auto-Resolution
        if ($order->wasChanged(['measures_confirmed', 'estimate_approved', 'customer_service_required', 'designer_id', 'core_status', 'substatus', 'scheduled_date'])) {
            try {
                app(ActionRequiredResolverService::class)->evaluateAndAutoResolve($order);
            } catch (\Throwable $e) {
                Log::warning("OrderObserver failed auto-resolution evaluation for order #{$order->id}: ".$e->getMessage());
            }
        }

        // 3. Sync changes back to Trello
        if (! $order->in_workspace || ! $order->trello_card_id) {
            return;
        }

        $syncableFields = [
            'company_name',
            'task_name',
            'location_name',
            'responsible_person',
            'wo_number',
            'designer_id',
            'current_due_date',
            'core_status',
            'in_workspace',
        ];

        if ($order->wasChanged($syncableFields)) {
            $syncCallback = function () use ($order) {
                try {
                    app(TrelloSyncService::class)->updateCardOnTrello($order);
                } catch (\Throwable $e) {
                    Log::warning("OrderObserver failed to sync order #{$order->id} to Trello: ".$e->getMessage());
                }
            };

            if (app()->environment('testing')) {
                $syncCallback();
            } else {
                app()->terminating($syncCallback);
            }
        }
    }
}
