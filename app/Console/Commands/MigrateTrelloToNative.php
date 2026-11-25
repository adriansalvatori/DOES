<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\GoogleDriveService;
use App\Services\TrelloNativeMigrator;
use App\Support\TrelloCutover;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateTrelloToNative extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trello:migrate-to-native
        {--order=* : Only migrate these order ids}
        {--limit= : Stop after this many orders (for running in batches)}
        {--only-pending : Skip orders that were already migrated once}
        {--without-files : Import descriptions, comments and links only (no Drive uploads)}
        {--dry-run : Read the board and report what would be imported, without writing anything}
        {--force : Run even though the cutover is already marked complete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Trello descriptions, comments and attachments (to Google Drive) for every order in the Overview';

    /**
     * Execute the console command.
     */
    public function handle(TrelloNativeMigrator $migrator, GoogleDriveService $drive): int
    {
        if (TrelloCutover::isComplete() && ! $this->option('force')) {
            $this->error('El corte de Trello ya está completo; volver a importar sobrescribiría datos nativos. Usa --force si de verdad lo necesitas.');

            return self::FAILURE;
        }

        $withFiles = ! $this->option('without-files');
        if ($withFiles && ! $this->option('dry-run') && ! $drive->isConfigured()) {
            $this->error('Google Drive no está listo: revisa GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET / GOOGLE_DRIVE_ROOT_FOLDER_ID y conecta la cuenta en Ajustes → Google Drive. Usa --without-files para importar solo texto.');

            return self::FAILURE;
        }

        $this->info('Leyendo el tablero de Trello (tarjetas, comentarios, miembros)...');
        $migrator->loadBoard();

        $query = $migrator->scopedOrders();
        if ($ids = array_filter((array) $this->option('order'))) {
            $query->whereIn('id', $ids);
        }
        if ($this->option('only-pending')) {
            $query->whereNull('trello_migrated_at');
        }
        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $orders = $query->get();

        if ($this->option('dry-run')) {
            return $this->reportDryRun($migrator, $orders->all());
        }

        $totals = ['comments' => 0, 'comments_removed' => 0, 'files' => 0, 'links' => 0, 'files_pending' => 0, 'media_removed' => 0, 'description' => 0];
        $statuses = [];
        $problems = [];

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            try {
                $result = $migrator->migrateOrder($order, $withFiles);
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'errors' => [$e->getMessage()]];
                Log::error("trello:migrate-to-native failed for order #{$order->id}: ".$e->getMessage());
            }

            $statuses[$result['status']] = ($statuses[$result['status']] ?? 0) + 1;
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (int) ($result[$key] ?? 0);
            }
            foreach ($result['errors'] ?? [] as $error) {
                $problems[] = ["#{$order->id}", (string) $order->wo_number, $error];
            }
            if (in_array($result['status'], [TrelloNativeMigrator::STATUS_CARD_NOT_FOUND, TrelloNativeMigrator::STATUS_DUPLICATE_CARD], true)) {
                $problems[] = ["#{$order->id}", (string) $order->wo_number, $result['status']];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Estado', 'Órdenes'], collect($statuses)->map(fn ($count, $status) => [$status, $count])->values()->all());
        $this->table(['Importado', 'Cantidad'], [
            ['Descripciones', $totals['description']],
            ['Comentarios', $totals['comments']],
            ['Comentarios eliminados (borrados en Trello)', $totals['comments_removed']],
            ['Archivos subidos a Drive', $totals['files']],
            ['Enlaces', $totals['links']],
            ['Archivos pendientes (--without-files)', $totals['files_pending']],
            ['Archivos retirados (borrados en Trello)', $totals['media_removed']],
        ]);

        if ($problems !== []) {
            $this->warn(count($problems).' incidencias (se pueden reintentar volviendo a ejecutar el comando):');
            $this->table(['Orden', 'WO', 'Detalle'], $problems);
        }

        return ($statuses['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<Order>  $orders
     */
    protected function reportDryRun(TrelloNativeMigrator $migrator, array $orders): int
    {
        $cards = 0;
        $files = 0;
        $bytes = 0;
        $links = 0;
        $missing = 0;
        $duplicates = 0;

        foreach ($orders as $order) {
            $card = $migrator->cardFor($order);
            if ($card === null) {
                $missing++;

                continue;
            }
            if (! $migrator->ownsCard($order, $card)) {
                $duplicates++;

                continue;
            }

            $cards++;
            foreach ($card['attachments'] ?? [] as $attachment) {
                if (! empty($attachment['isUpload'])) {
                    $files++;
                    $bytes += (int) ($attachment['bytes'] ?? 0);
                } else {
                    $links++;
                }
            }
        }

        $this->table(['Simulación', 'Cantidad'], [
            ['Órdenes en alcance', count($orders)],
            ['Con tarjeta en el tablero', $cards],
            ['Tarjeta no encontrada', $missing],
            ['Tarjeta compartida (se importa en la orden más antigua)', $duplicates],
            ['Archivos', $files],
            ['Tamaño (GB)', round($bytes / 1073741824, 2)],
            ['Enlaces', $links],
        ]);

        return self::SUCCESS;
    }
}
