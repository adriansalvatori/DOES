<?php

namespace App\Domain\Delivery\Enums;

enum DeliverableStatus: string
{
    case INTAKE = 'intake';
    case READY = 'ready';
    case IN_DESIGN = 'in_design';
    case INTERNAL_REVIEW = 'internal_review';
    case CLIENT_REVIEW = 'client_review';
    case PRODUCTION_FILES = 'production_files';
    case READY_FOR_PRODUCTION = 'ready_for_production';
    case PRODUCTION_QUEUE = 'production_queue';
    case IN_PRODUCTION = 'in_production';
    case PRODUCED = 'produced';
    case FULFILLMENT = 'fulfillment';
    case DONE = 'done';

    public function label(): string
    {
        return __('enums.deliverable_status.'.$this->value);
    }

    /**
     * The board area (tab) the status belongs to.
     */
    public function area(): Area
    {
        return match ($this) {
            self::INTAKE,
            self::READY,
            self::IN_DESIGN,
            self::INTERNAL_REVIEW,
            self::CLIENT_REVIEW,
            self::PRODUCTION_FILES,
            self::READY_FOR_PRODUCTION => Area::DESIGN,
            self::PRODUCTION_QUEUE,
            self::IN_PRODUCTION,
            self::PRODUCED => Area::PRODUCTION,
            self::FULFILLMENT,
            self::DONE => Area::FULFILLMENT,
        };
    }

    /**
     * One-based position of the status along the delivery flow.
     */
    public function position(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * The status one step ahead along the flow, or null after the last one.
     */
    public function next(): ?self
    {
        return self::cases()[$this->position()] ?? null;
    }

    /**
     * The status one step behind along the flow, or null before the first one.
     */
    public function previous(): ?self
    {
        return $this->position() > 1 ? self::cases()[$this->position() - 2] : null;
    }

    public function isClosed(): bool
    {
        return $this === self::DONE;
    }

    /**
     * @return list<self>
     */
    public static function inArea(Area $area): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->area() === $area));
    }
}
