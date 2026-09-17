<?php

namespace App\Console\Commands;

use App\Services\TrelloSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTrelloBoard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trello:sync {--board= : Optional Trello Board ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run periodic background synchronization with Trello board cards and statuses';

    /**
     * Execute the console command.
     */
    public function handle(TrelloSyncService $syncService): int
    {
        if ($syncService->isPaused()) {
            $this->warn('Sincronización con Trello omitida: La sincronización global está pausada.');
            Log::info('Scheduled Trello sync skipped: Sync is globally paused.');

            return self::SUCCESS;
        }

        $boardInput = $this->option('board') ?: config('services.trello.board_id', env('TRELLO_BOARD_ID', '597266b10db2cbf2568cda54'));
        $apiKey = config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f'));
        $apiToken = config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET')));

        if (empty($apiKey) || empty($apiToken)) {
            $this->error('Error: API Key o API Token de Trello no configurados.');

            return self::FAILURE;
        }

        $boardId = $syncService->extractBoardId($boardInput);
        $this->info("Iniciando sincronización periódica con Trello (Board ID: {$boardId})...");

        $listsRes = $syncService->getBoardLists($boardId, $apiKey, $apiToken);
        if (! $listsRes['success']) {
            $this->error("Error al obtener listas de Trello: {$listsRes['error']}");
            Log::error("Scheduled Trello sync failed fetching lists: {$listsRes['error']}");

            return self::FAILURE;
        }

        $listsMap = [];
        foreach ($listsRes['data'] as $list) {
            $listsMap[$list['id']] = $list['name'];
        }

        $cardsRes = $syncService->getBoardCards($boardId, $apiKey, $apiToken);
        if (! $cardsRes['success']) {
            $this->error("Error al obtener tarjetas de Trello: {$cardsRes['error']}");
            Log::error("Scheduled Trello sync failed fetching cards: {$cardsRes['error']}");

            return self::FAILURE;
        }

        $cards = $cardsRes['data'];
        $incomingCardIds = array_column($cards, 'id');

        $metrics = [
            'total' => count($cards),
            'created' => 0,
            'moved' => 0,
            'pushed_to_trello' => 0,
            'updated' => 0,
            'conflict' => 0,
            'unchanged' => 0,
        ];

        foreach ($cards as $card) {
            $res = $syncService->syncCardToOrder($card, $listsMap, $boardId, $apiKey, $apiToken);
            if ($res && isset($res['action'])) {
                if (isset($metrics[$res['action']])) {
                    $metrics[$res['action']]++;
                }
            }
        }

        $missingRes = $syncService->handleMissingOrders($incomingCardIds);
        $metrics['missing_handled'] = $missingRes['count'];

        $summaryMsg = sprintf(
            'Sincronización Trello completada: %d total (%d creadas, %d movidas, %d empujadas, %d actualizadas, %d conflictos, %d sin cambios, %d faltantes).',
            $metrics['total'],
            $metrics['created'],
            $metrics['moved'],
            $metrics['pushed_to_trello'],
            $metrics['updated'],
            $metrics['conflict'],
            $metrics['unchanged'],
            $metrics['missing_handled']
        );

        $this->info($summaryMsg);
        Log::info('Scheduled Trello sync finished: '.$summaryMsg);

        return self::SUCCESS;
    }
}
