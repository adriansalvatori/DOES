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
use Livewire\WithPagination;

/**
 * Gabriela's Excel-style screen: one line per work order (a child component each, so an edit re-renders only its
 * line) with an accordion of its deliverables. Admin and coordinator only.
 */
class OverviewPage extends Component
{
    use WithPagination;

    public const PER_PAGE = 50;

    public const TABS = ['all', 'active', 'production', 'done', 'backlog', 'urgent', 'with_due_date'];

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

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && in_array($user->role, [UserRole::ADMIN, UserRole::COORDINATOR], true), 403);
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'all';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'workOrderNumber', 'clientId', 'assigneeId', 'installation');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, OverviewRow>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return app(OverviewQuery::class)->run($this->filters(), self::PER_PAGE);
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
        );
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
        return view('livewire.overview-page.overview-page')
            ->layout('components.layouts.app', ['title' => __('Overview').' - '.config('app.name')]);
    }
}
