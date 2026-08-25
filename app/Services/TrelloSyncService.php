<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\TrelloListMapping;
use Carbon\Carbon;
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
    public function mapListToCoreStatus(string $listName, ?string $listId = null): CoreStatus
    {
        if ($listId) {
            $mapping = TrelloListMapping::where('trello_list_id', $listId)->first();
            if ($mapping && $mapping->core_status) {
                return $mapping->core_status;
            }
        }

        $cleanName = trim($listName);
        if ($cleanName !== '') {
            $mapping = TrelloListMapping::where('trello_list_name', $cleanName)->first();
            if ($mapping && $mapping->core_status) {
                return $mapping->core_status;
            }
        }

        $normalized = strtoupper($cleanName);

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
            Log::error('Trello API error fetching board lists: '.$e->getMessage());

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
            Log::error('Trello API error fetching board cards: '.$e->getMessage());

            return ['success' => false, 'status' => 500, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a single Trello card data array into an Order model and return action metrics.
     */
    public function syncCardToOrder(array $cardData, array $listsMap = [], ?string $boardId = null, ?string $apiKey = null, ?string $apiToken = null): ?array
    {
        $listId = $cardData['idList'] ?? '';
        $listName = $listsMap[$listId] ?? 'ENTRANTE';
        $coreStatus = $this->mapListToCoreStatus($listName, $listId);

        // Strictly resolve designer from Trello card members array
        $designerId = null;
        if (! empty($cardData['members'])) {
            foreach ($cardData['members'] as $member) {
                $mId = $member['id'] ?? null;
                $fullName = trim($member['fullName'] ?? '');
                $username = trim($member['username'] ?? '');

                if ($mId || $fullName || $username) {
                    $designer = Designer::where(function ($q) use ($mId, $fullName, $username) {
                        if ($mId) {
                            $q->where('trello_member_id', $mId);
                        }
                        if ($fullName) {
                            $q->orWhere('name', 'like', "%{$fullName}%");
                        }
                        if ($username) {
                            $q->orWhere('name', 'like', "%{$username}%");
                        }
                    })->first();

                    if (! $designer && ! empty($fullName)) {
                        // Alias and transliterated search to prevent duplicate designer records
                        $clean = mb_strtolower(preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $fullName) ?: $fullName));

                        if (str_contains($clean, 'cesar') || str_contains($clean, 'guzman')) {
                            $designer = Designer::where('name', 'like', '%Cés%')->orWhere('name', 'like', '%Cesar%')->first();
                        } elseif (str_contains($clean, 'eural') || str_contains($clean, 'bravo')) {
                            $designer = Designer::where('name', 'like', '%Eural%')->first();
                        } elseif (str_contains($clean, 'adr') || str_contains($clean, 'reinoza')) {
                            $designer = Designer::where('name', 'like', '%Adr%')->first();
                        }
                    }

                    if ($designer) {
                        if ($mId && $designer->trello_member_id !== $mId) {
                            $designer->update(['trello_member_id' => $mId]);
                        }
                        $designerId = $designer->id;
                        break;
                    }

                    if (! $designer && ! empty($fullName)) {
                        $designer = Designer::create([
                            'name' => ucwords(strtolower($fullName)),
                            'trello_member_id' => $mId,
                            'active' => true,
                        ]);
                        $designerId = $designer->id;
                        break;
                    }
                }
            }
        }

        if (! $designerId) {
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

        $dueDate = ! empty($cardData['due']) ? substr($cardData['due'], 0, 10) : null;
        $rawCardName = $cardData['name'] ?? 'Tarea de diseño';
        $parsed = OrderTitleParserService::parse($rawCardName);

        if ($parsed['is_incompatible']) {
            Log::info("Skipped syncing incompatible Trello header card: '{$rawCardName}'");

            return null;
        }

        $trelloCreatedAt = null;
        if (! empty($cardData['id']) && strlen($cardData['id']) >= 8) {
            try {
                $hex = substr($cardData['id'], 0, 8);
                $trelloCreatedAt = Carbon::createFromTimestamp(hexdec($hex));
            } catch (\Exception $e) {
            }
        }

        $existing = Order::where('trello_card_id', $cardData['id'])->first();
        $isNew = ! $existing;

        $targetStatus = $coreStatus;
        $action = 'created';
        $updateDetails = [];

        if ($existing) {
            // Track human-readable diff details before attributes are updated
            $oldDueDateStr = $existing->current_due_date ? $existing->current_due_date->toDateString() : null;
            if ($oldDueDateStr !== $dueDate) {
                $oldStr = $existing->current_due_date ? Carbon::parse($existing->current_due_date)->format('d M') : 'Sin fecha';
                $newStr = $dueDate ? Carbon::parse($dueDate)->format('d M') : 'Sin fecha';
                $updateDetails[] = "Fecha de entrega: {$oldStr} ➔ {$newStr}";
            }

            if ($existing->designer_id !== $designerId) {
                $oldDesigner = $existing->designer?->name ?? 'Sin asignar';
                $newDesigner = $designerId ? (Designer::find($designerId)?->name ?? 'Sin asignar') : 'Sin asignar';
                $updateDetails[] = "Diseñador: {$oldDesigner} ➔ {$newDesigner}";
            }

            if ($existing->trello_title !== $parsed['trello_title']) {
                if ($existing->task_name !== $parsed['task_name'] && ! empty($existing->task_name) && ! empty($parsed['task_name'])) {
                    $updateDetails[] = "Tarea: {$existing->task_name} ➔ {$parsed['task_name']}";
                } elseif ($existing->company_name !== $parsed['company_name'] && ! empty($existing->company_name) && ! empty($parsed['company_name'])) {
                    $updateDetails[] = "Empresa: {$existing->company_name} ➔ {$parsed['company_name']}";
                } else {
                    $updateDetails[] = 'Título de tarjeta actualizado en Trello';
                }
            }

            if (! empty($parsed['responsible_person']) && $existing->responsible_person !== $parsed['responsible_person']) {
                $updateDetails[] = 'Contacto: '.($existing->responsible_person ?: 'Sin contacto')." ➔ {$parsed['responsible_person']}";
            }

            if ($existing->in_workspace) {
                // Local status takes precedence for active workspace orders
                $targetStatus = $existing->core_status;

                if ($existing->core_status !== $coreStatus) {
                    $this->updateCardOnTrello($existing, $apiKey, $apiToken, $boardId);
                    $action = 'pushed_to_trello';
                } elseif (! empty($updateDetails)) {
                    $action = 'updated';
                } else {
                    $action = 'unchanged';
                }
            } else {
                // Backlog orders: Trello status updates local
                if ($existing->core_status !== $coreStatus) {
                    $action = 'moved';
                } elseif (! empty($updateDetails)) {
                    $action = 'updated';
                } else {
                    $action = 'unchanged';
                }
            }
        }

        $trelloWo = $parsed['wo_number'];
        $trelloWoClean = $trelloWo ? trim(preg_replace('/^WO\s*/i', '', $trelloWo)) : '';
        $isTrelloWoValid = ! empty($trelloWoClean) && ! preg_match('/^0+$/', $trelloWoClean);

        $pendingWoNumber = null;
        $finalWoNumber = $parsed['wo_number'];

        if ($existing) {
            if ($isTrelloWoValid && ($existing->hasNoWo() || $existing->wo_number !== $trelloWo)) {
                // Keep current WO in DOES, put Trello's valid WO into pending_wo_number for user decision inside Card Detail
                $finalWoNumber = $existing->wo_number;
                $pendingWoNumber = $trelloWo;
                if ($action === 'unchanged') {
                    $action = 'updated';
                }
                if ($existing->pending_wo_number !== $pendingWoNumber) {
                    $updateDetails[] = "Nueva WO detectada en Trello: {$trelloWo}";
                }
            } elseif ($existing->wo_number === $trelloWo) {
                // Already in sync, clear any stale pending WO
                $pendingWoNumber = null;
            } else {
                $pendingWoNumber = $existing->pending_wo_number;
            }

            if ($action === 'updated' && empty($updateDetails)) {
                $updateDetails[] = 'Datos de tarjeta actualizados';
            }
        }

        $attributes = [
            'trello_created_at' => $trelloCreatedAt ?: now(),
            'wo_number' => $finalWoNumber,
            'pending_wo_number' => $pendingWoNumber,
            'company_name' => $parsed['company_name'],
            'responsible_person' => $parsed['responsible_person'],
            'task_name' => $parsed['task_name'],
            'trello_title' => $parsed['trello_title'],
            'designer_id' => $designerId,
            'core_status' => $targetStatus,
            'current_due_date' => $dueDate,
            'original_due_date' => $dueDate,
            'start_date' => now()->toDateString(),
            'is_missing_from_trello' => false,
        ];

        if ($isNew) {
            $attributes['is_new_from_trello'] = true;
            $attributes['in_workspace'] = false; // New cards synced from Trello enter into Backlog inbox
        } elseif ($existing && $existing->in_workspace && $existing->client_id) {
            $attributes['company_name'] = $existing->company_name;
        }

        $order = Order::updateOrCreate(
            ['trello_card_id' => $cardData['id']],
            $attributes
        );

        if (! ($existing && $existing->in_workspace && $existing->client_id)) {
            $createIfMissing = (bool) $order->in_workspace;
            $match = app(ClientMatchingService::class)->matchOrCreate(
                $parsed['company_name'],
                $parsed['responsible_person'],
                createIfMissing: $createIfMissing
            );

            $updateQuietlyData = [];
            if ($match['client']) {
                $updateQuietlyData['client_id'] = $match['client']->id;
                $updateQuietlyData['company_name'] = $match['client']->name;
            }
            if ($match['location']) {
                $updateQuietlyData['client_location_id'] = $match['location']->id;
            }

            if (! empty($updateQuietlyData)) {
                $order->updateQuietly($updateQuietlyData);
            }
        }

        if ($designerId) {
            $order->syncDesigners([$designerId]);
        }

        if ($isNew) {
            app(AutomationEngine::class)->handleOrderCreated($order);
        } else {
            app(SlaEngine::class)->checkOverdue($order);
            if (empty($dueDate) && ! empty($oldDueDateStr)) {
                app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
            }
        }

        $previousStatusLabel = $existing?->core_status?->label();
        if ($action === 'pushed_to_trello') {
            $previousStatusLabel = $coreStatus->label(); // Trello list status before update
        }

        return [
            'order' => $order,
            'action' => $action,
            'company_name' => $parsed['company_name'],
            'task_name' => $parsed['task_name'],
            'previous_status' => $previousStatusLabel,
            'new_status' => $targetStatus->label(),
            'details' => $updateDetails,
        ];
    }

    /**
     * Get Trello List ID corresponding to a CoreStatus.
     */
    public function getTrelloListIdForCoreStatus(CoreStatus $status, ?string $boardId = null, ?string $apiKey = null, ?string $apiToken = null): ?string
    {
        $customMapping = TrelloListMapping::where('core_status', $status)->first();
        if ($customMapping && ! empty($customMapping->trello_list_id)) {
            return $customMapping->trello_list_id;
        }

        $boardId = $boardId ?: config('services.trello.board_id', env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54'));
        $apiKey = $apiKey ?: config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = $apiToken ?: config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));

        $res = $this->getBoardLists($boardId, $apiKey, $apiToken);

        if (! $res['success'] || empty($res['data'])) {
            return null;
        }

        foreach ($res['data'] as $list) {
            $lName = $list['name'] ?? '';
            $lId = $list['id'] ?? null;
            $mappedStatus = $this->mapListToCoreStatus($lName, $lId);
            if ($mappedStatus === $status) {
                return $list['id'];
            }
        }

        return null;
    }

    /**
     * Safely update a single card on Trello (Push to Trello name, due date, and list position).
     * Strictly targets Active Workspace Orders (Backlog cards are never pushed).
     */
    public function updateCardOnTrello(Order $order, ?string $apiKey = null, ?string $apiToken = null, ?string $boardId = null): bool
    {
        if (! $order->trello_card_id || ! $order->in_workspace) {
            return false;
        }

        $apiKey = $apiKey ?: config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = $apiToken ?: config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));
        $boardId = $boardId ?: config('services.trello.board_id', env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54'));

        if (! $apiToken) {
            Log::info("Trello sync skipped for order {$order->id}: No API token set.");

            return false;
        }

        try {
            $params = [
                'key' => $apiKey,
                'token' => $apiToken,
                'name' => OrderTitleParserService::buildTitle($order),
            ];

            if ($order->current_due_date) {
                $params['due'] = $order->current_due_date->toIso8601String();
            }

            if ($order->core_status) {
                $targetListId = $this->getTrelloListIdForCoreStatus($order->core_status, $boardId, $apiKey, $apiToken);
                if ($targetListId) {
                    $params['idList'] = $targetListId;
                }
            }

            $response = Http::put("{$this->baseUrl}/cards/{$order->trello_card_id}", $params);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Failed to update Trello card {$order->trello_card_id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Create a new card on Trello for an existing workspace Order and link its trello_card_id.
     */
    public function createCardOnTrello(Order $order, ?string $apiKey = null, ?string $apiToken = null, ?string $boardId = null): array
    {
        $apiKey = $apiKey ?: config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = $apiToken ?: config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));
        $boardId = $boardId ?: config('services.trello.board_id', env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54'));

        if (! $apiToken) {
            Log::info("Trello card creation skipped for order {$order->id}: No API token set.");

            return ['success' => false, 'error' => 'No hay Token de API de Trello configurado.'];
        }

        try {
            $targetListId = null;
            if ($order->core_status) {
                $targetListId = $this->getTrelloListIdForCoreStatus($order->core_status, $boardId, $apiKey, $apiToken);
            }

            if (! $targetListId) {
                $listsRes = $this->getBoardLists($boardId, $apiKey, $apiToken);
                if ($listsRes['success'] && ! empty($listsRes['data'])) {
                    $targetListId = $listsRes['data'][0]['id'] ?? null;
                }
            }

            if (! $targetListId) {
                return ['success' => false, 'error' => 'No se pudo determinar la lista de Trello de destino.'];
            }

            $params = [
                'key' => $apiKey,
                'token' => $apiToken,
                'idList' => $targetListId,
                'name' => OrderTitleParserService::buildTitle($order),
            ];

            if ($order->current_due_date) {
                $params['due'] = $order->current_due_date->toIso8601String();
            }

            if (! empty($order->task_name) && $order->task_name !== $order->company_name) {
                $params['desc'] = "Creado desde KUDOSDOES Workspace.\nCliente: {$order->company_name}\nTarea: {$order->task_name}";
            }

            $response = Http::post("{$this->baseUrl}/cards", $params);

            if ($response->successful()) {
                $data = $response->json();
                $cardId = $data['id'] ?? null;
                if ($cardId) {
                    $order->update([
                        'trello_card_id' => $cardId,
                        'trello_title' => $data['name'] ?? OrderTitleParserService::buildTitle($order),
                        'trello_created_at' => now(),
                    ]);

                    return ['success' => true, 'card_id' => $cardId, 'url' => $data['url'] ?? "https://trello.com/c/{$cardId}"];
                }
            }

            Log::warning("Failed to create Trello card for order {$order->id}: ".$response->body());

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error("Trello API error creating card for order {$order->id}: ".$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch all comments for a given Trello card.
     */
    public function getCardComments(string $cardId, ?string $apiKey = null, ?string $apiToken = null): array
    {
        $cardId = trim($cardId);
        if (empty($cardId)) {
            return ['success' => false, 'error' => 'No card ID specified.', 'comments' => []];
        }

        $apiKey = $apiKey ?: config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = $apiToken ?: config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));

        $params = ['key' => $apiKey, 'filter' => 'commentCard'];
        if ($apiToken) {
            $params['token'] = $apiToken;
        }

        try {
            $response = Http::get("{$this->baseUrl}/cards/{$cardId}/actions", $params);

            if ($response->successful()) {
                $actions = $response->json();
                $comments = [];

                foreach ($actions as $action) {
                    $creator = $action['memberCreator'] ?? [];
                    $avatar = ! empty($creator['avatarUrl'])
                        ? $creator['avatarUrl'].'/50.png'
                        : (! empty($creator['avatarHash']) ? "https://trello-members.s3.amazonaws.com/{$creator['id']}/{$creator['avatarHash']}/50.png" : null);

                    $comments[] = [
                        'id' => $action['id'] ?? null,
                        'text' => $action['data']['text'] ?? '',
                        'date' => $action['date'] ?? null,
                        'author_id' => $creator['id'] ?? null,
                        'author_name' => $creator['fullName'] ?? ($creator['username'] ?? 'Usuario Trello'),
                        'author_username' => $creator['username'] ?? '',
                        'author_avatar' => $avatar,
                    ];
                }

                return ['success' => true, 'comments' => $comments];
            }

            return ['success' => false, 'status' => $response->status(), 'error' => $response->body(), 'comments' => []];
        } catch (\Exception $e) {
            Log::error("Trello API error fetching card comments for {$cardId}: ".$e->getMessage());

            return ['success' => false, 'status' => 500, 'error' => $e->getMessage(), 'comments' => []];
        }
    }

    /**
     * Add a new comment to a Trello card.
     */
    public function addCardComment(string $cardId, string $text, ?string $apiKey = null, ?string $apiToken = null): array
    {
        $cardId = trim($cardId);
        $text = trim($text);

        if (empty($cardId) || empty($text)) {
            return ['success' => false, 'error' => 'Card ID and comment text are required.'];
        }

        $apiKey = $apiKey ?: config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = $apiToken ?: config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));

        if (! $apiToken) {
            return ['success' => false, 'error' => 'Se requiere Token de Usuario Trello para agregar comentarios.'];
        }

        $params = [
            'key' => $apiKey,
            'token' => $apiToken,
            'text' => $text,
        ];

        try {
            $response = Http::post("{$this->baseUrl}/cards/{$cardId}/actions/comments", $params);

            if ($response->successful()) {
                $action = $response->json();
                $creator = $action['memberCreator'] ?? [];
                $avatar = ! empty($creator['avatarUrl'])
                    ? $creator['avatarUrl'].'/50.png'
                    : (! empty($creator['avatarHash']) ? "https://trello-members.s3.amazonaws.com/{$creator['id']}/{$creator['avatarHash']}/50.png" : null);

                return [
                    'success' => true,
                    'comment' => [
                        'id' => $action['id'] ?? null,
                        'text' => $action['data']['text'] ?? $text,
                        'date' => $action['date'] ?? now()->toIso8601String(),
                        'author_id' => $creator['id'] ?? null,
                        'author_name' => $creator['fullName'] ?? 'Trello User',
                        'author_username' => $creator['username'] ?? '',
                        'author_avatar' => $avatar,
                    ],
                ];
            }

            return ['success' => false, 'status' => $response->status(), 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error("Trello API error adding comment to card {$cardId}: ".$e->getMessage());

            return ['success' => false, 'status' => 500, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get comments for all active workspace orders that have a Trello card linked.
     */
    public function getWorkspaceActiveOrdersComments(?string $apiKey = null, ?string $apiToken = null): array
    {
        $orders = Order::where('in_workspace', true)
            ->whereNotNull('trello_card_id')
            ->where('trello_card_id', '!=', '')
            ->get();

        $results = [];
        foreach ($orders as $order) {
            $res = $this->getCardComments($order->trello_card_id, $apiKey, $apiToken);
            $results[$order->id] = [
                'order' => $order,
                'success' => $res['success'],
                'comments' => $res['comments'] ?? [],
                'error' => $res['error'] ?? null,
            ];
        }

        return $results;
    }
}
