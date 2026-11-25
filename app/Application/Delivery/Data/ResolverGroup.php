<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\WorkOrder;

final readonly class ResolverGroup
{
    /**
     * @param  list<ResolverItem>  $items  Oldest first.
     */
    public function __construct(
        public WorkOrder $workOrder,
        public array $items,
        public int $oldestAgeInDays,
    ) {}
}
