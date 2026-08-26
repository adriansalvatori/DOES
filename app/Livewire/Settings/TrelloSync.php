<?php

namespace App\Livewire\Settings;

use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\OrderTitleParserService;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TrelloSync extends Component
{
    public $apiKey;

    public $userToken = '';

    public $boardId = '';

    public $syncLog = [];

    public function mount()
    {
        $this->apiKey = env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f');
        $this->userToken = env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET', ''));
        $this->boardId = env('TRELLO_BOARD_ID', '');
    }

    public function clearDemoData()
    {
        DB::transaction(function () {
            RelatedTask::query()->delete();
            DueDateHistory::query()->delete();
            OrderEvent::query()->delete();
            Order::query()->delete();
        });

        $timestamp = now()->format('H:i:s');
        $this->syncLog[] = "[$timestamp] 🗑️ Todos los datos de prueba eliminados. La base de datos está limpia.";
        session()->flash('message', 'Datos de prueba eliminados correctamente.');
    }

    public string $activeFilter = 'all';

    public ?array $selectedConflict = null;

    public array $syncReport = [
        'show' => false,
        'total' => 0,
        'added' => 0,
        'moved' => 0,
        'pushed' => 0,
        'updated' => 0,
        'deleted' => 0,
        'conflicts' => 0,
        'unchanged' => 0,
        'timestamp' => '',
        'changes' => [],
    ];

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
    }

    public function closeReportModal(): void
    {
        $this->syncReport['show'] = false;
        $this->activeFilter = 'all';
        $this->selectedConflict = null;
    }

    public function openConflictModal(int $orderId): void
    {
        foreach ($this->syncReport['changes'] as $change) {
            if (isset($change['order_id']) && $change['order_id'] == $orderId && $change['action'] === 'conflict') {
                $this->selectedConflict = $change;
                break;
            }
        }
    }

    public function closeConflictModal(): void
    {
        $this->selectedConflict = null;
    }

    public function resolveUseWorkspace(int $orderId): void
    {
        $order = Order::find($orderId);
        $pushedTitle = '';
        if ($order) {
            // Sanitize task_name if it redundantly starts with company_name
            $comp = trim($order->company_name);
            $task = trim($order->task_name);
            if (! empty($comp) && ! empty($task) && str_starts_with(mb_strtolower($task, 'UTF-8'), mb_strtolower($comp, 'UTF-8'))) {
                $cleanTask = trim(mb_substr($task, mb_strlen($comp, 'UTF-8'), null, 'UTF-8'), " \t\n\r\0\x0B-:");
                if (! empty($cleanTask)) {
                    $order->update(['task_name' => $cleanTask]);
                }
            }

            $success = app(TrelloSyncService::class)->updateCardOnTrello($order, $this->apiKey, $this->userToken, $this->boardId);
            $pushedTitle = OrderTitleParserService::buildTitle($order);

            if (! $success) {
                session()->flash('error', '⚠️ No se pudo actualizar en Trello. Verifica tu conexión o token de Trello.');
            }
        }

        foreach ($this->syncReport['changes'] as &$change) {
            if (isset($change['order_id']) && $change['order_id'] == $orderId) {
                $change['action'] = 'pushed_to_trello';
                $change['pushed_title'] = $pushedTitle;
                $change['details'] = ['Enviado a Trello con formato limpio'];
                break;
            }
        }

        $this->syncReport['conflicts'] = max(0, $this->syncReport['conflicts'] - 1);
        $this->syncReport['pushed'] = ($this->syncReport['pushed'] ?? 0) + 1;
        $this->selectedConflict = null;

        session()->flash('message', 'Conflicto resuelto. Se enviaron los datos de Workspace a Trello.');
    }

    public function resolveUseTrello(int $orderId): void
    {
        $targetChange = null;
        foreach ($this->syncReport['changes'] as &$change) {
            if (isset($change['order_id']) && $change['order_id'] == $orderId) {
                $targetChange = &$change;
                break;
            }
        }

        if ($targetChange && isset($targetChange['trello_data'])) {
            $order = Order::find($orderId);
            if ($order) {
                $trelloData = $targetChange['trello_data'];
                $updateData = [];

                if (! empty($trelloData['company_name'])) {
                    $updateData['company_name'] = $trelloData['company_name'];
                }
                if (! empty($trelloData['task_name'])) {
                    $updateData['task_name'] = $trelloData['task_name'];
                }
                if (! empty($trelloData['wo_number'])) {
                    $updateData['wo_number'] = $trelloData['wo_number'];
                }

                $order->update($updateData);
            }

            $targetChange['action'] = 'updated';
            $targetChange['details'] = ['Conflicto resuelto: Usar datos de Trello'];
            $this->syncReport['conflicts'] = max(0, $this->syncReport['conflicts'] - 1);
            $this->syncReport['updated'] = ($this->syncReport['updated'] ?? 0) + 1;
        }

        $this->selectedConflict = null;
        session()->flash('message', 'Conflicto resuelto. Se actualizaron los datos locales con la información de Trello.');
    }

    public function resolveAllWorkspace(): void
    {
        if (empty($this->syncReport['changes'])) {
            return;
        }

        $conflictIds = [];
        foreach ($this->syncReport['changes'] as $change) {
            if (isset($change['order_id']) && $change['action'] === 'conflict') {
                $conflictIds[] = (int) $change['order_id'];
            }
        }

        foreach ($conflictIds as $id) {
            $this->resolveUseWorkspace($id);
        }

        session()->flash('message', 'Todos los conflictos se resolvieron usando los datos de Workspace.');
    }

    public function resolveAllTrello(): void
    {
        if (empty($this->syncReport['changes'])) {
            return;
        }

        $conflictIds = [];
        foreach ($this->syncReport['changes'] as $change) {
            if (isset($change['order_id']) && $change['action'] === 'conflict') {
                $conflictIds[] = (int) $change['order_id'];
            }
        }

        foreach ($conflictIds as $id) {
            $this->resolveUseTrello($id);
        }

        session()->flash('message', 'Todos los conflictos se resolvieron usando los datos de Trello.');
    }

    public function runTrelloSync()
    {
        if (empty(trim($this->boardId))) {
            session()->flash('error', 'Por favor ingresa un ID o URL de Tablero Trello válido.');

            return;
        }

        $syncService = app(TrelloSyncService::class);
        $extractedBoardId = $syncService->extractBoardId($this->boardId);
        $timestamp = now()->format('H:i:s');

        $this->syncLog[] = "[$timestamp] Conectando a Trello API con Board ID/Link: {$extractedBoardId}...";

        // Fetch lists
        $listsRes = $syncService->getBoardLists($extractedBoardId, $this->apiKey, $this->userToken);
        if (! $listsRes['success']) {
            $this->syncLog[] = "[$timestamp] ❌ Error Trello API (Listas) - Status {$listsRes['status']}: {$listsRes['error']}";
            if ($listsRes['status'] == 401) {
                $this->syncLog[] = "[$timestamp] 💡 NOTA DE AUTENTICACIÓN: Tableros privados de Trello requieren un User Token. Haz clic en 'Generar Token de Usuario Trello' abajo e ingrésalo aquí.";
            } elseif ($listsRes['status'] == 404) {
                $this->syncLog[] = "[$timestamp] ⚠️ Tablero no encontrado. Revisa la URL o ID del tablero Trello.";
            }
            session()->flash('error', 'No se pudo sincronizar con Trello. Revisa el log de consola.');

            return;
        }

        $lists = $listsRes['data'];
        $listsMap = [];
        foreach ($lists as $list) {
            $listsMap[$list['id']] = $list['name'];
        }
        $this->syncLog[] = "[$timestamp] ✓ ".count($lists).' listas de Trello obtenidas ('.implode(', ', array_slice(array_values($listsMap), 0, 4)).'...).';

        // Fetch cards
        $cardsRes = $syncService->getBoardCards($extractedBoardId, $this->apiKey, $this->userToken);
        if (! $cardsRes['success']) {
            $this->syncLog[] = "[$timestamp] ❌ Error Trello API (Tarjetas) - Status {$cardsRes['status']}: {$cardsRes['error']}";
            session()->flash('error', 'Error al obtener tarjetas de Trello.');

            return;
        }

        $cards = $cardsRes['data'];
        $incomingCardIds = array_column($cards, 'id');

        $addedCount = 0;
        $movedCount = 0;
        $pushedCount = 0;
        $updatedCount = 0;
        $conflictCount = 0;
        $unchangedCount = 0;
        $changesList = [];

        foreach ($cards as $card) {
            $res = $syncService->syncCardToOrder($card, $listsMap, $extractedBoardId, $this->apiKey, $this->userToken);
            if (! $res) {
                continue;
            }

            match ($res['action']) {
                'created' => $addedCount++,
                'moved' => $movedCount++,
                'pushed_to_trello' => $pushedCount++,
                'updated' => $updatedCount++,
                'conflict' => $conflictCount++,
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
                    'diff_fields' => $res['diff_fields'] ?? [],
                    'workspace_updated_at' => $res['workspace_updated_at'] ?? 'N/A',
                    'trello_updated_at' => $res['trello_updated_at'] ?? 'N/A',
                    'workspace_data' => $res['workspace_data'] ?? [],
                    'trello_data' => $res['trello_data'] ?? [],
                    'card_data' => $res['card_data'] ?? [],
                ];
            }
        }

        // Calculate deleted/archived on Trello (Mark as missing instead of deleting)
        $deletedOrders = Order::whereNotNull('trello_card_id')
            ->whereNotIn('trello_card_id', $incomingCardIds)
            ->get();

        $deletedCount = $deletedOrders->count();
        foreach ($deletedOrders as $delOrder) {
            $changesList[] = [
                'order_id' => $delOrder->id,
                'action' => 'deleted',
                'company' => $delOrder->company_name ?: 'Empresa',
                'task' => $delOrder->task_name ?: 'Tarea',
                'previous_status' => $delOrder->core_status?->label() ?: 'Tablero Trello',
                'new_status' => 'Falta en Trello (Marcada como faltante)',
            ];

            $delOrder->update(['is_missing_from_trello' => true]);
        }

        $totalSynced = count($cards);

        $this->syncReport = [
            'show' => true,
            'total' => $totalSynced,
            'added' => $addedCount,
            'moved' => $movedCount,
            'pushed' => $pushedCount,
            'updated' => $updatedCount,
            'conflicts' => $conflictCount,
            'deleted' => $deletedCount,
            'unchanged' => $unchangedCount,
            'timestamp' => now()->format('d M, Y - h:i A'),
            'changes' => $changesList,
        ];

        $this->syncLog[] = "[$timestamp] 🎉 Sincronización procesada: {$totalSynced} tarjetas ({$addedCount} nuevas, {$pushedCount} enviadas a Trello, {$movedCount} movidas, {$updatedCount} actualizadas, {$conflictCount} conflictos, {$deletedCount} faltantes en Trello).";
        session()->flash('message', "Sincronización con Trello completada. ({$conflictCount} conflictos pendientes por resolver).");
    }

    public function render()
    {
        return view('livewire.settings.trello-sync')
            ->layout('components.layouts.app', ['title' => 'Configuración de Trello Sync - Kudos Design Ops']);
    }
}
