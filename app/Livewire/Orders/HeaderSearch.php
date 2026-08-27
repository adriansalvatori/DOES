<?php

namespace App\Livewire\Orders;

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

    public function clearSearch(): void
    {
        $this->reset('search');
    }

    public function render(): View
    {
        $results = collect();

        $queryStr = trim($this->search);

        if (mb_strlen($queryStr) >= 1) {
            $results = Order::inWorkspace()
                ->with(['designer', 'designers'])
                ->where(function ($q) use ($queryStr) {
                    $searchTerm = '%'.$queryStr.'%';
                    $q->where('company_name', 'like', $searchTerm)
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
                })
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get();
        }

        return view('livewire.orders.header-search', [
            'results' => $results,
        ]);
    }
}
