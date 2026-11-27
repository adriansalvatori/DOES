<?php

namespace App\Application\Delivery\Data;

final readonly class OverviewFilters
{
    /**
     * @param  string  $tab  all|active|production|done|backlog
     * @param  ?string  $installation  An installation type, or "NONE" for work orders without any.
     * @param  ?string  $review  CS, CAMILA, or "NONE" for work orders whose estimate nobody reviewed yet.
     * @param  bool  $urgent  Only work orders with an open urgent deliverable. Combines with the tab.
     * @param  bool  $withDueDate  Only work orders with a promised date. Combines with the tab.
     */
    public function __construct(
        public string $tab = 'all',
        public ?string $search = null,
        public ?string $workOrderNumber = null,
        public ?int $clientId = null,
        public ?int $assigneeId = null,
        public ?string $installation = null,
        public ?int $workOrderId = null,
        public ?string $review = null,
        public bool $urgent = false,
        public bool $withDueDate = false,
    ) {}

    /**
     * The same filters on another tab, for the tab counts.
     */
    public function onTab(string $tab): self
    {
        return new self($tab, $this->search, $this->workOrderNumber, $this->clientId, $this->assigneeId, $this->installation, $this->workOrderId, $this->review, $this->urgent, $this->withDueDate);
    }
}
