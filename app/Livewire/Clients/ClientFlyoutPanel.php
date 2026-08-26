<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLink;
use App\Models\ClientLocation;
use App\Models\Order;
use App\Services\ClientMatchingService;
use App\Services\TrelloSyncService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ClientFlyoutPanel extends Component
{
    public bool $isOpen = false;

    public ?int $clientId = null;

    public string $name = '';

    public string $website = '';

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
                $this->website = $client->website ?? '';
                $this->notes = $client->notes ?? '';
                $this->contacts = $client->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => ClientLocation::formatPhoneNumber($c->phone ?? ''),
                    'email' => $c->email ?? '',
                    'department' => $c->department ?? '',
                    'is_primary' => (bool) $c->is_primary,
                ])->toArray();

                if (empty($this->contacts)) {
                    $this->contacts = [
                        ['id' => null, 'name' => '', 'phone' => '', 'email' => '', 'department' => '', 'is_primary' => true],
                    ];
                }

                $this->links = $client->links->map(fn ($l) => [
                    'id' => $l->id,
                    'label' => $l->label,
                    'url' => $l->url,
                    'department' => $l->department ?? '',
                ])->toArray();

                $this->locations = $client->locations->map(function ($loc) {
                    $addr = trim($loc->address ?? '');
                    if ($addr === 'Por definir' || str_starts_with($addr, 'Por definir')) {
                        $addr = '';
                    }

                    return [
                        'id' => $loc->id,
                        'name' => $loc->name,
                        'address' => $addr,
                        'phone' => ClientLocation::formatPhoneNumber($loc->phone ?? $loc->manager_phone ?? ''),
                        'email' => $loc->email ?? '',
                        'manager_name' => $loc->manager_name ?? '',
                        'notes' => $loc->notes ?? '',
                    ];
                })->toArray();

                if (empty($this->locations)) {
                    $this->locations = [
                        ['id' => null, 'name' => 'SEDE PRINCIPAL', 'address' => '', 'phone' => '', 'email' => '', 'manager_name' => '', 'notes' => ''],
                    ];
                }

                $this->mergeSuggestions = app(ClientMatchingService::class)->findMergeSuggestions($client);
            }
        } else {
            $this->name = '';
            $this->website = '';
            $this->notes = '';
            $this->contacts = [
                ['id' => null, 'name' => '', 'phone' => '', 'email' => '', 'department' => '', 'is_primary' => true],
            ];
            $this->links = [
                ['id' => null, 'label' => 'Brandbook', 'url' => '', 'department' => ''],
                ['id' => null, 'label' => 'Assets / Recursos', 'url' => '', 'department' => ''],
            ];
            $this->locations = [
                ['id' => null, 'name' => 'SEDE PRINCIPAL', 'address' => '', 'phone' => '', 'email' => '', 'manager_name' => '', 'notes' => ''],
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

    public array $dismissedContactDuplicates = [];

    public function dismissDuplicateContact(int $index): void
    {
        $this->dismissedContactDuplicates[$index] = true;
    }

    public function confirmMergeContact(int $index, string $targetName): void
    {
        if (! isset($this->contacts[$index])) {
            return;
        }

        $source = $this->contacts[$index];
        $targetIndex = null;
        $cleanTarget = mb_strtolower(trim($targetName), 'UTF-8');

        foreach ($this->contacts as $i => $c) {
            if ($i === $index) {
                continue;
            }
            $cName = mb_strtolower(trim($c['name'] ?? ''), 'UTF-8');
            if (! empty($cName) && ($cName === $cleanTarget || Str::contains($cName, $cleanTarget) || Str::contains($cleanTarget, $cName))) {
                $targetIndex = $i;
                break;
            }
        }

        if ($targetIndex !== null && isset($this->contacts[$targetIndex])) {
            $target = &$this->contacts[$targetIndex];

            $sourceName = trim($source['name'] ?? '');
            $targetNameStr = trim($target['name'] ?? '');
            if (mb_strlen($sourceName, 'UTF-8') > mb_strlen($targetNameStr, 'UTF-8')) {
                $target['name'] = mb_strtoupper($sourceName, 'UTF-8');
            } else {
                $target['name'] = mb_strtoupper($targetNameStr, 'UTF-8');
            }

            if (empty(trim($target['phone'] ?? '')) && ! empty(trim($source['phone'] ?? ''))) {
                $target['phone'] = $source['phone'];
            }
            if (empty(trim($target['email'] ?? '')) && ! empty(trim($source['email'] ?? ''))) {
                $target['email'] = $source['email'];
            }
            if (empty(trim($target['department'] ?? '')) && ! empty(trim($source['department'] ?? ''))) {
                $target['department'] = $source['department'];
            }
            if (! empty($source['is_primary'])) {
                $target['is_primary'] = true;
            }

            if (! empty($source['id'])) {
                ClientContact::where('id', $source['id'])->delete();
            }

            unset($this->contacts[$index]);
            $this->contacts = array_values($this->contacts);
        } else {
            $this->contacts[$index]['name'] = mb_strtoupper($targetName, 'UTF-8');
        }

        $this->dismissedContactDuplicates = [];
    }

    public function getDuplicateWarningForContact(int $index): ?string
    {
        if ($this->dismissedContactDuplicates[$index] ?? false) {
            return null;
        }

        $name = mb_strtolower(trim($this->contacts[$index]['name'] ?? ''), 'UTF-8');
        if (strlen($name) < 3) {
            return null;
        }

        foreach ($this->contacts as $otherIndex => $other) {
            if ($otherIndex === $index) {
                continue;
            }
            $otherName = mb_strtolower(trim($other['name'] ?? ''), 'UTF-8');
            if (empty($otherName)) {
                continue;
            }

            if ($otherName === $name) {
                return trim($other['name']);
            }

            $nameFirst = explode(' ', $name)[0] ?? '';
            $otherFirst = explode(' ', $otherName)[0] ?? '';

            if ((strlen($nameFirst) >= 3 && $nameFirst === $otherFirst) || Str::contains($otherName, $name) || Str::contains($name, $otherName)) {
                return trim($other['name']);
            }
        }

        return null;
    }

    public function removeLink(int $index): void
    {
        unset($this->links[$index]);
        $this->links = array_values($this->links);
    }

    public function addContactWithName(string $name): void
    {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            return;
        }

        $cleanLower = mb_strtolower($cleanName, 'UTF-8');
        foreach ($this->contacts as $c) {
            $cName = mb_strtolower(trim($c['name'] ?? ''), 'UTF-8');
            if (! empty($cName) && ($cName === $cleanLower || Str::contains($cName, $cleanLower) || Str::contains($cleanLower, $cName))) {
                return;
            }
        }

        if (count($this->contacts) === 1 && empty(trim($this->contacts[0]['name'] ?? ''))) {
            $this->contacts[0]['name'] = $cleanName;

            return;
        }

        $this->contacts[] = [
            'id' => null,
            'name' => $cleanName,
            'phone' => '',
            'email' => '',
            'department' => '',
            'is_primary' => empty($this->contacts),
        ];
    }

    public function addLocation(): void
    {
        $this->locations[] = [
            'id' => null,
            'name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'manager_name' => '',
            'notes' => '',
        ];
    }

    public function removeLocation(int $index): void
    {
        if (isset($this->locations[$index])) {
            $locId = $this->locations[$index]['id'] ?? null;
            if ($locId) {
                Order::where('client_location_id', $locId)->update(['client_location_id' => null]);
                ClientLocation::where('id', $locId)->delete();
            }
            unset($this->locations[$index]);
            $this->locations = array_values($this->locations);
        }
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

        foreach ($this->locations as $index => $locData) {
            if (empty(trim($locData['name'] ?? ''))) {
                continue;
            }

            $phone = trim($locData['phone'] ?? '');
            if (! empty($phone) && ! ClientLocation::isValidPhoneNumber($phone)) {
                $this->addError("locations.{$index}.phone", 'El teléfono de la locación debe tener 10 dígitos (ej. (770) 864-9359).');

                return;
            }

            $email = trim($locData['email'] ?? '');
            if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError("locations.{$index}.email", 'El correo de la locación debe ser una dirección válida.');

                return;
            }
        }

        $cleanName = mb_strtoupper(trim($this->name), 'UTF-8');
        $cleanWebsite = trim($this->website);

        if ($this->clientId) {
            $client = Client::findOrFail($this->clientId);
            $client->update([
                'name' => $cleanName,
                'website' => $cleanWebsite,
                'notes' => $this->notes,
            ]);
        } else {
            $client = Client::create([
                'name' => $cleanName,
                'website' => $cleanWebsite,
                'notes' => $this->notes,
            ]);
            $this->clientId = $client->id;
        }

        // Auto-ensure manager_names from locations exist in contacts array
        foreach ($this->locations as $locData) {
            $mName = trim($locData['manager_name'] ?? '');
            if (! empty($mName)) {
                $this->addContactWithName($mName);
            }
        }

        // Sync contacts
        $existingContactIds = [];
        foreach ($this->contacts as $cData) {
            if (empty(trim($cData['name'] ?? ''))) {
                continue;
            }
            $cPhone = ClientLocation::formatPhoneNumber($cData['phone'] ?? '');
            if (! empty($cData['id'])) {
                $contact = ClientContact::find($cData['id']);
                if ($contact) {
                    $contact->update([
                        'name' => trim($cData['name']),
                        'phone' => $cPhone,
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
                    'phone' => $cPhone,
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
            $locAddress = trim($locData['address'] ?? '');
            if ($locAddress === 'Por definir' || str_starts_with($locAddress, 'Por definir')) {
                $locAddress = '';
            }
            $locPhone = ClientLocation::formatPhoneNumber($locData['phone'] ?? '');
            $locEmail = mb_strtolower(trim($locData['email'] ?? ''), 'UTF-8');
            $locManager = trim($locData['manager_name'] ?? '');

            if (! empty($locData['id'])) {
                $location = ClientLocation::find($locData['id']);
                if ($location) {
                    $location->update([
                        'name' => $locName,
                        'address' => $locAddress,
                        'phone' => $locPhone,
                        'email' => $locEmail,
                        'manager_name' => $locManager,
                        'notes' => trim($locData['notes'] ?? ''),
                    ]);
                    $existingLocIds[] = $location->id;
                }
            } else {
                $newLoc = ClientLocation::create([
                    'client_id' => $client->id,
                    'name' => $locName,
                    'address' => $locAddress,
                    'phone' => $locPhone,
                    'email' => $locEmail,
                    'manager_name' => $locManager,
                    'notes' => trim($locData['notes'] ?? ''),
                ]);
                $existingLocIds[] = $newLoc->id;
            }
        }
        // Nullify order location IDs for deleted locations
        $deletedLocIds = ClientLocation::where('client_id', $client->id)->whereNotIn('id', $existingLocIds)->pluck('id');
        if ($deletedLocIds->isNotEmpty()) {
            Order::whereIn('client_location_id', $deletedLocIds)->update(['client_location_id' => null]);
            ClientLocation::whereIn('id', $deletedLocIds)->delete();
        }

        // Push updated titles to Trello for active workspace orders
        $activeOrders = $client->orders()->where('in_workspace', true)->get();
        foreach ($activeOrders as $activeOrder) {
            $activeOrder->update(['company_name' => $cleanName]);
            if ($activeOrder->trello_card_id) {
                app(TrelloSyncService::class)->updateCardOnTrello($activeOrder);
            }
        }

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
