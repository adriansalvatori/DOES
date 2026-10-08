<?php

namespace App\Livewire\Backlog;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Setting;
use App\Services\ClientMatchingService;
use App\Services\OrderTitleParserService;
use App\Services\TrelloSyncService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = 'all';

    public $designerFilter = 'all';

    public $companyFilter = 'all';

    public $responsibleFilter = 'all';

    public $sortBy = 'trello_created_at_desc';

    public $perPage = 25;

    public $selectedOrders = [];

    public $selectAll = false;

    public string $activeFilter = 'all';

    public array $syncReport = [
        'show' => false,
        'type' => 'full',
        'total' => 0,
        'added' => 0,
        'moved' => 0,
        'pushed' => 0,
        'updated' => 0,
        'deleted' => 0,
        'unchanged' => 0,
        'timestamp' => '',
        'changes' => [],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingDesignerFilter()
    {
        $this->resetPage();
    }

    public function updatingCompanyFilter()
    {
        $this->resetPage();
    }

    public function updatingResponsibleFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedOrders = $this->getFilteredQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }

    public function addToWorkspace($orderId)
    {
        $order = Order::findOrFail($orderId);
        $updateData = [
            'in_workspace' => true,
            'is_new_from_trello' => false,
        ];

        if ($order->company_name) {
            $rawMatch = $order->company_name.($order->location_name ? ' REF '.$order->location_name : '');
            $match = app(ClientMatchingService::class)->matchOrCreate($rawMatch, $order->responsible_person, createIfMissing: true);
            if ($match['client']) {
                $updateData['client_id'] = $match['client']->id;
                $updateData['company_name'] = $match['client']->name;
            }
            if ($match['location']) {
                $updateData['client_location_id'] = $match['location']->id;
            }
        }

        $order->update($updateData);

        session()->flash('message', "Orden {$order->company_name} añadida al Workspace activo.");
    }

    public function addSelectedToWorkspace()
    {
        if (empty($this->selectedOrders)) {
            session()->flash('warning', 'Selecciona al menos una orden para añadir al Workspace.');

            return;
        }

        $orders = Order::whereIn('id', $this->selectedOrders)->get();
        foreach ($orders as $order) {
            $updateData = [
                'in_workspace' => true,
                'is_new_from_trello' => false,
            ];

            if ($order->company_name) {
                $rawMatch = $order->company_name.($order->location_name ? ' REF '.$order->location_name : '');
                $match = app(ClientMatchingService::class)->matchOrCreate($rawMatch, $order->responsible_person, createIfMissing: true);
                if ($match['client']) {
                    $updateData['client_id'] = $match['client']->id;
                    $updateData['company_name'] = $match['client']->name;
                }
                if ($match['location']) {
                    $updateData['client_location_id'] = $match['location']->id;
                }
            }

            $order->update($updateData);
        }
        $count = $orders->count();
        $this->selectedOrders = [];
        $this->selectAll = false;

        session()->flash('message', "Se añadieron {$count} órdenes al Workspace activo correctamente.");
    }

    public function addAllFilteredToWorkspace()
    {
        $orders = $this->getFilteredQuery()->get();
        foreach ($orders as $order) {
            $updateData = [
                'in_workspace' => true,
                'is_new_from_trello' => false,
            ];

            if ($order->company_name) {
                $rawMatch = $order->company_name.($order->location_name ? ' REF '.$order->location_name : '');
                $match = app(ClientMatchingService::class)->matchOrCreate($rawMatch, $order->responsible_person, createIfMissing: true);
                if ($match['client']) {
                    $updateData['client_id'] = $match['client']->id;
                    $updateData['company_name'] = $match['client']->name;
                }
                if ($match['location']) {
                    $updateData['client_location_id'] = $match['location']->id;
                }
            }

            $order->update($updateData);
        }
        $count = $orders->count();
        $this->selectedOrders = [];
        $this->selectAll = false;

        session()->flash('message', "Se añadieron las {$count} órdenes filtradas al Workspace activo.");
    }

    public function removeFromWorkspace($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['in_workspace' => false]);

        session()->flash('message', "Orden {$order->company_name} movida de regreso al Backlog.");
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
    }

    public function closeReportModal(): void
    {
        $this->syncReport['show'] = false;
        $this->activeFilter = 'all';
    }

    public function runTrelloSync()
    {
        $apiKey = Setting::get('trello_api_key', config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f')));
        $userToken = Setting::get('trello_user_token', config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''))));
        $boardId = Setting::get('trello_board_id', config('services.trello.board_id', env('TRELLO_BOARD_ID', '')));

        if (empty(trim($boardId))) {
            session()->flash('warning', 'No hay un Tablero Trello configurado. Por favor configúralo en Sincronización Trello.');

            return;
        }

        $syncService = app(TrelloSyncService::class);
        $extractedBoardId = $syncService->extractBoardId($boardId);

        // Fetch lists
        $listsRes = $syncService->getBoardLists($extractedBoardId, $apiKey, $userToken);
        if (! $listsRes['success']) {
            session()->flash('warning', 'Error al conectar con la API de Trello: '.($listsRes['error'] ?? 'Error de autenticación o tablero no encontrado'));

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
            session()->flash('warning', 'Error al obtener tarjetas desde Trello.');

            return;
        }

        $cards = $cardsRes['data'];
        $incomingCardIds = array_column($cards, 'id');
        $incomingShortLinks = array_values(array_filter(array_column($cards, 'shortLink')));

        $addedCount = 0;
        $movedCount = 0;
        $pushedCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $changesList = [];

        foreach ($cards as $card) {
            $res = $syncService->syncCardToOrder($card, $listsMap, $extractedBoardId, $apiKey, $userToken);
            if (! $res) {
                continue;
            }

            match ($res['action']) {
                'created' => $addedCount++,
                'moved' => $movedCount++,
                'pushed_to_trello' => $pushedCount++,
                'updated' => $updatedCount++,
                'unchanged' => $unchangedCount++,
                default => null,
            };

            if ($res['action'] !== 'unchanged') {
                $changesList[] = [
                    'order_id' => $res['order']->id,
                    'action' => $res['action'],
                    'company' => $res['company_name'] ?? 'Empresa',
                    'task' => $res['task_name'] ?? 'Tarea',
                    'previous_status' => $res['previous_status'] ?? '',
                    'new_status' => $res['new_status'] ?? '',
                    'details' => $res['details'] ?? [],
                ];
            }
        }

        // Mark missing orders
        $missingResult = $syncService->handleMissingOrders($incomingCardIds, $incomingShortLinks);
        $deletedCount = $missingResult['count'];
        foreach ($missingResult['changes'] as $change) {
            $changesList[] = $change;
        }

        $totalSynced = count($cards);

        $this->syncReport = [
            'show' => true,
            'type' => 'full',
            'total' => $totalSynced,
            'added' => $addedCount,
            'moved' => $movedCount,
            'pushed' => $pushedCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'unchanged' => $unchangedCount,
            'timestamp' => now()->format('d M, Y - h:i A'),
            'changes' => $changesList,
        ];

        session()->flash('message', "Sincronización con Trello completada exitosamente ({$totalSynced} tarjetas procesadas).");
    }

    public function runTrelloSyncOnlyNew(): void
    {
        $apiKey = Setting::get('trello_api_key', config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f')));
        $userToken = Setting::get('trello_user_token', config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''))));
        $boardId = Setting::get('trello_board_id', config('services.trello.board_id', env('TRELLO_BOARD_ID', '')));

        if (empty(trim($boardId))) {
            session()->flash('warning', 'No hay un Tablero Trello configurado. Por favor configúralo en Sincronización Trello.');

            return;
        }

        $syncService = app(TrelloSyncService::class);
        $extractedBoardId = $syncService->extractBoardId($boardId);

        // Fetch lists
        $listsRes = $syncService->getBoardLists($extractedBoardId, $apiKey, $userToken);
        if (! $listsRes['success']) {
            session()->flash('warning', 'Error al conectar con la API de Trello: '.($listsRes['error'] ?? 'Error de autenticación o tablero no encontrado'));

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
            session()->flash('warning', 'Error al obtener tarjetas desde Trello.');

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
        $skippedCount = 0;
        $changesList = [];

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
                $skippedCount++;

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
                    $skippedCount++;

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
                $changesList[] = [
                    'order_id' => $res['order']->id,
                    'action' => 'created',
                    'company' => $res['company_name'] ?? 'Empresa',
                    'task' => $res['task_name'] ?? 'Tarea',
                    'previous_status' => $res['previous_status'] ?? '',
                    'new_status' => $res['new_status'] ?? '',
                    'details' => ! empty($res['details']) ? $res['details'] : ['Nueva tarjeta importada desde Trello'],
                ];
            }
        }

        $totalSynced = count($cards);

        $this->syncReport = [
            'show' => true,
            'type' => 'new_only',
            'total' => $totalSynced,
            'added' => $addedCount,
            'moved' => 0,
            'pushed' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => $skippedCount,
            'timestamp' => now()->format('d M, Y - h:i A'),
            'changes' => $changesList,
        ];

        $this->activeFilter = $addedCount > 0 ? 'created' : 'all';

        if ($addedCount > 0) {
            session()->flash('message', "Sincronización completada: se importaron {$addedCount} tarjetas nuevas de Trello.");
        } else {
            session()->flash('message', "No hay tarjetas nuevas en Trello ({$skippedCount} existentes omitidas).");
        }
    }

    protected function getFilteredQuery()
    {
        $query = Order::inBacklog()->prioritizeUrgente()->with(['designer', 'designers']);

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('core_status', $this->statusFilter);
        }

        if ($this->designerFilter !== 'all') {
            $query->where('designer_id', $this->designerFilter);
        }

        if ($this->companyFilter !== 'all') {
            $query->where('company_name', $this->companyFilter);
        }

        if ($this->responsibleFilter !== 'all') {
            $query->where('responsible_person', $this->responsibleFilter);
        }

        match ($this->sortBy) {
            'trello_created_at_asc' => $query->orderBy('trello_created_at', 'asc'),
            'due_date_asc' => $query->orderByRaw('current_due_date IS NULL, current_due_date ASC'),
            'company_asc' => $query->orderBy('company_name', 'asc'),
            default => $query->orderBy('trello_created_at', 'desc'),
        };

        return $query;
    }

    public function render()
    {
        $orders = $this->getFilteredQuery()->paginate($this->perPage);
        $newTrelloOrders = Order::inBacklog()->newFromTrello()->prioritizeUrgente()->with(['designer', 'designers'])->get();
        $backlogTotalCount = Order::inBacklog()->count();
        $activeWorkspaceCount = Order::inWorkspace()->count();

        return view('livewire.backlog.index', [
            'orders' => $orders,
            'newTrelloOrders' => $newTrelloOrders,
            'backlogTotalCount' => $backlogTotalCount,
            'activeWorkspaceCount' => $activeWorkspaceCount,
            'designers' => Designer::where('active', true)->internal()->get(),
            'coreStatuses' => CoreStatus::cases(),
            'existingCompanies' => Order::inBacklog()
                ->whereNotNull('company_name')
                ->where('company_name', '!=', '')
                ->distinct()
                ->orderBy('company_name')
                ->pluck('company_name'),
            'existingResponsibles' => Order::inBacklog()
                ->whereNotNull('responsible_person')
                ->where('responsible_person', '!=', '')
                ->distinct()
                ->orderBy('responsible_person')
                ->pluck('responsible_person'),
        ])->layout('components.layouts.app', ['title' => __('Backlog de Órdenes - ').config('app.name')]);
    }
}
