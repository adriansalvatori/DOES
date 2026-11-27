<?php

namespace App\Livewire\Overview;

use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Data\OverviewRow;
use App\Application\Delivery\Queries\OverviewQuery;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\InstallationType;
use App\Models\User;
use App\Support\TitleCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Gabriela's Excel-style screen: one line per work order (a child component each, so an edit re-renders only its
 * line) with an accordion of its deliverables. Loads 100 lines at a time as you scroll and checks every 30 seconds
 * which lines someone else changed. Admin and coordinator only.
 */
class OverviewPage extends Component
{
    public const CHUNK = 100;

    public const TABS = OverviewQuery::TABS;

    /**
     * The columns frozen on the left from 1024px up: [left offset, width] in pixels.
     */
    public const FROZEN = [
        'toggle' => [0, 28],
        'processed' => [28, 100],
        'due' => [128, 108],
        'number' => [236, 88],
        'client' => [324, 164],
        'name' => [488, 188],
    ];

    /**
     * Inline position and width of a frozen column, shared by the header and every line.
     */
    public static function frozen(string $column): string
    {
        [$left, $width] = self::FROZEN[$column];

        return "left: {$left}px; width: {$width}px; min-width: {$width}px; max-width: {$width}px;";
    }

    #[Url(as: 'tab')]
    public string $tab = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'wo')]
    public string $workOrderNumber = '';

    #[Url(as: 'client')]
    public ?int $clientId = null;

    #[Url(as: 'designer')]
    public ?int $assigneeId = null;

    #[Url(as: 'installation')]
    public string $installation = '';

    #[Url(as: 'review')]
    public string $review = '';

    #[Url(as: 'urgent')]
    public bool $urgent = false;

    #[Url(as: 'due')]
    public bool $withDueDate = false;

    #[Url(as: 'expand')]
    public bool $expandAll = false;

    public int $limit = self::CHUNK;

    /**
     * The fingerprint of each line on screen when it was last rendered.
     *
     * @var array<int, string>
     */
    public array $versions = [];

    public int $total = 0;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->hasRole(UserRole::ADMIN, UserRole::COORDINATOR), 403);

        // Old links used the toggles as tabs.
        if ($this->tab === 'urgent') {
            [$this->tab, $this->urgent] = ['all', true];
        } elseif ($this->tab === 'with_due_date') {
            [$this->tab, $this->withDueDate] = ['all', true];
        }
    }

    public function updating(string $property): void
    {
        if (! in_array($property, ['limit', 'versions', 'total', 'expandAll'], true)) {
            $this->limit = self::CHUNK;
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'all';
        $this->limit = self::CHUNK;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'workOrderNumber', 'clientId', 'assigneeId', 'installation', 'review', 'urgent', 'withDueDate');
        $this->limit = self::CHUNK;
    }

    public function clearFilter(string $filter): void
    {
        if (in_array($filter, ['search', 'workOrderNumber', 'clientId', 'assigneeId', 'installation', 'review', 'urgent', 'withDueDate'], true)) {
            $this->reset($filter);
            $this->limit = self::CHUNK;
        }
    }

    public function loadMore(): void
    {
        if ($this->limit < $this->rows->total()) {
            $this->limit += self::CHUNK;
        }
    }

    /**
     * Called every 30 seconds while the tab is visible and nobody is typing in a cell. Lines that someone else
     * changed re-render on their own; the page itself only re-renders when lines came or went.
     */
    public function checkForChanges(): void
    {
        $current = app(OverviewQuery::class)->versions(array_keys($this->versions));
        $total = app(OverviewQuery::class)->run($this->filters(), 1)->total();

        foreach ($current as $workOrderId => $version) {
            if (($this->versions[$workOrderId] ?? null) !== $version) {
                $this->versions[$workOrderId] = $version;
                $this->dispatch("overview-line-stale.{$workOrderId}");
            }
        }

        if ($total === $this->total && count($current) === count($this->versions)) {
            $this->skipRender();
        }
    }

    /**
     * @return LengthAwarePaginator<int, OverviewRow>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return app(OverviewQuery::class)->run($this->filters(), $this->limit);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return app(OverviewQuery::class)->tabCounts($this->filters());
    }

    public function filters(): OverviewFilters
    {
        return new OverviewFilters(
            tab: in_array($this->tab, self::TABS, true) ? $this->tab : 'all',
            search: filled($this->search) ? $this->search : null,
            workOrderNumber: filled($this->workOrderNumber) ? $this->workOrderNumber : null,
            clientId: $this->clientId,
            assigneeId: $this->assigneeId,
            installation: filled($this->installation) ? $this->installation : null,
            review: filled($this->review) ? $this->review : null,
            urgent: $this->urgent,
            withDueDate: $this->withDueDate,
        );
    }

    /**
     * The removable chips for the filters in use.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function activeFilters(): array
    {
        return array_filter([
            'search' => filled($this->search) ? '“'.$this->search.'”' : null,
            'workOrderNumber' => filled($this->workOrderNumber) ? __('WO').' '.$this->workOrderNumber : null,
            'clientId' => $this->clientId !== null ? ($this->clientOptions[$this->clientId] ?? null) : null,
            'assigneeId' => $this->assigneeId !== null ? ($this->designerOptions[$this->assigneeId] ?? null) : null,
            'installation' => match (true) {
                $this->installation === 'NONE' => __('Installation').': '.__('No info'),
                filled($this->installation) => __('Installation').': '.$this->installation,
                default => null,
            },
            'review' => match ($this->review) {
                'NONE' => __('Not reviewed'),
                'CS' => __('Reviewed by :name', ['name' => 'CS']),
                'CAMILA' => __('Reviewed by :name', ['name' => 'Camila']),
                default => null,
            },
            'urgent' => $this->urgent ? __('Urgent') : null,
            'withDueDate' => $this->withDueDate ? __('With due date') : null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function clientOptions(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->map(fn (string $name): string => TitleCase::from($name))->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function designerOptions(): array
    {
        return User::query()->designTeam()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function installationTypes(): array
    {
        return InstallationType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
    }

    public function render()
    {
        $rows = $this->rows;
        $this->total = $rows->total();
        $this->versions = app(OverviewQuery::class)->versions(array_map(fn (OverviewRow $row): int => $row->workOrder->getKey(), $rows->items()));

        return view('livewire.overview-page.overview-page')
            ->layout('components.layouts.app', ['title' => __('Overview').' - '.config('app.name')]);
    }
}
