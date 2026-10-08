<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Setting;
use App\Services\TrelloSyncService;
use Illuminate\Console\Command;

class CleanupDuplicateTrelloOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trello:cleanup-duplicates 
                            {--dry-run : Solo muestra los duplicados detectados sin realizar cambios en la base de datos}
                            {--board= : ID opcional de tablero de Trello}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta y limpia órdenes duplicadas en DOES originadas por discrepancia entre ID canónico y shortLink de Trello';

    /**
     * Execute the console command.
     */
    public function handle(TrelloSyncService $syncService): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun
            ? '🔍 [MODO SIMULACIÓN] Buscando órdenes duplicadas de Trello...'
            : '🧹 Iniciando limpieza de órdenes duplicadas de Trello...');

        $boardInput = $this->option('board') ?: Setting::get('trello_board_id', config('services.trello.board_id', env('TRELLO_BOARD_ID', '')));
        $apiKey = Setting::get('trello_api_key', config('services.trello.api_key', env('TRELLO_API_KEY', '0771bd12b868f2ee8e1a72f424085b5f')));
        $apiToken = Setting::get('trello_user_token', config('services.trello.token', env('TRELLO_USER_TOKEN', env('TRELLO_API_SECRET'))));

        $cards = [];
        if (! empty($boardInput) && ! empty($apiKey) && ! empty($apiToken)) {
            $boardId = $syncService->extractBoardId($boardInput);
            $cardsRes = $syncService->getBoardCards($boardId, $apiKey, $apiToken);
            if ($cardsRes['success']) {
                $cards = $cardsRes['data'];
                $this->line('Conectado con Trello: Se obtuvieron '.count($cards).' tarjetas del tablero.');
            } else {
                $this->warn("Aviso: No se pudo conectar a Trello ({$cardsRes['error']}). Procediendo con deduplicación local.");
            }
        }

        $duplicatesFound = 0;
        $duplicatesResolved = 0;

        // --- Caso 1: Deduplicación por shortLink vs ID Canónico (usando datos del tablero de Trello) ---
        if (! empty($cards)) {
            foreach ($cards as $card) {
                $canonicalId = $card['id'] ?? null;
                $shortLink = $card['shortLink'] ?? null;

                if (! $canonicalId || ! $shortLink) {
                    continue;
                }

                // Buscar orden que tenga el ID canónico
                $canonicalOrders = Order::where('trello_card_id', $canonicalId)->get();

                // Buscar orden que tenga el shortLink (o URL conteniéndolo)
                $shortOrders = Order::where(function ($q) use ($shortLink) {
                    $q->where('trello_card_id', $shortLink)
                        ->orWhere('trello_card_id', 'like', "%{$shortLink}%");
                })->where('trello_card_id', '!=', $canonicalId)->get();

                if ($canonicalOrders->isNotEmpty() && $shortOrders->isNotEmpty()) {
                    $duplicatesFound++;

                    // La orden original suele ser la que está en el workspace o creada manualmente
                    $originalOrder = $shortOrders->first();
                    // El duplicado suele ser la orden recién importada en el Buzón de Backlog
                    $duplicateOrder = $canonicalOrders->first();

                    $this->warn("Duplicado detectado para tarjeta '{$card['name']}':");
                    $this->line("  • Orden original (#{$originalOrder->id}): {$originalOrder->company_name} - {$originalOrder->task_name} (ID Trello: {$originalOrder->trello_card_id})");
                    $this->line("  • Orden duplicada (#{$duplicateOrder->id}): {$duplicateOrder->company_name} - {$duplicateOrder->task_name} (ID Trello: {$duplicateOrder->trello_card_id})");

                    if (! $isDryRun) {
                        // Actualizar la orden original con el ID canónico
                        $originalOrder->update(['trello_card_id' => $canonicalId]);
                        // Eliminar permanentemente la orden duplicada importada por el sync
                        $duplicateOrder->forceDelete();
                        $duplicatesResolved++;
                        $this->info("  ✓ Resuelto: Orden #{$originalOrder->id} normalizada con ID canónico y duplicado #{$duplicateOrder->id} eliminado.\n");
                    } else {
                        $this->line("  [Simulación] Se mantendría #{$originalOrder->id} y se eliminaría #{$duplicateOrder->id}.\n");
                    }
                }
            }
        }

        // --- Caso 2: Órdenes con exactamente el mismo trello_card_id en la base de datos local ---
        $repeatedTrelloIds = Order::whereNotNull('trello_card_id')
            ->where('trello_card_id', '!=', '')
            ->select('trello_card_id')
            ->groupBy('trello_card_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('trello_card_id');

        foreach ($repeatedTrelloIds as $repId) {
            $matchingOrders = Order::where('trello_card_id', $repId)->orderBy('created_at', 'asc')->get();
            if ($matchingOrders->count() > 1) {
                // Conservar la orden que esté en workspace o la más antigua
                $keepOrder = $matchingOrders->firstWhere('in_workspace', true) ?: $matchingOrders->first();
                $toDelete = $matchingOrders->where('id', '!=', $keepOrder->id);

                foreach ($toDelete as $del) {
                    $duplicatesFound++;
                    $this->warn("Duplicado exacto detectado para ID Trello '{$repId}':");
                    $this->line("  • Conservar (#{$keepOrder->id}): {$keepOrder->company_name} - {$keepOrder->task_name}");
                    $this->line("  • Eliminar (#{$del->id}): {$del->company_name} - {$del->task_name}");

                    if (! $isDryRun) {
                        $del->forceDelete();
                        $duplicatesResolved++;
                        $this->info("  ✓ Eliminada orden duplicada #{$del->id}.\n");
                    } else {
                        $this->line("  [Simulación] Se eliminaría #{$del->id}.\n");
                    }
                }
            }
        }

        if ($duplicatesFound === 0) {
            $this->info('✨ No se encontraron órdenes duplicadas. Todo está en orden.');
        } else {
            if ($isDryRun) {
                $this->warn("Simulación terminada: Se detectaron {$duplicatesFound} órdenes duplicadas. Ejecuta sin '--dry-run' para aplicar los cambios.");
            } else {
                $this->info("🎉 Limpieza completada: Se resolvieron {$duplicatesResolved} órdenes duplicadas.");
            }
        }

        return self::SUCCESS;
    }
}
