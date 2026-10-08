<?php

namespace App\Observers;

use App\Enums\CoreStatus;
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
            label: 'New Order',
            order: $order
        );

        if ($order->isUrgente()) {
            NotificationDispatcher::dispatch(
                eventType: 'order_urgent',
                label: 'Urgent',
                order: $order,
                isUrgent: true
            );
        }
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
            $prevCore = $order->getOriginal('core_status');
            $prevLabel = $prevCore instanceof CoreStatus ? $prevCore->label() : (string) $prevCore;
            $newLabel = $order->core_status?->label() ?? (string) $order->core_status;
            $detail = $prevLabel ? "{$prevLabel} ➔ {$newLabel}" : $newLabel;

            NotificationDispatcher::dispatch(
                eventType: 'status_changed',
                label: 'Status',
                order: $order,
                detailText: $detail
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
                    label: 'Blocked',
                    order: $order,
                    detailText: $order->substatus?->label() ?? $newSubValue,
                    isUrgent: true
                );
            } elseif (in_array($originalSubString, $blockedSubstatuses, true) && ! in_array($newSubValue, $blockedSubstatuses, true)) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_unblocked',
                    label: 'Unblocked',
                    order: $order
                );
            }

            if (in_array($newSubValue, [Substatus::PONER_EN_ALTA->value, Substatus::ENVIADO_EN_ALTA->value], true)) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_approved_alta',
                    label: 'Approved',
                    order: $order,
                    detailText: 'Pendiente ALTA'
                );
            }

            if ($newSubValue === Substatus::URGENTE->value) {
                NotificationDispatcher::dispatch(
                    eventType: 'order_urgent',
                    label: 'Urgent',
                    order: $order,
                    isUrgent: true
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

    /**
     * Handle the Order "deleted" event (soft-delete archives card on Trello, force-delete permanently deletes card on Trello).
     */
    public function deleted(Order $order): void
    {
        $cardId = $order->trello_card_id;
        if (! $cardId) {
            return;
        }

        $isForceDeleting = $order->isForceDeleting();

        $syncCallback = function () use ($cardId, $isForceDeleting, $order) {
            try {
                $service = app(TrelloSyncService::class);
                if ($isForceDeleting) {
                    $service->deleteCard($cardId);
                } else {
                    $service->archiveCard($cardId);
                }
            } catch (\Throwable $e) {
                Log::warning("OrderObserver failed to sync card deletion for order #{$order->id}: ".$e->getMessage());
            }
        };

        if (app()->environment('testing')) {
            $syncCallback();
        } else {
            app()->terminating($syncCallback);
        }
    }

    /**
     * Handle the Order "restored" event (unarchives card on Trello).
     */
    public function restored(Order $order): void
    {
        $cardId = $order->trello_card_id;
        if (! $cardId) {
            return;
        }

        $syncCallback = function () use ($cardId, $order) {
            try {
                app(TrelloSyncService::class)->unarchiveCard($cardId);
            } catch (\Throwable $e) {
                Log::warning("OrderObserver failed to unarchive Trello card for order #{$order->id}: ".$e->getMessage());
            }
        };

        if (app()->environment('testing')) {
            $syncCallback();
        } else {
            app()->terminating($syncCallback);
        }
    }
}
