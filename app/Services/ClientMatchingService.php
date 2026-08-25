<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLink;
use App\Models\ClientLocation;
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

        if (! $client) {
            if (! $createIfMissing) {
                return [
                    'client' => null,
                    'location' => null,
                ];
            }

            $client = Client::create([
                'name' => $baseClientName,
            ]);
        }

        if ($responsiblePerson && ! empty(trim($responsiblePerson))) {
            $cleanPerson = trim($responsiblePerson);
            $hasContact = $client->contacts()->where('name', $cleanPerson)->exists();
            if (! $hasContact) {
                ClientContact::create([
                    'client_id' => $client->id,
                    'name' => $cleanPerson,
                    'is_primary' => $client->contacts()->where('is_primary', true)->doesntExist(),
                ]);
            }
        }

        $location = null;
        if ($locationName) {
            $location = ClientLocation::where('client_id', $client->id)
                ->where('name', $locationName)
                ->first();

            if (! $location && $createIfMissing) {
                $location = ClientLocation::create([
                    'client_id' => $client->id,
                    'name' => $locationName,
                    'address' => 'Por definir - Locación '.$locationName,
                ]);
            }
        }

        return [
            'client' => $client,
            'location' => $location,
        ];
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
                    'address' => 'Por definir - Locación '.$extractedLocationName,
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

        // Move contacts
        ClientContact::where('client_id', $sourceClient->id)->update([
            'client_id' => $targetClient->id,
            'is_primary' => false,
        ]);

        // Move links
        ClientLink::where('client_id', $sourceClient->id)->update([
            'client_id' => $targetClient->id,
        ]);

        // Delete source client
        $sourceClient->delete();
    }
}
