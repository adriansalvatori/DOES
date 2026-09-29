<?php

namespace App\Console\Commands;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncOverviewExcelData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-overview-excel 
                            {--file=docs/migration test 1.xlsx : Path to the Excel file}
                            {--dry-run : Simulate mapping without writing to database}
                            {--force : Force execution without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs Overview data from Excel file into existing database orders matching by WO number';

    public function handle(): int
    {
        $filePath = (string) $this->option('file');
        $isDryRun = (bool) $this->option('dry-run');

        if (! file_exists($filePath)) {
            $this->error("El archivo no existe: {$filePath}");

            return Command::FAILURE;
        }

        $this->info("Iniciando extracción de datos desde: {$filePath}");
        $this->info($isDryRun ? '== MODO SIMULACIÓN (DRY RUN) ACTIVADO ==' : '== MODO APLICACIÓN REAL ==');

        $scriptPath = base_path('storage/app/extract_excel_overview.py');
        if (! file_exists($scriptPath)) {
            $this->error("Script extractor no encontrado en {$scriptPath}");

            return Command::FAILURE;
        }

        $process = Process::timeout(120)->run(['python3', $scriptPath, base_path($filePath)]);

        if (! $process->successful()) {
            $this->error('Error al extraer datos con Python: '.$process->errorOutput());

            return Command::FAILURE;
        }

        $rows = json_decode($process->output(), true);
        if (! is_array($rows)) {
            $this->error('No se pudo decodificar el JSON devuelto por el extractor.');

            return Command::FAILURE;
        }

        $totalRows = count($rows);
        $this->info("Total de filas leídas con WO válida: {$totalRows}");

        $grayColors = ['FFB7B7B7', 'FFCCCCCC', 'FFD9D9D9', 'FFEFEFEF', 'FF999999', 'FF666666'];

        $stats = [
            'total_excel_rows' => $totalRows,
            'matched_db_orders' => 0,
            'unmatched_excel_rows' => 0,
            'archived_canceled_red' => 0,
            'archived_gray' => 0,
            'ticket_lavender' => 0,
            'revised_cs_magenta' => 0,
            'revised_camila_yellow' => 0,
            'email_dates_parsed' => 0,
            'email_notes_appended' => 0,
            'installation_assigned' => 0,
            'check_true' => 0,
            'check_red_alert' => 0,
            'delivery_notes_set' => 0,
            'production_notes_set' => 0,
        ];

        $samplePreviews = [];

        foreach ($rows as $item) {
            $rawWo = trim((string) $item['wo']);
            if (empty($rawWo)) {
                $stats['unmatched_excel_rows']++;

                continue;
            }

            // Search order in database
            $order = Order::where('wo_number', 'WO '.$rawWo)
                ->orWhere('wo_number', $rawWo)
                ->first();

            if (! $order) {
                $stats['unmatched_excel_rows']++;

                continue;
            }

            $stats['matched_db_orders']++;

            $estColor = $item['est_color'];
            $nipColor = $item['nip_color'];
            $emailColor = $item['email_color'];
            $instColor = $item['inst_color'];
            $chkColor = $item['chk_color'];
            $rowColors = $item['row_colors'] ?? [];

            $updates = [];
            $flags = is_array($order->flags) ? $order->flags : [];

            // 1. Color coding checks:
            // Check Red (Intense Red):
            $isRedIntense = ($estColor === 'FFFF0000')
                || ($nipColor === 'FFFF0000' && in_array('FFFF0000', $rowColors, true) && count(array_filter($rowColors, fn ($c) => $c === 'FFFF0000')) >= 3);

            // Check Gray (any shade):
            $isGray = in_array($estColor, $grayColors, true)
                || (in_array($nipColor, $grayColors, true) && count(array_intersect($rowColors, $grayColors)) >= 3);

            // Check Lavender:
            $isLavender = ($estColor === 'FFEAD1DC')
                || ($emailColor === 'FFEAD1DC')
                || in_array('FFEAD1DC', $rowColors, true);

            if ($isRedIntense) {
                $updates['core_status'] = CoreStatus::ARCHIVED;
                $updates['substatus'] = Substatus::CANCELADA;
                $updates['archived_at'] = $order->archived_at ?: now();
                $stats['archived_canceled_red']++;
            } elseif ($isGray) {
                $updates['core_status'] = CoreStatus::ARCHIVED;
                $updates['substatus'] = Substatus::NO_REALIZADA_TRANSFERIDA;
                $updates['archived_at'] = $order->archived_at ?: now();
                $stats['archived_gray']++;
            }

            if ($isLavender) {
                $updates['substatus'] = Substatus::TICKET;
                if (! in_array('TICKET', $flags, true)) {
                    $flags[] = 'TICKET';
                }
                $stats['ticket_lavender']++;
            }

            // 2. Production note
            $prodNote = $item['nip_val'] ? trim((string) $item['nip_val']) : '';

            // 3. Email Date & Depuration
            $emailRaw = $item['email_val'] ? trim((string) $item['email_val']) : '';
            $parsedEmailDate = null;

            if (! empty($emailRaw)) {
                // Match dates in MM/DD/YYYY, MM/DD/YY or YYYY-MM-DD
                if (preg_match('/(?:email\s*)?(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/i', $emailRaw, $matches)) {
                    $m = (int) $matches[1];
                    $d = (int) $matches[2];
                    $y = (int) $matches[3];
                    if ($y < 100) {
                        $y += 2000;
                    }
                    if (checkdate($m, $d, $y)) {
                        $parsedEmailDate = sprintf('%04d-%02d-%02d', $y, $m, $d);
                        $stats['email_dates_parsed']++;
                    }
                }

                // If not parsed as date, it's an observation note
                if (! $parsedEmailDate) {
                    $notePrefix = "[Email: {$emailRaw}]";
                    if (! empty($prodNote)) {
                        $prodNote .= ' '.$notePrefix;
                    } else {
                        $prodNote = $notePrefix;
                    }
                    $stats['email_notes_appended']++;
                }
            }

            $updates['email_date'] = $parsedEmailDate;

            if (! empty($prodNote)) {
                $updates['production_note'] = $prodNote;
                $stats['production_notes_set']++;
            }

            // 4. Estimate / Invoice & Review Status
            $estVal = $item['est_val'] ? trim((string) $item['est_val']) : '';
            if ($estColor === 'FFFF00FF') {
                $updates['review_status'] = 'CS';
                $stats['revised_cs_magenta']++;
            } elseif ($estColor === 'FFFFFF00') {
                $updates['review_status'] = 'CAMILA';
                $stats['revised_camila_yellow']++;
            } else {
                if (! isset($updates['core_status'])) {
                    $updates['review_status'] = null;
                }
            }

            if (! empty($estVal)) {
                // Clean revised prefix: 'REVISED - 10768' -> '10768'
                $cleanEst = preg_replace('/^REVISED\s*[-–]?\s*/i', '', $estVal);
                $cleanEst = trim($cleanEst);
                $updates['estimate_invoice_number'] = $cleanEst;
            }

            // 5. Installation
            $instVal = $item['inst_val'] ? trim((string) $item['inst_val']) : '';
            if ($instColor === 'FF00FF00' && stripos($instVal, 'KUDOS') !== false) {
                $updates['installation_type'] = 'Kudos (Entregado)';
                $stats['installation_assigned']++;
            } elseif (stripos($instVal, 'KUDOS') !== false) {
                $updates['installation_type'] = 'Kudos';
                $stats['installation_assigned']++;
            } elseif (stripos($instVal, 'ELIMINAR') !== false || $instColor === 'FFFF0000') {
                $updates['installation_type'] = 'Debe';
                $stats['installation_assigned']++;
            }

            // 6. Check
            if ($item['chk_val'] === true) {
                $updates['overview_checked'] = true;
                $stats['check_true']++;
                if ($chkColor === 'FFFF0000') {
                    if (! in_array('check_red_alert', $flags, true)) {
                        $flags[] = 'check_red_alert';
                    }
                    $stats['check_red_alert']++;
                }
            } else {
                $updates['overview_checked'] = false;
            }

            // 7. Delivery note
            $ndeVal = $item['nde_val'] ? trim((string) $item['nde_val']) : '';
            if (! empty($ndeVal)) {
                $updates['delivery_note'] = $ndeVal;
                $stats['delivery_notes_set']++;
            }

            $updates['flags'] = $flags;

            // Collect samples
            if (count($samplePreviews) < 8) {
                $samplePreviews[] = [
                    'WO' => $order->wo_number,
                    'Cliente' => substr((string) $order->company_name, 0, 20),
                    'CoreStatus' => ($updates['core_status'] ?? $order->core_status)?->value ?? '—',
                    'Substatus' => ($updates['substatus'] ?? $order->substatus)?->value ?? '—',
                    'RevStatus' => $updates['review_status'] ?? '—',
                    'Est/Inv' => $updates['estimate_invoice_number'] ?? '—',
                    'EmailDate' => $updates['email_date'] ?? '—',
                    'Inst' => $updates['installation_type'] ?? '—',
                    'Checked' => ($updates['overview_checked'] ?? false) ? 'YES' : 'NO',
                ];
            }

            // Execute update if not dry run
            if (! $isDryRun) {
                $order->update($updates);
            }
        }

        $this->newLine();
        $this->table(
            ['Métrica de Sincronización', 'Total'],
            [
                ['Filas leídas en Excel con WO', $stats['total_excel_rows']],
                ['Órdenes encontradas en BD', $stats['matched_db_orders']],
                ['Filas de Excel no encontradas en BD', $stats['unmatched_excel_rows']],
                ['Archivadas / Canceladas (Rojo)', $stats['archived_canceled_red']],
                ['Archivadas (Gris - No realizada / Transferida)', $stats['archived_gray']],
                ['Subestatus Ticket (Lavanda)', $stats['ticket_lavender']],
                ['Revised CS (Magenta)', $stats['revised_cs_magenta']],
                ['Revised Camila (Amarillo)', $stats['revised_camila_yellow']],
                ['Fechas de Email depuradas y asignadas', $stats['email_dates_parsed']],
                ['Notas textuales de Email preservadas en Notas', $stats['email_notes_appended']],
                ['Tipos de Instalación asignados', $stats['installation_assigned']],
                ['Check en True asignados', $stats['check_true']],
                ['Check con alerta roja asignados', $stats['check_red_alert']],
                ['Notas de Entrega asignadas', $stats['delivery_notes_set']],
                ['Notas de Producción asignadas', $stats['production_notes_set']],
            ]
        );

        $this->newLine();
        $this->info('Muestra de 8 órdenes procesadas:');
        $this->table(
            ['WO', 'Cliente', 'CoreStatus', 'Substatus', 'RevStatus', 'Est/Inv', 'EmailDate', 'Inst', 'Checked'],
            $samplePreviews
        );

        if ($isDryRun) {
            $this->newLine();
            $this->warn('Simulación finalizada con éxito. Ningún dato fue modificado en la base de datos.');
            $this->info('Para aplicar los cambios reales, ejecuta el comando sin la opción --dry-run');
        } else {
            $this->newLine();
            $this->info('¡Sincronización completada exitosamente en la base de datos!');
        }

        return Command::SUCCESS;
    }
}
