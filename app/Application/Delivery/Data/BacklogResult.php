<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class BacklogResult
{
    /**
     * @param  LengthAwarePaginator<int, WorkOrder>  $workOrders
     * @param  Collection<int, string>  $companies  Filter options.
     * @param  Collection<int, Contact>  $contacts  Filter options (responsible persons).
     */
    public function __construct(
        public LengthAwarePaginator $workOrders,
        public int $totalInBacklog,
        public Collection $companies,
        public Collection $contacts,
    ) {}
}
