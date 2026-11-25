<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\DeliverableFlag;

final readonly class BoardFilters
{
    /**
     * @param  list<DeliverableFlag>  $flags
     */
    public function __construct(
        public ?int $assigneeId = null,
        public ?int $clientId = null,
        public array $flags = [],
        public ?string $search = null,
    ) {}
}
