<?php

namespace App\Livewire\Settings;

use App\Models\Client;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\ClientMatchingService;
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
        $success = false;

        if ($order) {
            // Trim and sanitize company_name and task_name
            $comp = trim($order->company_name);
            $task = trim($order->task_name);

            // Strip redundant company_name prefix or trailing parenthesis matching responsible_person
            if (! empty($comp) && ! empty($task) && str_starts_with(mb_strtolower($task, 'UTF-8'), mb_strtolower($comp, 'UTF-8'))) {
                $task = trim(mb_substr($task, mb_strlen($comp, 'UTF-8'), null, 'UTF-8'), " \t\n\r\0\x0B-:");
            }

            // Strip parenthesized contact tags e.g. (ENTRO POR VALENTINA) from task_name if present
            if (preg_match('/\s*\(\s*([^)]+)\s*\)\s*/', $task, $matches)) {
                $parenContent = trim($matches[1]);
                $task = trim(preg_replace('/\s*\(\s*[^)]+\s*\)\s*/', ' ', $task), " \t\n\r\0\x0B-:");
                if (empty($order->responsible_person) && ! empty($parenContent)) {
                    $order->responsible_person = mb_strtoupper($parenContent, 'UTF-8');
                }
            }

            $order->update([
                'company_name' => $comp,
                'task_name' => $task,
                'wo_number' => trim($order->wo_number ?? ''),
                'responsible_person' => $order->responsible_person,
            ]);

            $pushedTitle = OrderTitleParserService::buildTitle($order);
            $success = app(TrelloSyncService::class)->updateCardOnTrello($order, $this->apiKey, $this->userToken, $this->boardId);

            if ($success) {
                $order->update(['trello_title' => $pushedTitle]);
            } else {
                session()->flash('error', '⚠️ No se pudo actualizar en Trello. Verifica tu conexión o token y vuelve a intentarlo.');
            }
        }

        foreach ($this->syncReport['changes'] as &$change) {
            if (isset($change['order_id']) && $change['order_id'] == $orderId) {
                if ($success) {
                    $change['action'] = 'pushed_to_trello';
                    $change['pushed_title'] = $pushedTitle;
                    $change['details'] = ['Enviado a Trello con formato limpio'];
                    $change['push_error'] = false;
                } else {
                    $change['push_error'] = true;
                    $change['details'] = ['⚠️ Error al enviar a Trello. Puedes reintentar.'];
                    if ($this->selectedConflict && ($this->selectedConflict['order_id'] ?? null) == $orderId) {
                        $this->selectedConflict['push_error'] = true;
                        $this->selectedConflict['details'] = ['⚠️ Error al enviar a Trello. Puedes reintentar.'];
                    }
                }
                break;
            }
        }

        if ($success) {
            $this->syncReport['conflicts'] = max(0, $this->syncReport['conflicts'] - 1);
            $this->syncReport['pushed'] = ($this->syncReport['pushed'] ?? 0) + 1;
            $this->selectedConflict = null;
            session()->flash('message', 'Conflicto resuelto. Se enviaron los datos de Workspace a Trello.');
        }
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
                    $rawComp = trim($trelloData['company_name']);
                    $updateData['company_name'] = $rawComp;

                    $clientMatch = Client::all()->first(fn ($c) => $c->matchesNameOrAlias($rawComp));
                    if ($clientMatch) {
                        $updateData['client_id'] = $clientMatch->id;
                        $updateData['company_name'] = $clientMatch->name;
                    } else {
                        $match = app(ClientMatchingService::class)->matchOrCreate(
                            $rawComp,
                            $order->responsible_person,
                            createIfMissing: false
                        );
                        if ($match['client']) {
                            $updateData['client_id'] = $match['client']->id;
                            $updateData['company_name'] = $match['client']->name;
                        }
                        if ($match['location']) {
                            $updateData['client_location_id'] = $match['location']->id;
                        }
                    }
                }
                if (! empty($trelloData['task_name'])) {
                    $updateData['task_name'] = trim($trelloData['task_name']);
                }
                if (! empty($trelloData['wo_number'])) {
                    $updateData['wo_number'] = trim($trelloData['wo_number']);
                }
                if (! empty($trelloData['trello_title'])) {
                    $updateData['trello_title'] = trim($trelloData['trello_title']);
                }
                if (! empty($trelloData['responsible_person']) && $trelloData['responsible_person'] !== 'Sin contacto') {
                    $updateData['responsible_person'] = trim($trelloData['responsible_person']);
                }
                if (array_key_exists('designer_id', $trelloData)) {
                    $updateData['designer_id'] = $trelloData['designer_id'];
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

        // Calculate deleted/archived on Trello (Mark as missing or complete if in production)
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
