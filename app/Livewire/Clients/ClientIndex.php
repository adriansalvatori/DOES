<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Base de Datos de Clientes')]
class ClientIndex extends Component
{
    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    #[On('client-updated')]
    public function refreshClients(): void
    {
        // Triggers re-render when client is saved or updated
    }

    public function openClientDetail(?int $clientId = null): void
    {
        $this->dispatch('open-client-flyout', clientId: $clientId);
    }

    public function render()
    {
        $query = Client::query()
            ->withCount(['activeOrders', 'archivedOrders'])
            ->with(['locations', 'contacts', 'primaryContact']);

        if (! empty(trim($this->search))) {
            $query->search($this->search);
        }

        $clients = $query->orderBy('name')->get();
        $totalClientsCount = Client::count();

        return view('livewire.clients.client-index', [
            'clients' => $clients,
            'totalClientsCount' => $totalClientsCount,
        ])->layout('components.layouts.app');
    }
}
