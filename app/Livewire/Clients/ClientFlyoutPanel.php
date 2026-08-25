<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLink;
use App\Models\ClientLocation;
use App\Services\ClientMatchingService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ClientFlyoutPanel extends Component
{
    public bool $isOpen = false;

    public ?int $clientId = null;

    public string $name = '';

    public string $notes = '';

    public array $contacts = [];

    public array $links = [];

    public array $locations = [];

    public array $mergeSuggestions = [];

    public string $activeTab = 'general';

    #[On('open-client-flyout')]
    public function open(?int $clientId = null): void
    {
        $this->resetErrorBag();
        $this->clientId = $clientId;
        $this->activeTab = 'general';

        if ($clientId) {
            $client = Client::with(['locations', 'contacts', 'links', 'orders'])->find($clientId);
            if ($client) {
                $this->name = $client->name;
                $this->notes = $client->notes ?? '';
                $this->contacts = $client->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone ?? '',
                    'email' => $c->email ?? '',
                    'department' => $c->department ?? '',
                    'is_primary' => (bool) $c->is_primary,
                ])->toArray();

                $this->links = $client->links->map(fn ($l) => [
                    'id' => $l->id,
                    'label' => $l->label,
                    'url' => $l->url,
                    'department' => $l->department ?? '',
                ])->toArray();

                $this->locations = $client->locations->map(fn ($loc) => [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->address,
                    'manager_name' => $loc->manager_name ?? '',
                    'manager_phone' => $loc->manager_phone ?? '',
                    'notes' => $loc->notes ?? '',
                ])->toArray();

                $this->mergeSuggestions = app(ClientMatchingService::class)->findMergeSuggestions($client);
            }
        } else {
            $this->name = '';
            $this->notes = '';
            $this->contacts = [
                ['id' => null, 'name' => '', 'phone' => '', 'email' => '', 'department' => '', 'is_primary' => true],
            ];
            $this->links = [
                ['id' => null, 'label' => 'Brandbook', 'url' => '', 'department' => 'Diseño'],
                ['id' => null, 'label' => 'Assets / Recursos', 'url' => '', 'department' => 'Diseño'],
            ];
            $this->locations = [
                ['id' => null, 'name' => 'SEDE PRINCIPAL', 'address' => '', 'manager_name' => '', 'manager_phone' => '', 'notes' => ''],
            ];
            $this->mergeSuggestions = [];
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function addContact(): void
    {
        $this->contacts[] = [
            'id' => null,
            'name' => '',
            'phone' => '',
            'email' => '',
            'department' => '',
            'is_primary' => empty($this->contacts),
        ];
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function addLink(): void
    {
        $this->links[] = [
            'id' => null,
            'label' => '',
            'url' => '',
            'department' => '',
        ];
    }

    public function removeLink(int $index): void
    {
        unset($this->links[$index]);
        $this->links = array_values($this->links);
    }

    public function addLocation(): void
    {
        $this->locations[] = [
            'id' => null,
            'name' => '',
            'address' => '',
            'manager_name' => '',
            'manager_phone' => '',
            'notes' => '',
        ];
    }

    public function removeLocation(int $index): void
    {
        unset($this->locations[$index]);
        $this->locations = array_values($this->locations);
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                Rule::unique('clients', 'name')
                    ->ignore($this->clientId)
                    ->whereNull('deleted_at'),
            ],
            'locations.*.address' => 'nullable|string',
        ], [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.unique' => 'Ya existe un cliente registrado con este nombre.',
        ]);

        $cleanName = mb_strtoupper(trim($this->name), 'UTF-8');

        if ($this->clientId) {
            $client = Client::findOrFail($this->clientId);
            $client->update([
                'name' => $cleanName,
                'notes' => $this->notes,
            ]);
        } else {
            $client = Client::create([
                'name' => $cleanName,
                'notes' => $this->notes,
            ]);
            $this->clientId = $client->id;
        }

        // Sync contacts
        $existingContactIds = [];
        foreach ($this->contacts as $cData) {
            if (empty(trim($cData['name'] ?? ''))) {
                continue;
            }
            if (! empty($cData['id'])) {
                $contact = ClientContact::find($cData['id']);
                if ($contact) {
                    $contact->update([
                        'name' => trim($cData['name']),
                        'phone' => trim($cData['phone'] ?? ''),
                        'email' => trim($cData['email'] ?? ''),
                        'department' => trim($cData['department'] ?? ''),
                        'is_primary' => (bool) ($cData['is_primary'] ?? false),
                    ]);
                    $existingContactIds[] = $contact->id;
                }
            } else {
                $newContact = ClientContact::create([
                    'client_id' => $client->id,
                    'name' => trim($cData['name']),
                    'phone' => trim($cData['phone'] ?? ''),
                    'email' => trim($cData['email'] ?? ''),
                    'department' => trim($cData['department'] ?? ''),
                    'is_primary' => (bool) ($cData['is_primary'] ?? false),
                ]);
                $existingContactIds[] = $newContact->id;
            }
        }
        ClientContact::where('client_id', $client->id)->whereNotIn('id', $existingContactIds)->delete();

        // Sync links
        $existingLinkIds = [];
        foreach ($this->links as $lData) {
            if (empty(trim($lData['label'] ?? '')) || empty(trim($lData['url'] ?? ''))) {
                continue;
            }
            if (! empty($lData['id'])) {
                $link = ClientLink::find($lData['id']);
                if ($link) {
                    $link->update([
                        'label' => trim($lData['label']),
                        'url' => trim($lData['url']),
                        'department' => trim($lData['department'] ?? ''),
                    ]);
                    $existingLinkIds[] = $link->id;
                }
            } else {
                $newLink = ClientLink::create([
                    'client_id' => $client->id,
                    'label' => trim($lData['label']),
                    'url' => trim($lData['url']),
                    'department' => trim($lData['department'] ?? ''),
                ]);
                $existingLinkIds[] = $newLink->id;
            }
        }
        ClientLink::where('client_id', $client->id)->whereNotIn('id', $existingLinkIds)->delete();

        // Sync locations
        $existingLocIds = [];
        foreach ($this->locations as $locData) {
            if (empty(trim($locData['name'] ?? ''))) {
                continue;
            }
            $locName = mb_strtoupper(trim($locData['name']), 'UTF-8');
            $locAddress = trim($locData['address'] ?? '') ?: 'Por definir';

            if (! empty($locData['id'])) {
                $location = ClientLocation::find($locData['id']);
                if ($location) {
                    $location->update([
                        'name' => $locName,
                        'address' => $locAddress,
                        'manager_name' => trim($locData['manager_name'] ?? ''),
                        'manager_phone' => trim($locData['manager_phone'] ?? ''),
                        'notes' => trim($locData['notes'] ?? ''),
                    ]);
                    $existingLocIds[] = $location->id;
                }
            } else {
                $newLoc = ClientLocation::create([
                    'client_id' => $client->id,
                    'name' => $locName,
                    'address' => $locAddress,
                    'manager_name' => trim($locData['manager_name'] ?? ''),
                    'manager_phone' => trim($locData['manager_phone'] ?? ''),
                    'notes' => trim($locData['notes'] ?? ''),
                ]);
                $existingLocIds[] = $newLoc->id;
            }
        }
        ClientLocation::where('client_id', $client->id)->whereNotIn('id', $existingLocIds)->delete();

        session()->flash('message', "Cliente '{$client->name}' guardado correctamente.");
        $this->dispatch('client-updated');
        $this->close();
    }

    public function mergeClient(int $sourceClientId): void
    {
        if (! $this->clientId) {
            return;
        }

        $targetClient = Client::find($this->clientId);
        $sourceClient = Client::find($sourceClientId);

        if ($targetClient && $sourceClient) {
            app(ClientMatchingService::class)->mergeClients($targetClient, $sourceClient);
            session()->flash('message', "Cliente '{$sourceClient->name}' fusionado con '{$targetClient->name}' correctamente.");
            $this->open($targetClient->id);
            $this->dispatch('client-updated');
        }
    }

    public function dismissMerge(int $sourceClientId): void
    {
        $this->mergeSuggestions = array_filter(
            $this->mergeSuggestions,
            fn ($s) => $s['id'] !== $sourceClientId
        );
    }

    public function render()
    {
        $currentClient = $this->clientId ? Client::with(['activeOrders', 'archivedOrders', 'locations'])->find($this->clientId) : null;

        return view('livewire.clients.client-flyout-panel', [
            'currentClient' => $currentClient,
        ]);
    }
}
