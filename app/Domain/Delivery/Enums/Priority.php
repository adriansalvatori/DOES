<?php

namespace App\Domain\Delivery\Enums;

/**
 * How soon a deliverable should be worked on. Every deliverable starts at medium.
 */
enum Priority: string
{
    case LOWEST = 'lowest';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case HIGHEST = 'highest';

    public function label(): string
    {
        return __('enums.priority.'.$this->value);
    }

    /**
     * 1 (lowest) to 5 (highest), for sorting.
     */
    public function rank(): int
    {
        return match ($this) {
            self::LOWEST => 1,
            self::LOW => 2,
            self::MEDIUM => 3,
            self::HIGH => 4,
            self::HIGHEST => 5,
        };
    }

    /**
     * Lucide icon shown next to the work: chevrons up for high, down for low.
     */
    public function icon(): string
    {
        return match ($this) {
            self::LOWEST => 'chevrons-down',
            self::LOW => 'chevron-down',
            self::MEDIUM => 'equal',
            self::HIGH => 'chevron-up',
            self::HIGHEST => 'chevrons-up',
        };
    }

    /**
     * Highest first.
     *
     * @return list<self>
     */
    public static function descending(): array
    {
        return array_reverse(self::cases());
    }
}
