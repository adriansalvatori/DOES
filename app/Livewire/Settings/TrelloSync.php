<?php

namespace App\Livewire\Settings;

use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
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

    public array $syncReport = [
        'show' => false,
        'total' => 0,
        'added' => 0,
        'moved' => 0,
        'updated' => 0,
        'deleted' => 0,
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
        $updatedCount = 0;
        $unchangedCount = 0;
        $changesList = [];

        foreach ($cards as $card) {
            $res = $syncService->syncCardToOrder($card, $listsMap);
            if (! $res) {
                continue;
            }

            match ($res['action']) {
                'created' => $addedCount++,
                'moved' => $movedCount++,
                'updated' => $updatedCount++,
                'unchanged' => $unchangedCount++,
            };

            if ($res['action'] !== 'unchanged') {
                $changesList[] = [
                    'order_id' => $res['order']->id,
                    'action' => $res['action'],
                    'company' => $res['company_name'] ?? 'Empresa',
                    'task' => $res['task_name'] ?? 'Tarea',
                    'previous_status' => $res['previous_status'] ?? '',
                    'new_status' => $res['new_status'] ?? '',
                ];
            }
        }

        // Calculate deleted/archived on Trello
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
                'new_status' => 'Archivada / Eliminada de Trello',
            ];

            $delOrder->designers()->detach();
            $delOrder->relatedTasks()->delete();
            $delOrder->events()->delete();
            $delOrder->dueDateHistories()->delete();
            $delOrder->forceDelete();
        }

        $totalSynced = count($cards);

        $this->syncReport = [
            'show' => true,
            'total' => $totalSynced,
            'added' => $addedCount,
            'moved' => $movedCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'unchanged' => $unchangedCount,
            'timestamp' => now()->format('d M, Y - h:i A'),
            'changes' => $changesList,
        ];

        $this->syncLog[] = "[$timestamp] 🎉 Sincronización exitosa: {$totalSynced} tarjetas procesadas ({$addedCount} nuevas, {$movedCount} movidas, {$updatedCount} actualizadas, {$deletedCount} archivadas).";
        session()->flash('message', "Sincronización con Trello completada exitosamente ({$totalSynced} tarjetas procesadas).");
    }

    public function render()
    {
        return view('livewire.settings.trello-sync')
            ->layout('components.layouts.app', ['title' => 'Configuración de Trello Sync - Kudos Design Ops']);
    }
}
