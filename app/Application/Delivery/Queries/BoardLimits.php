<?php

namespace App\Application\Delivery\Queries;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Scheduling\WipPolicy;
use App\Models\Setting;

/**
 * The limits an admin or coordinator sets in Board & WIP: the default WIP limit for a person and how many cards each board column shows.
 */
class BoardLimits
{
    public const DEFAULT_COLUMN_CAP = 25;

    public function defaultWipLimit(): int
    {
        return max(1, (int) Setting::get('default_wip_limit', WipPolicy::DEFAULT_LIMIT));
    }

    public function defaultColumnCap(): int
    {
        return max(1, (int) Setting::get('board_column_cap', self::DEFAULT_COLUMN_CAP));
    }

    /**
     * @return array<string, int> Overrides by status code.
     */
    public function columnCapOverrides(): array
    {
        $stored = Setting::get('board_column_caps', []);
        $stored = is_array($stored) ? $stored : (json_decode((string) $stored, true) ?? []);

        return array_map('intval', array_filter($stored, fn ($cap): bool => is_numeric($cap) && (int) $cap > 0));
    }

    public function columnCap(DeliverableStatus $status): int
    {
        return $this->columnCapOverrides()[$status->value] ?? $this->defaultColumnCap();
    }
}
