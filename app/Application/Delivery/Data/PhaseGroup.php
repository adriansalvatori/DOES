<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use Illuminate\Support\Collection;

final readonly class PhaseGroup
{
    /**
     * @param  ?Phase  $phase  Null for the implicit batch (work order without phases, or deliverables outside any phase).
     * @param  Collection<int, Deliverable>  $deliverables
     */
    public function __construct(
        public ?Phase $phase,
        public Collection $deliverables,
        public BatchReadiness $readiness,
    ) {}
}
