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
            $terms = array_values(array_filter(array_unique([$queryStr, $cleanQuery]), fn ($t) => mb_strlen($t) >= 1));

            $results = Order::inWorkspace()
                ->with(['designer', 'designers'])
                ->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $searchTerm = '%'.$term.'%';
                        $q->orWhere('company_name', 'like', $searchTerm)
                            ->orWhere('task_name', 'like', $searchTerm)
                            ->orWhere('trello_title', 'like', $searchTerm)
                            ->orWhere('wo_number', 'like', $searchTerm)
                            ->orWhere('responsible_person', 'like', $searchTerm)
                            ->orWhere('location_name', 'like', $searchTerm)
                            ->orWhereHas('clientLocation', function ($lq) use ($searchTerm) {
                                $lq->where('name', 'like', $searchTerm);
                            })
                            ->orWhereHas('designer', function ($dq) use ($searchTerm) {
                                $dq->where('name', 'like', $searchTerm);
                            })
                            ->orWhereHas('designers', function ($dq) use ($searchTerm) {
                                $dq->where('name', 'like', $searchTerm);
                            });
                    }
                })
                ->orderBy('updated_at', 'desc')
                ->take(8)
                ->get();

            $clientResults = Client::query()
                ->withCount(['activeOrders', 'locations', 'contacts'])
                ->with(['primaryContact', 'locations'])
                ->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $searchTerm = '%'.$term.'%';
                        $q->orWhere('name', 'like', $searchTerm)
                            ->orWhere('website', 'like', $searchTerm)
                            ->orWhere('notes', 'like', $searchTerm)
                            ->orWhere('aliases', 'like', $searchTerm)
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('name', 'like', $searchTerm)
                                    ->orWhere('email', 'like', $searchTerm)
                                    ->orWhere('phone', 'like', $searchTerm);
                            })
                            ->orWhereHas('locations', function ($lq) use ($searchTerm) {
                                $lq->where('name', 'like', $searchTerm)
                                    ->orWhere('address', 'like', $searchTerm);
                            });
                    }
                })
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
