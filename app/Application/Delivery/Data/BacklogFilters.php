<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\DeliverableStatus;

final readonly class BacklogFilters
{
    /**
     * @param  string  $sortBy  created_desc|created_asc|due_date_asc|company_asc
     */
    public function __construct(
        public ?string $search = null,
        public ?DeliverableStatus $status = null,
        public ?int $assigneeId = null,
        public ?string $company = null,
        public ?int $contactId = null,
        public string $sortBy = 'created_desc',
    ) {}
}
