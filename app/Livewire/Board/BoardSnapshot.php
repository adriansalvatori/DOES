<?php

namespace App\Livewire\Board;

use App\Application\Delivery\Data\BoardColumn;
use App\Application\Delivery\Data\BoardFilters;
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

    private const COLORS = 'board.snapshot.designer-colors';

    public static function column(User $viewer, DeliverableStatus $status, BoardFilters $filters, int $cap): BoardColumn
    {
        $key = implode('|', [
            $viewer->getKey(),
            $status->area()->value,
            $filters->assigneeId,
            $filters->clientId,
            implode(',', array_map(fn ($flag): string => $flag->value, $filters->flags)),
            $filters->search,
            $cap,
        ]);

        $attributes = request()->attributes;
        $results = $attributes->get(self::RESULTS, []);
        $result = $results[$key] ??= app(BoardQuery::class)->run($viewer, $status->area(), $filters, $cap);
        $attributes->set(self::RESULTS, $results);

        foreach ($result->columns as $column) {
            if ($column->status === $status) {
                return $column;
            }
        }

        return new BoardColumn($status, 0, collect());
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
    }
}
