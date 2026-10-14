<?php

namespace App\Livewire\Orders;

use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderTitleParserService;
use App\Services\TrelloSyncService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $search = '';

    public function selectOrder(int $orderId): void
    {
        $this->dispatch('open-order-detail', orderId: $orderId);
        $this->reset('search');
    }

    public function selectClient(int $clientId): void
    {
        $this->dispatch('open-client-flyout', clientId: $clientId);
        $this->reset('search');
    }

    public function clearSearch(): void
    {
        $this->reset('search');
    }

    public function runTrelloSyncOnlyNew(): void
    {
        $apiKey = Setting::get('trello_api_key', config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f')));
        $userToken = Setting::get('trello_user_token', config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''))));
        $boardId = Setting::get('trello_board_id', config('services.trello.board_id', env('TRELLO_BOARD_ID', '')));

        if (empty(trim($boardId))) {
            $this->dispatch('toast', message: __('No hay un Tablero Trello configurado. Por favor configúralo en Sincronización Trello.'));

            return;
        }

        $syncService = app(TrelloSyncService::class);
        $extractedBoardId = $syncService->extractBoardId($boardId);

        // Fetch lists
        $listsRes = $syncService->getBoardLists($extractedBoardId, $apiKey, $userToken);
        if (! $listsRes['success']) {
            $this->dispatch('toast', message: __('Error al conectar con la API de Trello: ').($listsRes['error'] ?? 'Error de autenticación o tablero no encontrado'));

            return;
        }

        $lists = $listsRes['data'];
        $listsMap = [];
        foreach ($lists as $list) {
            $listsMap[$list['id']] = $list['name'];
        }

        // Fetch cards
        $cardsRes = $syncService->getBoardCards($extractedBoardId, $apiKey, $userToken);
        if (! $cardsRes['success']) {
            $this->dispatch('toast', message: __('Error al obtener tarjetas desde Trello.'));

            return;
        }

        $cards = $cardsRes['data'];

        $existingOrders = Order::whereNotNull('trello_card_id')
            ->where('trello_card_id', '!=', '')
            ->get(['id', 'trello_card_id', 'wo_number']);

        $existingTrelloMap = [];
        $existingWoMap = [];

        foreach ($existingOrders as $ord) {
            $rawId = trim($ord->trello_card_id);
            $existingTrelloMap[$rawId] = $ord;
            if (preg_match('/trello\.com\/c\/([a-zA-Z0-9]+)/i', $rawId, $m)) {
                $existingTrelloMap[$m[1]] = $ord;
            }
            if ($ord->wo_number) {
                $cleanWo = preg_replace('/^WO\s*/i', '', $ord->wo_number);
                $existingWoMap[$cleanWo] = $ord;
            }
        }

        $addedCount = 0;

        foreach ($cards as $card) {
            $cardId = $card['id'] ?? null;
            $shortLink = $card['shortLink'] ?? null;
            if (! $shortLink && ! empty($card['shortUrl']) && preg_match('/trello\.com\/c\/([a-zA-Z0-9]+)/i', $card['shortUrl'], $m)) {
                $shortLink = $m[1];
            }
            if (! $shortLink && ! empty($card['url']) && preg_match('/trello\.com\/c\/([a-zA-Z0-9]+)/i', $card['url'], $m)) {
                $shortLink = $m[1];
            }

            // Check if card is already registered by canonical ID or shortLink
            $matchedOrder = null;
            if ($cardId && isset($existingTrelloMap[$cardId])) {
                $matchedOrder = $existingTrelloMap[$cardId];
            } elseif ($shortLink && isset($existingTrelloMap[$shortLink])) {
                $matchedOrder = $existingTrelloMap[$shortLink];
            }

            if ($matchedOrder) {
                // Auto-canonicalize trello_card_id to the 24-char ID if needed
                if ($cardId && $matchedOrder->trello_card_id !== $cardId) {
                    $matchedOrder->update(['trello_card_id' => $cardId]);
                    $existingTrelloMap[$cardId] = $matchedOrder;
                }

                continue;
            }

            // Also check by WO number if card title includes WO
            $cardName = $card['name'] ?? '';
            $parsed = OrderTitleParserService::parse($cardName);
            if (! empty($parsed['wo_number']) && isset($existingWoMap[$parsed['wo_number']])) {
                $woOrder = $existingWoMap[$parsed['wo_number']];
                $existingRaw = trim($woOrder->trello_card_id ?? '');
                if (empty($existingRaw) || $existingRaw === $shortLink || strlen($existingRaw) < 20) {
                    if ($cardId) {
                        $woOrder->update(['trello_card_id' => $cardId]);
                        $existingTrelloMap[$cardId] = $woOrder;
                    }

                    continue;
                }
            }

            $res = $syncService->syncCardToOrder($card, $listsMap, $extractedBoardId, $apiKey, $userToken);
            if (! $res) {
                continue;
            }

            if ($res['action'] === 'created') {
                $existingTrelloMap[$cardId] = $res['order'];
                $addedCount++;
            }
        }

        $this->dispatch('order-updated');

        if ($addedCount > 0) {
            $this->dispatch('toast', message: __('Sincronización completada: se importaron :count tarjetas nuevas de Trello.', ['count' => $addedCount]));
        } else {
            $this->dispatch('toast', message: __('Sincronización completada: no se encontraron tarjetas nuevas en Trello.'));
        }
    }

    public function render(): View
    {
        $results = collect();
        $clientResults = collect();

        $queryStr = trim($this->search);

        if (mb_strlen($queryStr) >= 1) {
            $cleanQuery = trim(preg_replace('/\b(clients?|clientes?|empresas?|company|companies)\b/i', '', $queryStr));
            $searchTermToUse = ! empty($cleanQuery) ? $cleanQuery : $queryStr;

            $results = Order::inWorkspace()
                ->with(['designer', 'designers'])
                ->search($searchTermToUse)
                ->orderBy('updated_at', 'desc')
                ->take(8)
                ->get();

            $clientResults = Client::query()
                ->withCount(['activeOrders', 'locations', 'contacts'])
                ->with(['primaryContact', 'locations'])
                ->search($searchTermToUse)
                ->orderBy('name', 'asc')
                ->take(5)
                ->get();
        }

        return view('livewire.orders.header-search', [
            'results' => $results,
            'clientResults' => $clientResults,
        ]);
    }
}
