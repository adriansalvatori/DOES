<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Support\Collection;

final readonly class BoardColumn
{
    /**
     * @param  Collection<int, Deliverable>  $cards  Capped list; `$total` is the real count.
     */
    public function __construct(
        public DeliverableStatus $status,
        public int $total,
        public Collection $cards,
    ) {}

    public function hiddenCount(): int
    {
        return max(0, $this->total - $this->cards->count());
    }
}
