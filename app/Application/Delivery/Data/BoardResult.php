<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class BoardResult
{
    /**
     * @param  list<BoardColumn>  $columns
     */
    public function __construct(
        public Area $area,
        public array $columns,
    ) {}

    /**
     * Rows by assignee (unassigned last), each holding the capped cards per status.
     *
     * @return list<array{assignee: ?User, cells: array<string, Collection<int, Deliverable>>}>
     */
    public function lanes(): array
    {
        $assignees = [];
        $cells = [];

        foreach ($this->columns as $column) {
            foreach ($column->cards as $card) {
                $key = $card->assignee_id ?? 0;
                $assignees[$key] ??= $card->assignee;
                $cells[$key][$column->status->value][] = $card;
            }
        }

        uksort($cells, function (int $left, int $right) use ($assignees): int {
            if ($left === 0 || $right === 0) {
                return $left === 0 ? 1 : -1;
            }

            return strcasecmp((string) $assignees[$left]?->name, (string) $assignees[$right]?->name);
        });

        $lanes = [];
        foreach ($cells as $key => $byStatus) {
            $lanes[] = [
                'assignee' => $assignees[$key] ?? null,
                'cells' => array_map(fn (array $cards): Collection => collect($cards), $byStatus),
            ];
        }

        return $lanes;
    }
}
