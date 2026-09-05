<?php

namespace App\Livewire\Orders;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $search = '';

    public function selectOrder(int $orderId): void
    {
        $this->dispatch('open-order-detail', orderId: $orderId);
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
        $results = collect();
        $clientResults = collect();

        $queryStr = trim($this->search);

        if (mb_strlen($queryStr) >= 1) {
            $cleanQuery = trim(preg_replace('/\b(clients?|clientes?|empresas?|company|companies)\b/i', '', $queryStr));
            $searchTermToUse = ! empty($cleanQuery) ? $cleanQuery : $queryStr;

            $results = Order::inWorkspace()
                ->with(['designer', 'designers'])
                ->search($searchTermToUse)
                ->orderBy('updated_at', 'desc')
                ->take(8)
                ->get();

            $clientResults = Client::query()
                ->withCount(['activeOrders', 'locations', 'contacts'])
                ->with(['primaryContact', 'locations'])
                ->search($searchTermToUse)
                ->orderBy('name', 'asc')
                ->take(5)
                ->get();
        }

        return view('livewire.orders.header-search', [
            'results' => $results,
            'clientResults' => $clientResults,
        ]);
    }
}
