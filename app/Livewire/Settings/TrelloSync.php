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
        session()->flash('message', "Datos de prueba eliminados correctamente.");
    }

    public function runTrelloSync()
    {
        if (empty(trim($this->boardId))) {
            session()->flash('error', "Por favor ingresa un ID o URL de Tablero Trello válido.");
            return;
        }

        $syncService = app(TrelloSyncService::class);
        $extractedBoardId = $syncService->extractBoardId($this->boardId);
        $timestamp = now()->format('H:i:s');

        $this->syncLog[] = "[$timestamp] Conectando a Trello API con Board ID/Link: {$extractedBoardId}...";

        // Fetch lists
        $listsRes = $syncService->getBoardLists($extractedBoardId, $this->apiKey, $this->userToken);
        if (!$listsRes['success']) {
            $this->syncLog[] = "[$timestamp] ❌ Error Trello API (Listas) - Status {$listsRes['status']}: {$listsRes['error']}";
            if ($listsRes['status'] == 401) {
                $this->syncLog[] = "[$timestamp] 💡 NOTA DE AUTENTICACIÓN: Tableros privados de Trello requieren un User Token. Haz clic en 'Generar Token de Usuario Trello' abajo e ingrésalo aquí.";
            } elseif ($listsRes['status'] == 404) {
                $this->syncLog[] = "[$timestamp] ⚠️ Tablero no encontrado. Revisa la URL o ID del tablero Trello.";
            }
            session()->flash('error', "No se pudo sincronizar con Trello. Revisa el log de consola.");
            return;
        }

        $lists = $listsRes['data'];
        $listsMap = [];
        foreach ($lists as $list) {
            $listsMap[$list['id']] = $list['name'];
        }
        $this->syncLog[] = "[$timestamp] ✓ " . count($lists) . " listas de Trello obtenidas (" . implode(', ', array_slice(array_values($listsMap), 0, 4)) . "...).";

        // Fetch cards
        $cardsRes = $syncService->getBoardCards($extractedBoardId, $this->apiKey, $this->userToken);
        if (!$cardsRes['success']) {
            $this->syncLog[] = "[$timestamp] ❌ Error Trello API (Tarjetas) - Status {$cardsRes['status']}: {$cardsRes['error']}";
            session()->flash('error', "Error al obtener tarjetas de Trello.");
            return;
        }

        $cards = $cardsRes['data'];
        $syncedCount = 0;
        foreach ($cards as $card) {
            $syncService->syncCardToOrder($card, $listsMap);
            $syncedCount++;
        }

        $this->syncLog[] = "[$timestamp] 🎉 Sincronización exitosa: $syncedCount tarjetas importadas/actualizadas en Kudos Design Ops.";
        session()->flash('message', "Sincronización con Trello completada exitosamente ($syncedCount tarjetas).");
    }

    public function render()
    {
        return view('livewire.settings.trello-sync')
            ->layout('components.layouts.app', ['title' => 'Configuración de Trello Sync - Kudos Design Ops']);
    }
}
