<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ActionRequiredResolverService;
use App\Services\StatusTransitionService;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updating" event.
     */
    public function updating(Order $order): void
    {
        if ($order->isDirty('core_status') && ! $order->isDirty('substatus')) {
            app(StatusTransitionService::class)->normalizeSubstatusOnCoreStatusChange($order, $order->core_status);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // 1. Auto-evaluate Action Required & Auto-Resolution
        if ($order->wasChanged(['measures_confirmed', 'estimate_approved', 'customer_service_required', 'designer_id', 'core_status', 'substatus', 'scheduled_date'])) {
            try {
                app(ActionRequiredResolverService::class)->evaluateAndAutoResolve($order);
            } catch (\Throwable $e) {
                Log::warning("OrderObserver failed auto-resolution evaluation for order #{$order->id}: ".$e->getMessage());
            }
        }

        // 2. Sync changes back to Trello
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
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
                Log::warning("OrderObserver failed to sync order #{$order->id} to Trello: ".$e->getMessage());
            }
        }
    }
}
