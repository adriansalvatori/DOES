<?php

namespace App\Livewire\Orders;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Client;
use App\Services\NavigationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Command palette (⌘K): work orders by number, client or description, deliverables by name, clients and quick actions.
 */
class HeaderSearch extends Component
{
    public const WORK_ORDER_LIMIT = 6;

    public const DELIVERABLE_LIMIT = 8;

    public const CLIENT_LIMIT = 4;

    public string $search = '';

    public function selectDeliverable(int $deliverableId): void
    {
        $this->dispatch('open-deliverable', deliverableId: $deliverableId);
        $this->reset('search');
    }

    public function selectClient(int $clientId): void
    {
        $this->dispatch('open-client-flyout', clientId: $clientId);
        $this->reset('search');
    }

    public function clearSearch(): void
    {
        $this->reset('search');
    }

    public function render(): View
    {
        $workOrders = collect();
        $deliverables = collect();
        $clientResults = collect();

        $terms = $this->terms();

        if ($terms !== []) {
            $workOrders = WorkOrder::query()
                ->with(['client:id,name', 'clientLocation:id,name'])
                ->withCount('deliverables')
                ->where('in_backlog', false)
                ->where(function (Builder $query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->where(fn (Builder $inner) => $inner
                            ->where('number', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%")
                            ->orWhere('company_name', 'like', "%{$term}%")
                            ->orWhereHas('client', fn (Builder $clients) => $clients->where('name', 'like', "%{$term}%")));
                    }
                })
                ->orderByDesc('id')
                ->limit(self::WORK_ORDER_LIMIT)
                ->get();

            $deliverables = Deliverable::query()
                ->with(['workOrder:id,number,client_id,company_name', 'workOrder.client:id,name'])
                ->where(function (Builder $query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->where('task_name', 'like', "%{$term}%");
                    }
                })
                ->orderByDesc('updated_at')
                ->limit(self::DELIVERABLE_LIMIT)
                ->get();

            $clientResults = Client::query()
                ->withCount('activeOrders')
                ->where(function (Builder $query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->where('name', 'like', "%{$term}%");
                    }
                })
                ->orderBy('name')
                ->limit(self::CLIENT_LIMIT)
                ->get();
        }

        return view('livewire.orders.header-search', [
            'workOrders' => $workOrders,
            'deliverables' => $deliverables,
            'clientResults' => $clientResults,
            'destinations' => app(NavigationService::class)->destinations(auth()->user()),
        ]);
    }

    /**
     * @return list<string>
     */
    private function terms(): array
    {
        $query = trim(preg_replace('/\b(clients?|clientes?|empresas?|company|companies)\b/i', '', trim($this->search)) ?? '');
        if ($query === '') {
            $query = trim($this->search);
        }

        $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(fn (string $term): string => addcslashes($term, '%_\\'), array_slice($terms, 0, 5)));
    }
}
