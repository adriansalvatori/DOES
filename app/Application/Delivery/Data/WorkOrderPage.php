<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Support\Collection;

final readonly class WorkOrderPage
{
    /**
     * @param  list<PhaseGroup>  $groups
     * @param  Collection<int, Alert>  $alerts  Open alerts of the work order and its deliverables.
     * @param  Collection<int, Deliverable>  $leftBehind
     */
    public function __construct(
        public WorkOrder $workOrder,
        public array $groups,
        public Collection $alerts,
        public Collection $leftBehind,
        public int $deliverablesCount,
        public int $approvedCount,
        public int $receivedCount,
        public PromiseHealth $health,
    ) {}
}
