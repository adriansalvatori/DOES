<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\WorkOrder;

final readonly class WorkOrderListRow
{
    public function __construct(
        public WorkOrder $workOrder,
        public WorkOrderTab $tab,
        public int $deliverablesCount,
        public int $openCount,
        public int $approvedCount,
        public int $receivedCount,
        public PromiseHealth $health,
    ) {}
}
