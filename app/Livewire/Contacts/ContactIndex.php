<?php

namespace App\Livewire\Contacts;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Order;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Directorio de Clientes')]
class ContactIndex extends Component
{
    public string $search = '';

    public string $channelFilter = '';

    public string $languageFilter = '';

    public string $clientFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'channelFilter' => ['except' => ''],
        'languageFilter' => ['except' => ''],
        'clientFilter' => ['except' => ''],
    ];

    #[On('contact-updated')]
    public function refreshContacts(): void
    {
        // Triggers re-render when a contact is saved or updated
    }

    public function openContactDetail(?int $contactId = null): void
    {
        $this->dispatch('open-contact-flyout', contactId: $contactId);
    }

    public function deleteContact(int $contactId): void
    {
        $contact = Contact::find($contactId);
        if ($contact) {
            $name = $contact->name;
            Order::where('contact_id', $contact->id)->update([
                'contact_id' => null,
            ]);
            $contact->clients()->detach();
            $contact->delete();

            session()->flash('message', "Cliente '{$name}' eliminado del directorio correctamente.");
            $this->dispatch('contact-updated');
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->channelFilter = '';
        $this->languageFilter = '';
        $this->clientFilter = '';
    }

    public function render()
    {
        $query = Contact::query()
            ->withCount(['activeOrders', 'archivedOrders', 'backlogOrders'])
            ->with(['clients']);

        if (! empty(trim($this->search))) {
            $query->search($this->search);
        }

        if (! empty($this->channelFilter)) {
            $query->where('preferred_channel', $this->channelFilter);
        }

        if (! empty($this->languageFilter)) {
            $query->where('preferred_language', $this->languageFilter);
        }

        if (! empty($this->clientFilter)) {
            $clientId = (int) $this->clientFilter;
            $query->whereHas('clients', fn ($cq) => $cq->where('clients.id', $clientId));
        }

        $contacts = $query->orderBy('name')->get();
        $totalContactsCount = Contact::count();
        $allClients = Client::orderBy('name')->get();

        return view('livewire.contacts.contact-index', [
            'contacts' => $contacts,
            'totalContactsCount' => $totalContactsCount,
            'allClients' => $allClients,
        ])->layout('components.layouts.app');
    }
}
