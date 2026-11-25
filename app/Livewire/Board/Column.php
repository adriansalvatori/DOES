<?php

namespace App\Livewire\Board;

use App\Application\Delivery\Data\BoardColumn;
use App\Application\Delivery\Data\BoardFilters;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Scheduling\WipPolicy;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One board column. It renders on its own, so a move only re-sends its source and target columns.
 */
class Column extends Component
{
    public const PAGE_SIZE = 25;

    public string $status;

    public ?int $assigneeId = null;

    public ?int $clientId = null;

    /** @var list<string> */
    public array $flags = [];

    public string $search = '';

    public string $groupBy = 'designer';

    public int $pagesShown = 1;

    /**
     * Filters live on the page; they arrive here by event so a filter change is the only thing that re-sends every column.
     *
     * @param  array{assigneeId: ?int, clientId: ?int, flags: list<string>, search: string, groupBy: string}  $filters
     */
    #[On('board-filters-changed')]
    public function applyFilters(array $filters): void
    {
        $this->fill($filters);
        $this->pagesShown = 1;
        unset($this->column, $this->lanes, $this->workOrderGroups);
    }

    #[On('board-refresh.{status}')]
    public function refreshColumn(): void
    {
        unset($this->column, $this->lanes, $this->workOrderGroups);
    }

    public function showMore(): void
    {
        $this->pagesShown++;
        unset($this->column, $this->lanes, $this->workOrderGroups);
    }

    #[Computed]
    public function statusEnum(): DeliverableStatus
    {
        return DeliverableStatus::from($this->status);
    }

    #[Computed]
    public function column(): BoardColumn
    {
        $filters = new BoardFilters(
            assigneeId: $this->assigneeId,
            clientId: $this->clientId,
            flags: array_values(array_filter(array_map(fn (string $flag): ?DeliverableFlag => DeliverableFlag::tryFrom($flag), $this->flags))),
            search: $this->search !== '' ? $this->search : null,
        );

        return BoardSnapshot::column(auth()->user(), $this->statusEnum, $filters, self::PAGE_SIZE * $this->pagesShown);
    }

    /**
     * Cards by assignee when the board is grouped by designer (design columns only), otherwise one unnamed lane.
     *
     * @return list<array{assignee: ?User, cards: Collection<int, Deliverable>, wip: ?array{used: int, limit: int}}>
     */
    #[Computed]
    public function lanes(): array
    {
        $cards = $this->column->cards;

        if ($this->groupBy !== 'designer' || $this->statusEnum->area() !== Area::DESIGN || $cards->isEmpty()) {
            return [['assignee' => null, 'cards' => $cards, 'wip' => null]];
        }

        $wipPolicy = app(WipPolicy::class);
        $lanes = [];

        foreach ($cards->groupBy(fn (Deliverable $card): int => (int) $card->assignee_id) as $assigneeId => $laneCards) {
            $assignee = $laneCards->first()->assignee;
            $lanes[] = [
                'assignee' => $assignee,
                'cards' => $laneCards->values(),
                'wip' => $assignee !== null && $this->statusEnum === DeliverableStatus::IN_DESIGN
                    ? ['used' => $wipPolicy->inDesignCount($assignee), 'limit' => $wipPolicy->limitFor($assignee)]
                    : null,
            ];
        }

        usort($lanes, fn (array $left, array $right): int => match (true) {
            $left['assignee'] === null => 1,
            $right['assignee'] === null => -1,
            default => strcasecmp($left['assignee']->name, $right['assignee']->name),
        });

        return $lanes;
    }

    /**
     * Fulfillment cards grouped by work order, so a whole order can be confirmed received at once.
     *
     * @return Collection<int, Collection<int, Deliverable>>
     */
    #[Computed]
    public function workOrderGroups(): Collection
    {
        return $this->column->cards->groupBy('work_order_id');
    }

    public function render()
    {
        return view('livewire.board.column', ['designerColors' => BoardSnapshot::designerColors()]);
    }
}
