<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrelloSyncService
{
    protected string $baseUrl = 'https://api.trello.com/1';

    public function extractBoardId(string $input): string
    {
        $input = trim($input);
        if (preg_match('/trello\.com\/b\/([a-zA-Z0-9]+)/', $input, $matches)) {
            return $matches[1];
        }
        return $input;
    }

    /**
     * Map Trello List name to internal CoreStatus enum.
     */
    public function mapListToCoreStatus(string $listName): CoreStatus
    {
        $normalized = strtoupper(trim($listName));

        return match (true) {
            str_contains($normalized, 'ENTRANTE') => CoreStatus::ENTRANTE,
            str_contains($normalized, 'EURALIZ') => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            str_contains($normalized, 'ADRIAN') || str_contains($normalized, 'ADRIÁN') => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            str_contains($normalized, 'CESAR') || str_contains($normalized, 'CÉSAR') => CoreStatus::CESAR_ORDERS_RECEIVED,
            str_contains($normalized, 'TODAY') || str_contains($normalized, 'HOY') => CoreStatus::TO_DO_TODAY,
            str_contains($normalized, 'CAMILA') => CoreStatus::ENVIADO_A_CAMILA,
            str_contains($normalized, 'CLIENTE') => CoreStatus::ENVIADO_AL_CLIENTE,
            str_contains($normalized, 'HOLD') || str_contains($normalized, 'PAUSA') => CoreStatus::ON_HOLD,
            str_contains($normalized, 'PRODUCCI') => CoreStatus::EN_PRODUCCION,
            default => CoreStatus::ENTRANTE,
        };
    }

    /**
     * Fetch lists from a Trello Board.
     */
    public function getBoardLists(string $boardId, string $apiKey, ?string $apiToken = null): array
    {
        $boardId = $this->extractBoardId($boardId);
        $params = ['key' => $apiKey];
        if ($apiToken) {
            $params['token'] = $apiToken;
        }

        try {
            $response = Http::get("{$this->baseUrl}/boards/{$boardId}/lists", $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'status' => $response->status(), 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error("Trello API error fetching board lists: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch cards from a Trello Board.
     */
    public function getBoardCards(string $boardId, string $apiKey, ?string $apiToken = null): array
    {
        $boardId = $this->extractBoardId($boardId);
        $params = [
            'key' => $apiKey,
            'members' => 'true',
            'member_fields' => 'fullName,username,avatarUrl',
        ];
        if ($apiToken) {
            $params['token'] = $apiToken;
        }

        try {
            $response = Http::get("{$this->baseUrl}/boards/{$boardId}/cards", $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'status' => $response->status(), 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error("Trello API error fetching board cards: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a single Trello card data array into an Order model.
     */
    public function syncCardToOrder(array $cardData, array $listsMap = []): ?Order
    {
        $listId = $cardData['idList'] ?? '';
        $listName = $listsMap[$listId] ?? 'ENTRANTE';
        $coreStatus = $this->mapListToCoreStatus($listName);

        // Strictly resolve designer from Trello card members array
        $designerId = null;
        if (!empty($cardData['members'])) {
            foreach ($cardData['members'] as $member) {
                $mId = $member['id'] ?? null;
                $fullName = trim($member['fullName'] ?? '');
                $username = trim($member['username'] ?? '');

                if ($mId || $fullName || $username) {
                    $designer = Designer::where(function($q) use ($mId, $fullName, $username) {
                        if ($mId) $q->where('trello_member_id', $mId);
                        if ($fullName) $q->orWhere('name', 'like', "%{$fullName}%");
                        if ($username) $q->orWhere('name', 'like', "%{$username}%");
                    })->first();

                    if (!$designer && !empty($fullName)) {
                        $designer = Designer::create([
                            'name' => ucwords(strtolower($fullName)),
                            'trello_member_id' => $mId,
                            'active' => true,
                        ]);
                    }

                    if ($designer) {
                        $designerId = $designer->id;
                        break;
                    }
                }
            }
        }

        if (!$designerId) {
            $designerName = match ($coreStatus) {
                CoreStatus::EURALIZ_ORDERS_RECEIVED => 'Euralíz',
                CoreStatus::CESAR_ORDERS_RECEIVED => 'César',
                CoreStatus::ADRIAN_ORDERS_RECEIVED => 'Adrián',
                default => null,
            };
            if ($designerName) {
                $designer = Designer::where('name', $designerName)->first();
                $designerId = $designer?->id;
            }
        }

        $dueDate = !empty($cardData['due']) ? substr($cardData['due'], 0, 10) : null;
        $rawCardName = $cardData['name'] ?? 'Tarea de diseño';
        $parsed = \App\Services\OrderTitleParserService::parse($rawCardName);

        if ($parsed['is_incompatible']) {
            Log::info("Skipped syncing incompatible Trello header card: '{$rawCardName}'");
            return null;
        }

        $trelloCreatedAt = null;
        if (!empty($cardData['id']) && strlen($cardData['id']) >= 8) {
            try {
                $hex = substr($cardData['id'], 0, 8);
                $trelloCreatedAt = \Carbon\Carbon::createFromTimestamp(hexdec($hex));
            } catch (\Exception $e) {}
        }

        $order = Order::updateOrCreate(
            ['trello_card_id' => $cardData['id']],
            [
                'trello_created_at' => $trelloCreatedAt ?: now(),
                'wo_number' => $parsed['wo_number'],
                'company_name' => $parsed['company_name'],
                'responsible_person' => $parsed['responsible_person'],
                'task_name' => $parsed['task_name'],
                'trello_title' => $parsed['trello_title'],
                'designer_id' => $designerId,
                'core_status' => $coreStatus,
                'current_due_date' => $dueDate,
                'original_due_date' => $dueDate,
                'start_date' => now()->toDateString(),
            ]
        );

        if ($order->wasRecentlyCreated) {
            app(AutomationEngine::class)->handleOrderCreated($order);
        }

        return $order;
    }

    /**
     * Safely update a single card on Trello (Push to Trello).
     */
    public function updateCardOnTrello(Order $order, string $apiKey = '0771bd12b868f2ee8e1a72f424085b5f', ?string $apiToken = null): bool
    {
        if (!$order->trello_card_id) {
            return false;
        }

        $apiToken = $apiToken ?: config('services.trello.token');

        if (!$apiToken) {
            Log::info("Trello sync skipped for order {$order->id}: No API token set.");
            return false;
        }

        try {
            $params = [
                'key' => $apiKey,
                'token' => $apiToken,
                'name' => \App\Services\OrderTitleParserService::buildTitle($order),
            ];

            if ($order->current_due_date) {
                $params['due'] = $order->current_due_date->toIso8601String();
            }

            $response = Http::put("{$this->baseUrl}/cards/{$order->trello_card_id}", $params);
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Failed to update Trello card {$order->trello_card_id}: " . $e->getMessage());
            return false;
        }
    }
}
