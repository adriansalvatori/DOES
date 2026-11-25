<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use Carbon\CarbonInterface;

/**
 * Everything the Create Order form collects.
 */
final readonly class CreateWorkOrderData
{
    /**
     * @param  list<array{name: string, size?: string|null, material?: string|null, quantity?: int|null}>  $deliverables
     * @param  list<DeliverableFlag>  $flags
     * @param  list<string>  $installationTypes
     */
    public function __construct(
        public array $deliverables,
        public ?string $number = null,
        public bool $generateNumber = false,
        public ?int $clientId = null,
        public ?int $clientLocationId = null,
        public ?int $contactId = null,
        public ?string $description = null,
        public ?string $phaseName = null,
        public ?FulfillmentMethod $fulfillmentMethod = null,
        public array $flags = [],
        public ?int $assigneeId = null,
        public ?CarbonInterface $promisedDate = null,
        public ?string $trelloLink = null,
        public ?CarbonInterface $createdAt = null,
        public array $installationTypes = [],
        public ?string $estimateInvoiceNumber = null,
        public ?string $notes = null,
    ) {}
}
