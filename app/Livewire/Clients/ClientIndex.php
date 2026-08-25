<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Base de Datos de Clientes')]
class ClientIndex extends Component
{
    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function openClientDetail(?int $clientId = null): void
    {
        $this->dispatch('open-client-flyout', clientId: $clientId);
    }

    public function render()
    {
        $query = Client::query()
            ->withCount(['activeOrders', 'archivedOrders'])
            ->with(['locations', 'contacts']);

        if (! empty(trim($this->search))) {
            $term = mb_strtoupper(trim($this->search), 'UTF-8');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('locations', function ($lq) use ($term) {
                        $lq->where('name', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%");
                    })
                    ->orWhereHas('contacts', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        $clients = $query->orderBy('name')->get();
        $totalClientsCount = Client::count();

        return view('livewire.clients.client-index', [
            'clients' => $clients,
            'totalClientsCount' => $totalClientsCount,
        ])->layout('components.layouts.app');
    }
}
