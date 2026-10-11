<?php

namespace App\Livewire\Contacts;

use App\Enums\CoreStatus;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\Order;
use App\Services\ClientMatchingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class ContactFlyoutPanel extends Component
{
    public bool $isOpen = false;

    public ?int $contactId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $role_title = '';

    public string $preferred_channel = 'any';

    public string $preferred_language = 'es';

    public string $notes = '';

    public array $linkedClientIds = [];

    public string $activeTab = 'general';

    public string $activeOrdersSortField = 'wo';

    public string $activeOrdersSortDirection = 'asc';

    public array $mergeSuggestions = [];

    public ?int $selectedMergeTargetId = null;

    public string $clientSearch = '';

    public function sortByActiveOrders(string $field): void
    {
        if ($this->activeOrdersSortField === $field) {
            $this->activeOrdersSortDirection = $this->activeOrdersSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->activeOrdersSortField = $field;
            $this->activeOrdersSortDirection = 'asc';
        }
    }

    public function sortOrdersCollection($orders, string $field, string $direction)
    {
        $desc = $direction === 'desc';

        return $orders->sortBy(function ($order) use ($field) {
            return match ($field) {
                'wo' => (int) preg_replace('/\D/', '', (string) ($order->wo_number ?? 0)),
                'company' => mb_strtolower($order->company_name ?? ($order->client?->name ?? 'zzz')),
                'designer' => mb_strtolower($order->designer?->name ?? 'zzz'),
                'core_status' => mb_strtolower($order->core_status?->label() ?? 'zzz'),
                'task' => mb_strtolower($order->clean_task_name ?: ($order->task_name ?? 'zzz')),
                'substatus' => mb_strtolower($order->substatus?->label() ?? 'zzz'),
                'due_date' => $order->current_due_date ? $order->current_due_date->timestamp : 0,
                default => $order->id,
            };
        }, SORT_REGULAR, $desc)->values();
    }

    #[On('open-contact-flyout')]
    public function open(?int $contactId = null): void
    {
        $this->resetErrorBag();
        $this->contactId = $contactId;
        $this->activeTab = 'general';
        $this->selectedMergeTargetId = null;
        $this->clientSearch = '';

        if ($contactId) {
            $contact = Contact::with(['clients', 'orders'])->find($contactId);
            if ($contact) {
                $this->name = $contact->name;
                $this->phone = ClientLocation::formatPhoneNumber($contact->phone ?? '');
                $this->email = $contact->email ?? '';
                $this->role_title = $contact->role_title ?? '';
                $this->preferred_channel = $contact->preferred_channel ?? 'any';
                $this->preferred_language = $contact->preferred_language ?? 'es';
                $this->notes = $contact->notes ?? '';
                $this->linkedClientIds = $contact->clients->pluck('id')->toArray();
                $this->mergeSuggestions = app(ClientMatchingService::class)->findContactMergeSuggestions($contact);
            }
        } else {
            $this->name = '';
            $this->phone = '';
            $this->email = '';
            $this->role_title = '';
            $this->preferred_channel = 'any';
            $this->preferred_language = 'es';
            $this->notes = '';
            $this->linkedClientIds = [];
            $this->mergeSuggestions = [];
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function toggleClientLink(int $clientId): void
    {
        if (in_array($clientId, $this->linkedClientIds, true)) {
            $this->linkedClientIds = array_values(array_filter($this->linkedClientIds, fn ($id) => $id !== $clientId));
        } else {
            $this->linkedClientIds[] = $clientId;
        }
    }

    public function save(): void
    {
        $this->name = mb_strtoupper(trim($this->name), 'UTF-8');

        $this->validate([
            'name' => [
                'required',
                'string',
                'min:2',
            ],
            'email' => 'nullable|email',
        ], [
            'name.required' => 'El nombre del cliente/contacto es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
        ]);

        $phone = trim($this->phone);
        if (! empty($phone) && ! ClientLocation::isValidPhoneNumber($phone)) {
            $this->addError('phone', 'El teléfono debe tener 10 dígitos (ej. (770) 864-9359).');

            return;
        }

        $cleanPhone = ClientLocation::formatPhoneNumber($phone);
        $cleanEmail = trim($this->email);

        if ($this->contactId) {
            $contact = Contact::findOrFail($this->contactId);
            $contact->update([
                'name' => $this->name,
                'phone' => $cleanPhone ?: null,
                'email' => $cleanEmail ?: null,
                'role_title' => trim($this->role_title) ?: null,
                'preferred_channel' => $this->preferred_channel,
                'preferred_language' => $this->preferred_language,
                'notes' => trim($this->notes) ?: null,
            ]);
        } else {
            $contact = Contact::create([
                'name' => $this->name,
                'phone' => $cleanPhone ?: null,
                'email' => $cleanEmail ?: null,
                'role_title' => trim($this->role_title) ?: null,
                'preferred_channel' => $this->preferred_channel,
                'preferred_language' => $this->preferred_language,
                'notes' => trim($this->notes) ?: null,
            ]);
            $this->contactId = $contact->id;
        }

        // Sync linked clients in pivot table preserving is_primary
        $existingPivots = DB::table('client_contact')
            ->where('contact_id', $contact->id)
            ->get()
            ->keyBy('client_id');

        $syncData = [];
        foreach ($this->linkedClientIds as $cId) {
            $isPrimary = false;
            if ($existingPivots->has($cId)) {
                $isPrimary = (bool) $existingPivots[$cId]->is_primary;
            } else {
                $hasPrimary = DB::table('client_contact')
                    ->where('client_id', $cId)
                    ->where('is_primary', true)
                    ->exists();
                $isPrimary = ! $hasPrimary;
            }

            $syncData[$cId] = [
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $contact->clients()->sync($syncData);

        // Update any orders that match this contact by name to have contact_id
        Order::whereNull('contact_id')
            ->where(function ($q) use ($contact) {
                $q->where('responsible_person', $contact->name)
                    ->orWhere('responsible_person', mb_strtolower($contact->name, 'UTF-8'));
            })
            ->update(['contact_id' => $contact->id]);

        // Auto-assign unassigned orders for brands where this contact is primary
        $primaryClientIds = DB::table('client_contact')
            ->where('contact_id', $contact->id)
            ->where('is_primary', true)
            ->pluck('client_id')
            ->toArray();

        if (! empty($primaryClientIds)) {
            Order::whereIn('client_id', $primaryClientIds)
                ->where(function ($q) {
                    $q->whereNull('contact_id')
                        ->orWhere('responsible_person', 'MAIN')
                        ->orWhereNull('responsible_person');
                })
                ->update([
                    'contact_id' => $contact->id,
                    'responsible_person' => $contact->name,
                ]);
        }

        $this->dispatch('contact-updated');
        session()->flash('message', "Cliente '{$contact->name}' guardado correctamente.");
    }

    public function setAsPrimaryClient(int $clientId): void
    {
        if (! $this->contactId) {
            return;
        }

        $contact = Contact::find($this->contactId);
        if (! $contact) {
            return;
        }

        DB::table('client_contact')
            ->where('client_id', $clientId)
            ->update(['is_primary' => false]);

        DB::table('client_contact')
            ->where('client_id', $clientId)
            ->where('contact_id', $contact->id)
            ->update(['is_primary' => true]);

        Order::where('client_id', $clientId)
            ->where(function ($q) {
                $q->whereNull('contact_id')
                    ->orWhere('responsible_person', 'MAIN')
                    ->orWhereNull('responsible_person');
            })
            ->update([
                'contact_id' => $contact->id,
                'responsible_person' => $contact->name,
            ]);

        $this->dispatch('contact-updated');
        session()->flash('message', "Se estableció a {$contact->name} como MAIN CONTACT de la compañía.");
    }

    public function executeMerge(int $targetContactId): void
    {
        if (! $this->contactId || $this->contactId === $targetContactId) {
            return;
        }

        $source = Contact::find($this->contactId);
        $target = Contact::find($targetContactId);

        if ($source && $target) {
            $targetName = $target->name;
            app(ClientMatchingService::class)->mergeContacts($source, $target);

            $this->dispatch('contact-updated');
            session()->flash('message', "Contacto fusionado exitosamente en '{$targetName}'.");
            $this->open($target->id);
        }
    }

    public function render()
    {
        $currentContact = $this->contactId ? Contact::with(['clients'])->find($this->contactId) : null;

        $assignedOrders = collect();
        $archivedOrders = collect();
        $backlogOrders = collect();

        if ($currentContact) {
            $primaryClientIds = $currentContact->clients
                ->where('pivot.is_primary', true)
                ->pluck('id')
                ->toArray();

            $orderScope = function ($q) use ($currentContact, $primaryClientIds) {
                $q->where('contact_id', $currentContact->id)
                    ->orWhere('responsible_person', $currentContact->name);
                if (! empty($primaryClientIds)) {
                    $q->orWhere(function ($sub) use ($primaryClientIds) {
                        $sub->whereNull('contact_id')
                            ->whereIn('client_id', $primaryClientIds);
                    });
                }
            };

            $rawActive = Order::where($orderScope)
                ->where('in_workspace', true)
                ->where('core_status', '!=', CoreStatus::ARCHIVED)
                ->with(['client', 'designer', 'clientLocation'])
                ->get();

            $assignedOrders = $this->sortOrdersCollection($rawActive, $this->activeOrdersSortField, $this->activeOrdersSortDirection);

            $archivedOrders = Order::where($orderScope)
                ->where('core_status', CoreStatus::ARCHIVED)
                ->with(['client', 'designer', 'clientLocation'])
                ->get();

            $backlogOrders = Order::where($orderScope)
                ->where('in_workspace', false)
                ->where('core_status', '!=', CoreStatus::ARCHIVED)
                ->with(['client', 'designer', 'clientLocation'])
                ->get();
        }

        $allClientsQuery = Client::query()->orderBy('name');
        if (! empty(trim($this->clientSearch))) {
            $term = '%'.trim($this->clientSearch).'%';
            $allClientsQuery->where('name', 'like', $term);
        }
        $allClients = $allClientsQuery->take(20)->get();

        $allOtherContacts = $currentContact
            ? Contact::where('id', '!=', $currentContact->id)->orderBy('name')->take(30)->get()
            : collect();

        return view('livewire.contacts.contact-flyout-panel', [
            'currentContact' => $currentContact,
            'assignedOrders' => $assignedOrders,
            'archivedOrders' => $archivedOrders,
            'backlogOrders' => $backlogOrders,
            'allClients' => $allClients,
            'allOtherContacts' => $allOtherContacts,
        ]);
    }
}
