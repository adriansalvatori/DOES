<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientLink;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Support\Str;

class ClientMatchingService
{
    /**
     * Clean raw string to extract clean company name and location name in UPPERCASE.
     */
    public function cleanCompanyName(string $rawCompany): array
    {
        $clean = trim($rawCompany);

        // Remove designer/status prefixes like "(ALTA)", "(CAMILA)", "(EURALIZ)"
        $clean = preg_replace('/^\([^\)]+\)\s*/i', '', $clean);

        // Remove leading WO prefixes or slashes like "/ 15039 -", "WO 12345 -", "/ 15039"
        $clean = preg_replace('/^(?:\/\s*\d+|\bWO\b\s*\d+)\s*[\-\:]?\s*/i', '', $clean);

        $cleanUpper = mb_strtoupper(trim($clean), 'UTF-8');

        if (empty($cleanUpper)) {
            $cleanUpper = 'CLIENTE SIN NOMBRE';
        }

        $baseClientName = $cleanUpper;
        $locationName = null;

        // Check location regex patterns (e.g. "FUERZA LATINA REF EL SOL", "FUERZA LATINA - SEDE CENTRO")
        if (preg_match('/^(.*?)\s+(?:REF\.?|SEDE|SUCURSAL|LOCAL)\s+(.+)$/i', $cleanUpper, $matches)) {
            $baseClientName = mb_strtoupper(trim($matches[1]), 'UTF-8');
            $locationName = mb_strtoupper(trim($matches[2]), 'UTF-8');
        } elseif (preg_match('/^(.*?)\s*[\-\/]\s*(.+)$/i', $cleanUpper, $matches)) {
            $baseClientName = mb_strtoupper(trim($matches[1]), 'UTF-8');
            $locationName = mb_strtoupper(trim($matches[2]), 'UTF-8');
        }

        if (empty($baseClientName)) {
            $baseClientName = $cleanUpper;
            $locationName = null;
        }

        return [
            'client_name' => $baseClientName,
            'location_name' => $locationName,
        ];
    }

    /**
     * Parse raw company string, find or create Client and ClientLocation from Workspace Order.
     *
     * @return array{client: ?Client, location: ?ClientLocation}
     */
    public function matchOrCreate(string $rawCompany, ?string $responsiblePerson = null, bool $createIfMissing = true): array
    {
        $parsed = $this->cleanCompanyName($rawCompany);
        $baseClientName = $parsed['client_name'];
        $locationName = $parsed['location_name'];

        $client = Client::where('name', $baseClientName)->first();

        // Backlog cards (createIfMissing = false) must NEVER create or mutate Client, Location, or Contact records.
        if (! $createIfMissing) {
            $location = null;
            if ($client && $locationName) {
                $location = ClientLocation::where('client_id', $client->id)
                    ->where('name', $locationName)
                    ->first();
            }

            return [
                'client' => $client,
                'location' => $location,
            ];
        }

        if (! $client) {
            $client = Client::create([
                'name' => $baseClientName,
            ]);
        }

        if ($responsiblePerson && ! empty(trim($responsiblePerson))) {
            $cleanPerson = trim($responsiblePerson);
            if ($this->isValidContactName($cleanPerson, $client)) {
                $cleanPersonUpper = mb_strtoupper($cleanPerson, 'UTF-8');
                $contact = Contact::where('name', $cleanPersonUpper)->first();
                if (! $contact) {
                    $contact = Contact::create([
                        'name' => $cleanPersonUpper,
                        'preferred_channel' => 'any',
                        'preferred_language' => 'es',
                    ]);
                }

                if (! $client->contacts()->where('contacts.id', $contact->id)->exists()) {
                    $isFirst = $client->contacts()->wherePivot('is_primary', true)->doesntExist();
                    $client->contacts()->attach($contact->id, [
                        'is_primary' => $isFirst,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $location = null;
        if ($locationName) {
            $location = ClientLocation::where('client_id', $client->id)
                ->where('name', $locationName)
                ->first();

            if (! $location) {
                $location = ClientLocation::create([
                    'client_id' => $client->id,
                    'name' => $locationName,
                    'address' => '',
                ]);
            }
        }

        return [
            'client' => $client,
            'location' => $location,
        ];
    }

    /**
     * Determine if a string extracted from parenthetical title card notes is a valid person contact name.
     */
    public function isValidContactName(string $name, ?Client $client = null): bool
    {
        $clean = trim($name);
        $cleanUpper = mb_strtoupper($clean, 'UTF-8');

        // Must be at least 3 characters
        if (mb_strlen($cleanUpper, 'UTF-8') < 3) {
            return false;
        }

        // Numeric or pure digits / single numbers
        if (is_numeric($cleanUpper) || preg_match('/^\d+$/', $cleanUpper)) {
            return false;
        }

        // Known designer, status, workflow, product, or location keywords from backlog cards
        $invalidKeywords = [
            'ALTA', 'CAMILA', 'KIKE', 'CLIENT NAME', 'MAIN',
            'MARCA PROPIA', 'CLIENT', 'ENTREGADO', 'UPDATE', 'PERMIT', '4OVER', 'PICASSO',
            'BUSINESS CARDS', 'BOLSO', 'PASAPORTE', 'PORTA', 'LOCACION', 'LOGO', 'DIPSA',
            'MARIETTA', 'CONFIANZA', 'SAVANNAH', 'DORAVILLE', 'BEAVER', 'MI TIERRA',
            'AUTO', 'COMERCIAL', 'BAKERY', 'TAQUERIA', 'RESTAURANT', 'SUPERMERCADOS',
        ];

        $designerKeywords = Designer::where('active', true)->pluck('name')->map(fn ($n) => mb_strtoupper($n))->toArray();
        $invalidKeywords = array_merge($invalidKeywords, $designerKeywords);

        foreach ($invalidKeywords as $kw) {
            if ($cleanUpper === $kw || str_contains($cleanUpper, $kw)) {
                return false;
            }
        }

        // If client is provided, check if the contact name is actually a location name of that client
        if ($client) {
            $isLocationName = $client->locations()->get()->contains(function ($loc) use ($cleanUpper) {
                $locName = mb_strtoupper(trim($loc->name ?? ''), 'UTF-8');

                return ! empty($locName) && ($locName === $cleanUpper || str_contains($cleanUpper, $locName) || str_contains($locName, $cleanUpper));
            });

            if ($isLocationName) {
                return false;
            }
        }

        return true;
    }

    /**
     * Purge bogus contacts across all clients in the database.
     */
    public function purgeBogusContacts(): int
    {
        $purged = 0;
        $contacts = Contact::with('clients')->get();

        foreach ($contacts as $contact) {
            $sampleClient = $contact->clients->first();
            if (! $this->isValidContactName($contact->name, $sampleClient)) {
                $contact->delete();
                $purged++;
            }
        }

        return $purged;
    }

    /**
     * Find potential merge candidates for a given client.
     */
    public function findMergeSuggestions(Client $client): array
    {
        $allOtherClients = Client::where('id', '!=', $client->id)->get();
        $suggestions = [];

        $targetName = $client->name;

        foreach ($allOtherClients as $other) {
            $otherName = $other->name;

            if (Str::contains($otherName, $targetName) || Str::contains($targetName, $otherName) || levenshtein($targetName, $otherName) <= 3) {
                $suggestions[] = [
                    'id' => $other->id,
                    'name' => $other->name,
                    'orders_count' => $other->orders()->count(),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Merge source client into target client.
     */
    public function mergeClients(Client $targetClient, Client $sourceClient): void
    {
        if ($targetClient->id === $sourceClient->id) {
            return;
        }

        // Check if source client name contains target client name + location (e.g. FUERZA LATINA TALPA 8 vs FUERZA LATINA)
        $extractedLocationName = null;
        $sourceName = $sourceClient->name;
        $targetName = $targetClient->name;

        if (Str::startsWith($sourceName, $targetName) && strlen($sourceName) > strlen($targetName)) {
            $suffix = trim(substr($sourceName, strlen($targetName)), " \t\n\r\0\x0B-:");
            // Strip keywords like REF, SEDE, SUCURSAL
            $suffixClean = trim(preg_replace('/^(?:REF\.?|SEDE|SUCURSAL|LOCAL)\s+/i', '', $suffix));
            if (! empty($suffixClean)) {
                $extractedLocationName = mb_strtoupper($suffixClean, 'UTF-8');
            }
        }

        $targetLocationId = null;
        if ($extractedLocationName) {
            $location = ClientLocation::where('client_id', $targetClient->id)
                ->where('name', $extractedLocationName)
                ->first();

            if (! $location) {
                $location = ClientLocation::create([
                    'client_id' => $targetClient->id,
                    'name' => $extractedLocationName,
                    'address' => '',
                ]);
            }
            $targetLocationId = $location->id;
        }

        // Move orders and update company_name & location_name
        $sourceOrders = Order::where('client_id', $sourceClient->id)->get();
        foreach ($sourceOrders as $order) {
            $updateData = [
                'client_id' => $targetClient->id,
                'company_name' => $targetClient->name,
            ];

            if ($extractedLocationName) {
                $updateData['location_name'] = $extractedLocationName;
                $updateData['client_location_id'] = $targetLocationId;
            }

            $order->update($updateData);

            if ($order->in_workspace && $order->trello_card_id) {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            }
        }

        // Move locations
        ClientLocation::where('client_id', $sourceClient->id)->update([
            'client_id' => $targetClient->id,
        ]);

        // Move or attach contacts
        $sourceContacts = $sourceClient->contacts()->get();
        foreach ($sourceContacts as $sContact) {
            if (! $targetClient->contacts()->where('contacts.id', $sContact->id)->exists()) {
                $targetClient->contacts()->attach($sContact->id, [
                    'is_primary' => (bool) ($sContact->pivot->is_primary ?? false),
                    'role' => $sContact->pivot->role ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $sourceClient->contacts()->detach();

        // Move links
        ClientLink::where('client_id', $sourceClient->id)->update([
            'client_id' => $targetClient->id,
        ]);

        // Delete source client
        $sourceClient->delete();
    }

    /**
     * Find potential merge candidates for a given contact.
     */
    public function findContactMergeSuggestions(Contact $contact): array
    {
        $allOtherContacts = Contact::where('id', '!=', $contact->id)->get();
        $suggestions = [];

        $targetName = mb_strtolower(trim($contact->name), 'UTF-8');

        foreach ($allOtherContacts as $other) {
            $otherName = mb_strtolower(trim($other->name), 'UTF-8');

            if (Str::contains($otherName, $targetName) || Str::contains($targetName, $otherName) || levenshtein($targetName, $otherName) <= 2) {
                $suggestions[] = [
                    'contact' => $other,
                    'reason' => 'Nombre similar',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Merge sourceContact into targetContact:
     * - Reassign all orders to targetContact
     * - Link all sourceContact's clients to targetContact
     * - Combine phone/email/channel/notes
     * - Delete sourceContact
     */
    public function mergeContacts(Contact $sourceContact, Contact $targetContact): void
    {
        // Reassign orders
        Order::where('contact_id', $sourceContact->id)->update([
            'contact_id' => $targetContact->id,
            'responsible_person' => $targetContact->name,
        ]);

        // Reassign orders that matched sourceContact by text
        Order::where('responsible_person', $sourceContact->name)->update([
            'contact_id' => $targetContact->id,
            'responsible_person' => $targetContact->name,
        ]);

        // Move clients
        $sourceClients = $sourceContact->clients()->get();
        foreach ($sourceClients as $cl) {
            if (! $targetContact->clients()->where('clients.id', $cl->id)->exists()) {
                $targetContact->clients()->attach($cl->id, [
                    'is_primary' => (bool) ($cl->pivot->is_primary ?? false),
                    'role' => $cl->pivot->role ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $sourceContact->clients()->detach();

        // Fill missing fields on target
        $targetUpdates = [];
        if (empty($targetContact->phone) && ! empty($sourceContact->phone)) {
            $targetUpdates['phone'] = $sourceContact->phone;
        }
        if (empty($targetContact->email) && ! empty($sourceContact->email)) {
            $targetUpdates['email'] = $sourceContact->email;
        }
        if (empty($targetContact->role_title) && ! empty($sourceContact->role_title)) {
            $targetUpdates['role_title'] = $sourceContact->role_title;
        }
        if (empty($targetContact->notes) && ! empty($sourceContact->notes)) {
            $targetUpdates['notes'] = $sourceContact->notes;
        } elseif (! empty($sourceContact->notes) && ! Str::contains($targetContact->notes ?? '', $sourceContact->notes)) {
            $targetUpdates['notes'] = trim(($targetContact->notes ?? '')."\n\n[Fusionado]: ".$sourceContact->notes);
        }
        if (($targetContact->preferred_channel === 'any' || empty($targetContact->preferred_channel)) && $sourceContact->preferred_channel !== 'any') {
            $targetUpdates['preferred_channel'] = $sourceContact->preferred_channel;
        }
        if ($targetContact->preferred_language === 'es' && $sourceContact->preferred_language !== 'es') {
            $targetUpdates['preferred_language'] = $sourceContact->preferred_language;
        }

        if (! empty($targetUpdates)) {
            $targetContact->update($targetUpdates);
        }

        $sourceContact->delete();
    }
}
