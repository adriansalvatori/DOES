<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<int>  $deliverableIds
     */
    public function __construct(
        public WorkOrder $workOrder,
        public array $deliverableIds,
        public ?User $actor = null,
    ) {}
}
