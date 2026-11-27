<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * One Overview line (Excel style) for a work order, with its deliverables for the accordion.
 */
final readonly class OverviewRow
{
    /**
     * @param  Collection<int, Deliverable>  $deliverables
     * @param  list<string>  $designers  Distinct assignee names.
     * @param  list<DeliverableFlag>  $flags
     * @param  array<string, int>  $statusCounts  Deliverable count per status code.
     * @param  ?string  $reviewStatus  Who reviewed the estimate (CS or CAMILA), from the first deliverable that has it.
     * @param  list<string>  $varies  Line fields whose value differs between the deliverables shown.
     */
    public function __construct(
        public WorkOrder $workOrder,
        public Collection $deliverables,
        public ?string $processedAt,
        public ?string $emailDate,
        public ?string $productionNote,
        public ?string $deliveryNote,
        public array $designers,
        public array $flags,
        public array $statusCounts,
        public bool $checked,
        public PromiseHealth $health,
        public ?string $reviewStatus = null,
        public array $varies = [],
    ) {}

    public function varies(string $field): bool
    {
        return in_array($field, $this->varies, true);
    }
}
