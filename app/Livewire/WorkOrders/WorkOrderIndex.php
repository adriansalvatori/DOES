<?php

namespace App\Livewire\WorkOrders;

use App\Application\Delivery\Data\WorkOrderListRow;
use App\Application\Delivery\Data\WorkOrderTab;
use App\Application\Delivery\Queries\WorkOrderListQuery;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One row per work order, in tabs (Active, Production, Fulfillment, Done). Open to every signed-in user.
 */
class WorkOrderIndex extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'active';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'client')]
    public ?int $clientId = null;

    /** @var list<string> Work orders picked for a merge. */
    public array $selected = [];

    #[Computed]
    public function canMerge(): bool
    {
        return Gate::allows('merge', Deliverable::class);
    }

    /**
     * Opens the merge modal with the picked work orders.
     */
    public function mergeSelected(): void
    {
        if (! $this->canMerge || count($this->selected) < 2) {
            return;
        }

        $this->dispatch('open-merge', kind: 'work_order', ids: array_map('intval', $this->selected));
        $this->selected = [];
    }

    public function selectedTab(): WorkOrderTab
    {
        return WorkOrderTab::tryFrom($this->tab) ?? WorkOrderTab::ACTIVE;
    }

    public function setTab(string $tab): void
    {
        $this->tab = (WorkOrderTab::tryFrom($tab) ?? WorkOrderTab::ACTIVE)->value;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClientId(): void
    {
        $this->clientId = $this->clientId ?: null;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, WorkOrderListRow>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return (new WorkOrderListQuery)->run($this->selectedTab(), $this->search, $this->clientId, 25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return (new WorkOrderListQuery)->tabCounts($this->search, $this->clientId);
    }

    /**
     * Companies that have work orders, for the filter.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function clients(): array
    {
        return Client::query()
            ->whereIn('id', WorkOrder::query()->inWorkspace()->whereNotNull('client_id')->select('client_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }

    public function render()
    {
        return view('livewire.work-orders.work-order-index')
            ->layout('components.layouts.app', ['title' => __('Work orders').' - '.config('app.name')]);
    }
}
