<?php

namespace App\Livewire\Backlog;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use App\Services\ClientMatchingService;
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
        $apiKey = env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f');
        $userToken = env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''));
        $boardId = env('TRELLO_BOARD_ID', '');

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
        $missingResult = $syncService->handleMissingOrders($incomingCardIds);
        $deletedCount = $missingResult['count'];
        foreach ($missingResult['changes'] as $change) {
            $changesList[] = $change;
        }

        $totalSynced = count($cards);

        $this->syncReport = [
            'show' => true,
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
            'designers' => Designer::where('active', true)->get(),
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
        ])->layout('components.layouts.app', ['title' => 'Backlog de Órdenes - Kudos Design Ops']);
    }
}
