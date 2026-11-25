<?php

namespace App\Application\Delivery\Data;

final readonly class OverviewFilters
{
    /**
     * @param  string  $tab  all|active|production|done|backlog|urgent|with_due_date
     * @param  ?string  $installation  An installation type, or "NONE" for work orders without any.
     */
    public function __construct(
        public string $tab = 'all',
        public ?string $search = null,
        public ?string $workOrderNumber = null,
        public ?int $clientId = null,
        public ?int $assigneeId = null,
        public ?string $installation = null,
    ) {}
}
