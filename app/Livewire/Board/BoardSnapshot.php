<?php

namespace App\Livewire\Board;

use App\Application\Delivery\Data\BoardColumn;
use App\Application\Delivery\Data\BoardFilters;
use App\Application\Delivery\Queries\BoardLimits;
use App\Application\Delivery\Queries\BoardQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Models\Designer;
use App\Models\User;

/**
 * Lets the column components of one request share a single BoardQuery run per area. A move that refreshes the
 * source and target columns therefore reads the board once, and the first page render does too. The result lives
 * on the request, so nothing leaks between requests.
 */
class BoardSnapshot
{
    private const RESULTS = 'board.snapshot.results';

    private const CAPS = 'board.snapshot.column-caps';

    private const COLORS = 'board.snapshot.designer-colors';

    /**
     * The column's cards. One run per area and filter set serves every column at its Board & WIP cap; a column that
     * asked for more cards (Show more) gets its own run.
     */
    public static function column(User $viewer, DeliverableStatus $status, BoardFilters $filters, int $cap): BoardColumn
    {
        $area = $status->area();
        $baseKey = implode('|', [
            $viewer->getKey(),
            $area->value,
            $filters->assigneeId,
            $filters->clientId,
            implode(',', array_map(fn ($flag): string => $flag->value, $filters->flags)),
            $filters->search,
        ]);

        $attributes = request()->attributes;
        $results = $attributes->get(self::RESULTS, []);

        $key = $baseKey;
        $caps = [];
        foreach (DeliverableStatus::inArea($area) as $areaStatus) {
            $caps[$areaStatus->value] = self::cap($areaStatus);
        }

        if ($cap > $caps[$status->value]) {
            $key = $baseKey.'|'.$status->value.'|'.$cap;
            $caps[$status->value] = $cap;
        }

        $result = $results[$key] ??= app(BoardQuery::class)->run($viewer, $area, $filters, $caps);
        $attributes->set(self::RESULTS, $results);

        foreach ($result->columns as $column) {
            if ($column->status === $status) {
                return $column;
            }
        }

        return new BoardColumn($status, 0, collect());
    }

    /**
     * Cards a column shows per page, from Board & WIP. Settings are read once per request.
     */
    public static function cap(DeliverableStatus $status): int
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::CAPS)) {
            $limits = app(BoardLimits::class);
            $attributes->set(self::CAPS, collect(DeliverableStatus::cases())
                ->mapWithKeys(fn (DeliverableStatus $each): array => [$each->value => $limits->columnCap($each)])
                ->all());
        }

        return $attributes->get(self::CAPS)[$status->value];
    }

    /**
     * Identity color of each designer by user id.
     *
     * @return array<int, string>
     */
    public static function designerColors(): array
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::COLORS)) {
            $attributes->set(self::COLORS, Designer::query()
                ->whereNotNull('user_id')
                ->whereNotNull('hex_color')
                ->pluck('hex_color', 'user_id')
                ->all());
        }

        return $attributes->get(self::COLORS);
    }

    /**
     * Drops what this request already read, after a write.
     */
    public static function flush(): void
    {
        request()->attributes->remove(self::RESULTS);
        request()->attributes->remove(self::CAPS);
    }
}
