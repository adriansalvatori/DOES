<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
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
