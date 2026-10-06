<?php

namespace App\Services;

use App\Models\Designer;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderReconciliationService
{
    protected string $storageDir;

    protected string $historyFile;

    public function __construct()
    {
        $this->storageDir = storage_path('app/reconciliation');
        $this->historyFile = $this->storageDir.'/migration_history.json';
        File::ensureDirectoryExists($this->storageDir);
    }

    /**
     * Parse and analyze a CSV file against existing database orders.
     *
     * @return array{meta: array, full_matches: array, partial_matches: array, unmatched: array, anomalies: array}
     */
    public function analyzeCsv(string $filePath, float $similarityThreshold = 0.75): array
    {
        if (! File::exists($filePath)) {
            throw new \InvalidArgumentException("El archivo CSV no existe en la ruta: {$filePath}");
        }

        $rows = $this->parseCsvFile($filePath);
        if (empty($rows)) {
            return [
                'meta' => [
                    'file_name' => basename($filePath),
                    'total_rows' => 0,
                    'full_match_count' => 0,
                    'partial_match_count' => 0,
                    'unmatched_count' => 0,
                    'anomalies_count' => 0,
                    'analyzed_at' => now()->toIso8601String(),
                ],
                'full_matches' => [],
                'partial_matches' => [],
                'unmatched' => [],
                'anomalies' => [],
            ];
        }

        // Cache existing DB orders for fast in-memory matching
        $dbOrders = Order::query()
            ->select([
                'id',
                'wo_number',
                'company_name',
                'task_name',
                'designer_id',
                'core_status',
                'substatus',
                'delivery_due_date',
                'production_processed_at',
                'email_date',
                'production_note',
                'delivery_note',
                'estimate_invoice_number',
                'review_status',
                'installation_type',
                'overview_checked',
                'trello_card_id',
                'in_workspace',
            ])
            ->get();

        // Build lookup map by normalized WO number
        $ordersByWo = [];
        $ordersWithoutWo = [];

        foreach ($dbOrders as $order) {
            $normWo = $this->normalizeWo($order->wo_number);
            if (! empty($normWo)) {
                $ordersByWo[$normWo] = $order;
            } else {
                $ordersWithoutWo[] = $order;
            }
        }

        // Cache available substatuses
        $dbSubstatuses = Substatus::pluck('name', 'id')->toArray();

        // Cache designers map for designers table
        $designers = Designer::all();
        $designerMap = [];
        foreach ($designers as $d) {
            $designerMap[Str::lower(trim($d->name))] = $d->id;
            $designerMap[$d->slug] = $d->id;
        }

        $fullMatches = [];
        $partialMatches = [];
        $unmatched = [];
        $anomalies = [];

        foreach ($rows as $index => $row) {
            $rowId = 'row_'.($index + 1);
            $parsedRow = $this->sanitizeAndMapRow($row, $designerMap, $dbSubstatuses);

            if (! empty($parsedRow['date_anomalies'])) {
                foreach ($parsedRow['date_anomalies'] as $da) {
                    $anomalies[] = [
                        'row_id' => $rowId,
                        'wo' => $parsedRow['raw_wo'],
                        'company' => $parsedRow['company_name'],
                        'column' => $da['column'],
                        'raw_value' => $da['raw_value'],
                        'note_appended' => $da['note_appended'],
                    ];
                }
            }

            $csvWo = $parsedRow['normalized_wo'];
            $csvCompany = $parsedRow['company_name'];
            $csvTask = $parsedRow['task_name'];

            // 1. Try matching by WO number
            if (! empty($csvWo) && isset($ordersByWo[$csvWo])) {
                $matchedOrder = $ordersByWo[$csvWo];
                $simScore = $this->calculateCombinedSimilarity(
                    $csvCompany,
                    $csvTask,
                    $matchedOrder->company_name,
                    $matchedOrder->task_name
                );

                $diffs = $this->calculateOrderDiffs($matchedOrder, $parsedRow);

                if ($simScore >= $similarityThreshold) {
                    // Full match
                    $fullMatches[] = [
                        'row_id' => $rowId,
                        'order_id' => $matchedOrder->id,
                        'wo_number' => $matchedOrder->wo_number ?? ('WO '.$csvWo),
                        'db_company' => $matchedOrder->company_name,
                        'db_task' => $matchedOrder->task_name,
                        'csv_company' => $csvCompany,
                        'csv_task' => $csvTask,
                        'similarity' => round($simScore * 100, 1),
                        'parsed_data' => $parsedRow,
                        'diffs' => $diffs,
                        'has_changes' => count($diffs) > 0,
                    ];
                } else {
                    // WO matches but names differ
                    $partialMatches[] = [
                        'row_id' => $rowId,
                        'order_id' => $matchedOrder->id,
                        'wo_number' => $matchedOrder->wo_number ?? ('WO '.$csvWo),
                        'db_company' => $matchedOrder->company_name,
                        'db_task' => $matchedOrder->task_name,
                        'csv_company' => $csvCompany,
                        'csv_task' => $csvTask,
                        'similarity' => round($simScore * 100, 1),
                        'reason' => "Mismo WO ({$csvWo}), pero la similitud de Empresa y Tarea es de ".round($simScore * 100).'%',
                        'match_type' => 'wo_name_divergence',
                        'parsed_data' => $parsedRow,
                        'diffs' => $diffs,
                    ];
                }

                continue;
            }

            // 2. WO not found or blank in CSV: search by similarity in DB
            $bestCandidate = null;
            $bestScore = 0.0;

            foreach ($dbOrders as $candidateOrder) {
                $score = $this->calculateCombinedSimilarity(
                    $csvCompany,
                    $csvTask,
                    $candidateOrder->company_name,
                    $candidateOrder->task_name
                );

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $candidateOrder;
                }
            }

            if ($bestCandidate && $bestScore >= $similarityThreshold) {
                $diffs = $this->calculateOrderDiffs($bestCandidate, $parsedRow);
                $partialMatches[] = [
                    'row_id' => $rowId,
                    'order_id' => $bestCandidate->id,
                    'wo_number' => $bestCandidate->wo_number ?? 'Sin WO en BD',
                    'db_company' => $bestCandidate->company_name,
                    'db_task' => $bestCandidate->task_name,
                    'csv_company' => $csvCompany,
                    'csv_task' => $csvTask,
                    'similarity' => round($bestScore * 100, 1),
                    'reason' => 'Sin WO exacto en CSV, pero coincide con orden #'.$bestCandidate->id.' ('.($bestCandidate->wo_number ?? 'Sin WO').') al '.round($bestScore * 100).'%',
                    'match_type' => 'fuzzy_names_match',
                    'parsed_data' => $parsedRow,
                    'diffs' => $diffs,
                ];

                continue;
            }

            // 3. Not enough match
            $unmatched[] = [
                'row_id' => $rowId,
                'raw_wo' => $parsedRow['raw_wo'],
                'csv_company' => $csvCompany,
                'csv_task' => $csvTask,
                'parsed_data' => $parsedRow,
                'suggested_trello_link' => null,
            ];
        }

        $result = [
            'meta' => [
                'file_name' => basename($filePath),
                'total_rows' => count($rows),
                'full_match_count' => count($fullMatches),
                'partial_match_count' => count($partialMatches),
                'unmatched_count' => count($unmatched),
                'anomalies_count' => count($anomalies),
                'analyzed_at' => now()->toIso8601String(),
            ],
            'full_matches' => $fullMatches,
            'partial_matches' => $partialMatches,
            'unmatched' => $unmatched,
            'anomalies' => $anomalies,
        ];

        // Store active analysis in session/storage for instant retrieval
        $this->storeAnalysisCache($result);

        return $result;
    }

    /**
     * Parse raw CSV file into associative arrays with header sanitization.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);

            return [];
        }

        // Sanitize headers
        $cleanHeaders = [];
        foreach ($rawHeaders as $idx => $header) {
            $h = trim($header);
            // Remove BOM if present
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            // Lowercase and remove leading dots
            $h = ltrim($h, '.');
            $h = strtolower($h);
            // Clean specific known messy headers
            if (str_starts_with($h, 'company_name')) {
                $h = 'company_name';
            } elseif (str_starts_with($h, 'installation_type')) {
                $h = 'installation_type';
            } elseif (str_starts_with($h, 'production_processed_at')) {
                $h = 'production_processed_at';
            }
            $cleanHeaders[$idx] = trim($h);
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($data, fn ($val) => trim((string) $val) !== ''))) {
                continue; // Skip completely empty rows
            }

            $row = [];
            foreach ($cleanHeaders as $colIdx => $colName) {
                $row[$colName] = isset($data[$colIdx]) ? trim($data[$colIdx]) : null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Sanitize individual row values, parse dates, resolve designers and substatuses.
     */
    public function sanitizeAndMapRow(array $rawRow, array &$designerMap, array $dbSubstatuses): array
    {
        $dateAnomalies = [];

        // 1. Production Processed At
        $rawProdProc = $rawRow['production_processed_at'] ?? null;
        $parsedProdProc = null;
        $prodProcNoteAppend = null;
        if (! empty($rawProdProc)) {
            $dateResult = $this->parseDateOrNote($rawProdProc, 'Fecha producción');
            $parsedProdProc = $dateResult['date'];
            if ($dateResult['note_append']) {
                $prodProcNoteAppend = $dateResult['note_append'];
                $dateAnomalies[] = [
                    'column' => 'production_processed_at',
                    'raw_value' => $rawProdProc,
                    'note_appended' => $prodProcNoteAppend,
                ];
            }
        }

        // 2. Delivery Due Date
        $rawDeliveryDate = $rawRow['delivery_due_date'] ?? null;
        $parsedDeliveryDate = null;
        $deliveryDateNoteAppend = null;
        if (! empty($rawDeliveryDate)) {
            $dateResult = $this->parseDateOrNote($rawDeliveryDate, 'Fecha entrega');
            $parsedDeliveryDate = $dateResult['date'];
            if ($dateResult['note_append']) {
                $deliveryDateNoteAppend = $dateResult['note_append'];
                $dateAnomalies[] = [
                    'column' => 'delivery_due_date',
                    'raw_value' => $rawDeliveryDate,
                    'note_appended' => $deliveryDateNoteAppend,
                ];
            }
        }

        // 3. Email Date
        $rawEmailDate = $rawRow['email_date'] ?? null;
        $parsedEmailDate = null;
        $emailDateNoteAppend = null;
        if (! empty($rawEmailDate)) {
            $dateResult = $this->parseDateOrNote($rawEmailDate, 'Email');
            $parsedEmailDate = $dateResult['date'];
            if ($dateResult['note_append']) {
                $emailDateNoteAppend = $dateResult['note_append'];
                $dateAnomalies[] = [
                    'column' => 'email_date',
                    'raw_value' => $rawEmailDate,
                    'note_appended' => $emailDateNoteAppend,
                ];
            }
        }

        // 4. Production Note
        $productionNote = ! empty($rawRow['production_note']) ? trim($rawRow['production_note']) : null;
        if ($prodProcNoteAppend) {
            $productionNote = $productionNote ? ($productionNote.' '.$prodProcNoteAppend) : $prodProcNoteAppend;
        }

        // 5. Delivery Note
        $deliveryNote = ! empty($rawRow['delivery_note']) ? trim($rawRow['delivery_note']) : null;
        if ($deliveryDateNoteAppend) {
            $deliveryNote = $deliveryNote ? ($deliveryNote.' '.$deliveryDateNoteAppend) : $deliveryDateNoteAppend;
        }

        // 6. Estimate / Invoice & Review Status (CS / CAMILA)
        $rawEst = $rawRow['estimate_invoice_number'] ?? null;
        $cleanEstInvoice = null;
        $reviewStatus = null;
        if (! empty($rawEst)) {
            $upperEst = strtoupper($rawEst);
            if (str_contains($upperEst, 'CAMILA')) {
                $reviewStatus = 'CAMILA';
            } elseif (str_contains($upperEst, 'CS')) {
                $reviewStatus = 'CS';
            }

            // Remove prefixes like "REVISED - " or "REVIS-"
            $cleanEst = preg_replace('/^(REVISED|REVIS)[\s\-_–]*/i', '', $rawEst);
            $cleanEst = trim($cleanEst);
            // If it's solely "CS" or "CAMILA", leave invoice as empty, status is recorded
            if (! in_array(strtoupper($cleanEst), ['CS', 'CAMILA'], true)) {
                $cleanEstInvoice = $cleanEst;
            }
        }

        // 7. Designer Resolution
        $rawDesigner = $rawRow['designer_id'] ?? null;
        $resolvedDesignerId = null;
        if (! empty($rawDesigner)) {
            $resolvedDesignerId = $this->resolveDesignerId($rawDesigner, $designerMap);
        }

        // 8. Substatus Matching (Almost exact match)
        $rawSubstatus = $rawRow['substatus'] ?? null;
        $matchedSubstatus = null;
        if (! empty($rawSubstatus)) {
            $matchedSubstatus = $this->matchSubstatusAlmostExact($rawSubstatus, $dbSubstatuses);
        }

        // 9. Overview Checked
        $rawChecked = strtolower((string) ($rawRow['overview_checked'] ?? ''));
        $overviewChecked = in_array($rawChecked, ['true', '1', 'yes', 'si'], true);

        // 10. Installation Types
        $rawInstType = $rawRow['installation_type'] ?? null;
        $instType = ! empty($rawInstType) ? trim($rawInstType) : null;
        $instTypesArray = $instType ? array_map('trim', explode(',', $instType)) : [];

        $rawWo = $rawRow['wo_number'] ?? '';

        return [
            'raw_wo' => $rawWo,
            'normalized_wo' => $this->normalizeWo($rawWo),
            'company_name' => trim((string) ($rawRow['company_name'] ?? '')),
            'task_name' => trim((string) ($rawRow['task_name'] ?? '')),
            'production_processed_at' => $parsedProdProc,
            'delivery_due_date' => $parsedDeliveryDate,
            'email_date' => $parsedEmailDate,
            'production_note' => $productionNote,
            'delivery_note' => $deliveryNote,
            'estimate_invoice_number' => $cleanEstInvoice,
            'review_status' => $reviewStatus,
            'designer_id' => $resolvedDesignerId,
            'substatus' => $matchedSubstatus,
            'overview_checked' => $overviewChecked,
            'installation_type' => $instType,
            'installation_types' => $instTypesArray,
            'date_anomalies' => $dateAnomalies,
        ];
    }

    /**
     * Resolves designer name to existing user ID, or creates an inactive user account if unknown.
     */
    public function resolveDesignerId(string $rawName, array &$designerMap): int
    {
        $cleanName = trim($rawName);
        $normName = Str::lower($cleanName);
        $slug = Str::slug($cleanName);

        // Predefined mappings
        if (in_array($normName, ['euraliz', 'eu'], true)) {
            $normName = 'euralíz';
            $slug = 'euraliz';
        } elseif (in_array($normName, ['adrian', 'adrián'], true)) {
            $normName = 'adrián';
            $slug = 'adrian';
        } elseif (in_array($normName, ['cesar', 'césar'], true)) {
            $normName = 'césar';
            $slug = 'cesar';
        }

        if (isset($designerMap[$normName])) {
            return $designerMap[$normName];
        }

        if (isset($designerMap[$slug])) {
            return $designerMap[$slug];
        }

        // Partial lookup in existing map
        foreach ($designerMap as $nameKey => $id) {
            if (str_contains($nameKey, $normName) || str_contains($normName, $nameKey)) {
                return $id;
            }
        }

        // Not found: Create inactive Designer & User to preserve historical relationship without polluting active UI
        $displayName = ucwords(str_replace(['.', '_', '-'], ' ', $cleanName));
        $email = $slug.'@kudos.com';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $displayName,
                'role' => 'designer',
                'active' => false,
                'password' => bcrypt(Str::random(32)),
            ]
        );

        $designer = Designer::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $displayName,
                'user_id' => $user->id,
                'active' => false,
            ]
        );

        $designerMap[$normName] = $designer->id;
        $designerMap[$slug] = $designer->id;

        return $designer->id;
    }

    /**
     * Matches raw substatus with database substatuses requiring almost exact match.
     */
    public function matchSubstatusAlmostExact(string $rawSubstatus, array $dbSubstatuses): ?string
    {
        $clean = Str::upper(trim($rawSubstatus));
        $normalizedInput = Str::ascii($clean);

        // Direct exact match
        foreach ($dbSubstatuses as $id => $name) {
            $normName = Str::ascii(Str::upper(trim($name)));
            if ($normName === $normalizedInput) {
                return $name;
            }
        }

        // Common clean abbreviations
        if ($normalizedInput === 'CANCELED' || $normalizedInput === 'CANCELADA') {
            return 'CANCELADA';
        }
        if ($normalizedInput === 'PRODUCTION' || $normalizedInput === 'PRODUCCION') {
            return 'ENVIADO EN ALTA';
        }

        return null;
    }

    /**
     * Parse date string into Y-m-d. If invalid or plain text, returns null date and parenthesis note text.
     */
    public function parseDateOrNote(string $val, string $label): array
    {
        $clean = trim($val);
        // Strip prefixes like "Email " or "email "
        $cleanDate = preg_replace('/^email\s+/i', '', $clean);

        // Try standard formats
        $patterns = [
            '/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', // YYYY-MM-DD
            '/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', // MM/DD/YYYY or DD/MM/YYYY
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanDate, $m)) {
                $y = (int) ($m[3] > 1000 ? $m[3] : $m[1]);
                $mth = (int) ($m[3] > 1000 ? $m[1] : $m[2]);
                $d = (int) ($m[3] > 1000 ? $m[2] : $m[3]);

                if (checkdate($mth, $d, $y)) {
                    return [
                        'date' => sprintf('%04d-%02d-%02d', $y, $mth, $d),
                        'note_append' => null,
                    ];
                }
            }
        }

        // Try Carbon parsing
        try {
            $parsed = Carbon::parse($cleanDate);
            // If parsed year is reasonable (between 2020 and 2035)
            if ($parsed->year >= 2020 && $parsed->year <= 2035) {
                return [
                    'date' => $parsed->format('Y-m-d'),
                    'note_append' => null,
                ];
            }
        } catch (\Throwable $e) {
            // Not a parseable date
        }

        // Invalid or text milestone -> null date and note between parentheses
        return [
            'date' => null,
            'note_append' => "({$label}: {$clean})",
        ];
    }

    /**
     * Calculate field differences between existing DB order and parsed CSV data.
     */
    public function calculateOrderDiffs(Order $order, array $parsed): array
    {
        $diffs = [];

        if (! empty($parsed['production_processed_at']) && $order->production_processed_at?->format('Y-m-d') !== $parsed['production_processed_at']) {
            $diffs['production_processed_at'] = [
                'field' => 'Fecha Procesado',
                'current' => $order->production_processed_at?->format('Y-m-d') ?? '-',
                'proposed' => $parsed['production_processed_at'],
            ];
        }

        if (! empty($parsed['delivery_due_date']) && $order->delivery_due_date?->format('Y-m-d') !== $parsed['delivery_due_date']) {
            $diffs['delivery_due_date'] = [
                'field' => 'Fecha Entrega',
                'current' => $order->delivery_due_date?->format('Y-m-d') ?? '-',
                'proposed' => $parsed['delivery_due_date'],
            ];
        }

        if (! empty($parsed['email_date']) && $order->email_date?->format('Y-m-d') !== $parsed['email_date']) {
            $diffs['email_date'] = [
                'field' => 'Fecha Email',
                'current' => $order->email_date?->format('Y-m-d') ?? '-',
                'proposed' => $parsed['email_date'],
            ];
        }

        if (! empty($parsed['substatus']) && $order->substatus?->value !== $parsed['substatus']) {
            $diffs['substatus'] = [
                'field' => 'Subestatus',
                'current' => $order->substatus?->value ?? '-',
                'proposed' => $parsed['substatus'],
            ];
        }

        if (! empty($parsed['designer_id']) && $order->designer_id !== $parsed['designer_id']) {
            $diffs['designer_id'] = [
                'field' => 'Diseñador',
                'current' => $order->designer?->name ?? 'Sin asignar',
                'proposed' => Designer::find($parsed['designer_id'])?->name ?? ('ID '.$parsed['designer_id']),
            ];
        }

        if (! empty($parsed['production_note']) && trim((string) $order->production_note) !== $parsed['production_note']) {
            $diffs['production_note'] = [
                'field' => 'Notas de Producción',
                'current' => $order->production_note ? Str::limit($order->production_note, 35) : '-',
                'proposed' => Str::limit($parsed['production_note'], 35),
            ];
        }

        if (! empty($parsed['delivery_note']) && trim((string) $order->delivery_note) !== $parsed['delivery_note']) {
            $diffs['delivery_note'] = [
                'field' => 'Notas de Entrega',
                'current' => $order->delivery_note ? Str::limit($order->delivery_note, 35) : '-',
                'proposed' => Str::limit($parsed['delivery_note'], 35),
            ];
        }

        if (! empty($parsed['estimate_invoice_number']) && $order->estimate_invoice_number !== $parsed['estimate_invoice_number']) {
            $diffs['estimate_invoice_number'] = [
                'field' => 'Estimado / Factura',
                'current' => $order->estimate_invoice_number ?? '-',
                'proposed' => $parsed['estimate_invoice_number'],
            ];
        }

        if (! empty($parsed['review_status']) && $order->review_status !== $parsed['review_status']) {
            $diffs['review_status'] = [
                'field' => 'Revisión',
                'current' => $order->review_status ?? '-',
                'proposed' => $parsed['review_status'],
            ];
        }

        if (! empty($parsed['installation_type']) && $order->installation_type !== $parsed['installation_type']) {
            $diffs['installation_type'] = [
                'field' => 'Instalación',
                'current' => $order->installation_type ?? '-',
                'proposed' => $parsed['installation_type'],
            ];
        }

        if ($parsed['overview_checked'] && ! $order->overview_checked) {
            $diffs['overview_checked'] = [
                'field' => 'Overview Checked',
                'current' => 'No',
                'proposed' => 'Sí',
            ];
        }

        return $diffs;
    }

    /**
     * Normalize Work Order number (e.g. "WO 13919" -> "13919").
     */
    public function normalizeWo(?string $wo): string
    {
        if (empty($wo)) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $wo);

        return ltrim($clean, '0');
    }

    /**
     * Compute combined similarity score between CSV company/task and DB order company/task.
     */
    public function calculateCombinedSimilarity(?string $csvComp, ?string $csvTask, ?string $dbComp, ?string $dbTask): float
    {
        $compScore = $this->calculateStringSimilarity($csvComp, $dbComp);
        $taskScore = $this->calculateStringSimilarity($csvTask, $dbTask);

        // Weighted: 60% Company, 40% Task
        return ($compScore * 0.6) + ($taskScore * 0.4);
    }

    /**
     * Clean and calculate similarity between two strings using tokens & Levenshtein.
     */
    public function calculateStringSimilarity(?string $str1, ?string $str2): float
    {
        $s1 = $this->cleanForMatching($str1);
        $s2 = $this->cleanForMatching($str2);

        if ($s1 === '' && $s2 === '') {
            return 1.0;
        }
        if ($s1 === '' || $s2 === '') {
            return 0.0;
        }
        if ($s1 === $s2) {
            return 1.0;
        }

        // Substring containment check
        if (str_contains($s1, $s2) || str_contains($s2, $s1)) {
            $minLen = min(strlen($s1), strlen($s2));
            $maxLen = max(strlen($s1), strlen($s2));

            return 0.85 + (0.15 * ($minLen / $maxLen));
        }

        // Token overlap (Jaccard)
        $tokens1 = array_unique(array_filter(explode(' ', $s1)));
        $tokens2 = array_unique(array_filter(explode(' ', $s2)));

        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));

        $jaccard = count($union) > 0 ? (count($intersection) / count($union)) : 0.0;

        // Levenshtein / similar_text
        similar_text($s1, $s2, $simPercent);
        $simText = $simPercent / 100.0;

        return max($jaccard, $simText);
    }

    /**
     * Strips punctuation, accents, contact names in parentheses and legal noise.
     */
    protected function cleanForMatching(?string $str): string
    {
        if (empty($str)) {
            return '';
        }

        // Remove parentheses and their content (e.g. "(CESAR CHAVEZ)")
        $clean = preg_replace('/\s*\([^)]*\)/', '', (string) $str);
        // Normalize ASCII (accents)
        $clean = Str::ascii($clean);
        // Lowercase
        $clean = strtolower($clean);
        // Remove legal suffixes
        $clean = preg_replace('/\b(llc|inc|corp|services|service)\b/i', '', $clean);
        // Remove special characters
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
        // Collapse spaces
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $clean;
    }

    /**
     * Apply updates for full matches in batch silently (Zero-Automation Protocol).
     */
    public function applyBatchUpdates(array $itemsToUpdate, string $batchType = 'full_match'): array
    {
        if (empty($itemsToUpdate)) {
            return ['success' => false, 'message' => 'No hay órdenes seleccionadas para actualizar.'];
        }

        // 1. Create safety backup snapshot
        $backupFile = $this->createSafetyBackup();

        $updatedCount = 0;
        $orderIds = [];

        DB::transaction(function () use ($itemsToUpdate, &$updatedCount, &$orderIds) {
            foreach ($itemsToUpdate as $item) {
                $orderId = $item['order_id'] ?? null;
                $parsed = $item['parsed_data'] ?? null;

                if (! $orderId || ! $parsed) {
                    continue;
                }

                $updateData = [];

                if (! empty($parsed['production_processed_at'])) {
                    $updateData['production_processed_at'] = $parsed['production_processed_at'];
                }
                if (! empty($parsed['delivery_due_date'])) {
                    $updateData['delivery_due_date'] = $parsed['delivery_due_date'];
                }
                if (! empty($parsed['email_date'])) {
                    $updateData['email_date'] = $parsed['email_date'];
                }
                if (! empty($parsed['production_note'])) {
                    $updateData['production_note'] = $parsed['production_note'];
                }
                if (! empty($parsed['delivery_note'])) {
                    $updateData['delivery_note'] = $parsed['delivery_note'];
                }
                if (! empty($parsed['estimate_invoice_number'])) {
                    $updateData['estimate_invoice_number'] = $parsed['estimate_invoice_number'];
                }
                if (! empty($parsed['review_status'])) {
                    $updateData['review_status'] = $parsed['review_status'];
                }
                if (! empty($parsed['designer_id'])) {
                    $updateData['designer_id'] = $parsed['designer_id'];
                }
                if (! empty($parsed['substatus'])) {
                    $updateData['substatus'] = $parsed['substatus'];
                }
                if (! empty($parsed['installation_type'])) {
                    $updateData['installation_type'] = $parsed['installation_type'];
                }
                if (! empty($parsed['installation_types'])) {
                    $updateData['installation_types'] = json_encode($parsed['installation_types']);
                }
                if (! empty($parsed['overview_checked'])) {
                    $updateData['overview_checked'] = true;
                }

                // If updating a partial match where WO was missing in DB, assign it
                if (! empty($parsed['raw_wo'])) {
                    $order = Order::find($orderId);
                    if ($order && empty($order->wo_number)) {
                        $updateData['wo_number'] = 'WO '.$parsed['normalized_wo'];
                    }
                }

                // Strictly ensure company_name and task_name are NEVER updated
                unset($updateData['company_name'], $updateData['task_name']);

                if (! empty($updateData)) {
                    // ZERO AUTOMATION: execute without model observers or dispatchers
                    Order::withoutEvents(function () use ($orderId, $updateData) {
                        DB::table('orders')->where('id', $orderId)->update($updateData);
                    });
                    $updatedCount++;
                    $orderIds[] = $orderId;
                }
            }
        });

        // 2. Log to migration history
        $this->recordMigrationHistory([
            'id' => 'mig_'.date('Ymd_His'),
            'type' => $batchType,
            'user_id' => Auth::id() ?? 1,
            'user_name' => Auth::user()?->name ?? 'Admin',
            'updated_count' => $updatedCount,
            'order_ids' => $orderIds,
            'backup_file' => $backupFile,
            'created_at' => now()->toIso8601String(),
            'status' => 'applied',
        ]);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'backup_file' => $backupFile,
            'message' => "Se actualizaron exitosamente {$updatedCount} órdenes de forma silenciosa.",
        ];
    }

    /**
     * Link an unmatched CSV row to a Trello card, verifying card and saving silently.
     */
    public function linkTrelloCard(array $parsedData, string $trelloCardId, array $cardDetails): array
    {
        $backupFile = $this->createSafetyBackup();

        $cleanCardId = trim($trelloCardId);
        $order = Order::where('trello_card_id', $cleanCardId)->first();
        $isNew = false;

        DB::transaction(function () use (&$order, &$isNew, $cleanCardId, $parsedData, $cardDetails) {
            $woNumber = ! empty($parsedData['normalized_wo']) ? ('WO '.$parsedData['normalized_wo']) : null;

            if ($order) {
                // Order already exists in DB with this Trello card: update silently
                $updateData = [
                    'in_workspace' => false,
                ];

                if ($woNumber && empty($order->wo_number)) {
                    $updateData['wo_number'] = $woNumber;
                }
                if (! empty($parsedData['production_note'])) {
                    $updateData['production_note'] = $parsedData['production_note'];
                }
                if (! empty($parsedData['delivery_note'])) {
                    $updateData['delivery_note'] = $parsedData['delivery_note'];
                }
                if (! empty($parsedData['estimate_invoice_number'])) {
                    $updateData['estimate_invoice_number'] = $parsedData['estimate_invoice_number'];
                }
                if (! empty($parsedData['review_status'])) {
                    $updateData['review_status'] = $parsedData['review_status'];
                }
                if (! empty($parsedData['designer_id'])) {
                    $updateData['designer_id'] = $parsedData['designer_id'];
                }
                if (! empty($parsedData['substatus'])) {
                    $updateData['substatus'] = $parsedData['substatus'];
                }
                if (! empty($parsedData['installation_type'])) {
                    $updateData['installation_type'] = $parsedData['installation_type'];
                }

                Order::withoutEvents(function () use ($order, $updateData) {
                    DB::table('orders')->where('id', $order->id)->update($updateData);
                });
            } else {
                // Create new Order with in_workspace = false (Backlog inbox)
                $isNew = true;
                $cardName = $cardDetails['name'] ?? ($parsedData['task_name'] ?: 'Orden desde Trello');

                $createData = [
                    'trello_card_id' => $cleanCardId,
                    'trello_title' => $cardName,
                    'company_name' => $parsedData['company_name'] ?: ($cardDetails['name'] ?? 'Empresa'),
                    'task_name' => $parsedData['task_name'] ?: $cardName,
                    'wo_number' => $woNumber,
                    'in_workspace' => false, // strictly in backlog inbox
                    'core_status' => 'entrante',
                    'substatus' => $parsedData['substatus'] ?? null,
                    'designer_id' => $parsedData['designer_id'] ?? null,
                    'production_processed_at' => $parsedData['production_processed_at'] ?? null,
                    'delivery_due_date' => $parsedData['delivery_due_date'] ?? null,
                    'email_date' => $parsedData['email_date'] ?? null,
                    'production_note' => $parsedData['production_note'] ?? null,
                    'delivery_note' => $parsedData['delivery_note'] ?? null,
                    'estimate_invoice_number' => $parsedData['estimate_invoice_number'] ?? null,
                    'review_status' => $parsedData['review_status'] ?? null,
                    'installation_type' => $parsedData['installation_type'] ?? null,
                    'overview_checked' => (bool) ($parsedData['overview_checked'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Order::withoutEvents(function () use (&$order, $createData) {
                    $id = DB::table('orders')->insertGetId($createData);
                    $order = Order::find($id);
                });
            }
        });

        // Record migration history
        $this->recordMigrationHistory([
            'id' => 'mig_trello_'.date('Ymd_His'),
            'type' => 'trello_link',
            'user_id' => Auth::id() ?? 1,
            'user_name' => Auth::user()?->name ?? 'Admin',
            'updated_count' => 1,
            'order_ids' => [$order->id],
            'backup_file' => $backupFile,
            'created_at' => now()->toIso8601String(),
            'status' => 'applied',
        ]);

        return [
            'success' => true,
            'is_new' => $isNew,
            'order_id' => $order->id,
            'order_wo' => $order->wo_number,
            'message' => $isNew ? "Orden #{$order->id} creada y vinculada con Trello exitosamente." : "Orden existente #{$order->id} actualizada y vinculada con Trello.",
        ];
    }

    /**
     * Create safety SQLite snapshot backup prior to any batch changes.
     */
    public function createSafetyBackup(): string
    {
        $backupDir = config('database.backup_path', storage_path('app/backups'));
        File::ensureDirectoryExists($backupDir);

        $timestamp = date('Y-m-d_His');
        $filename = "reconciliation_pre_migration_{$timestamp}.sqlite";
        $targetPath = "{$backupDir}/{$filename}";

        $dbPath = config('database.connections.sqlite.database');
        if ($dbPath && File::exists($dbPath)) {
            File::copy($dbPath, $targetPath);
        } else {
            Artisan::call('db:backup');
        }

        return $filename;
    }

    /**
     * Rollback the last applied migration using its pre-migration backup snapshot.
     */
    public function rollbackLastMigration(): array
    {
        $history = $this->getMigrationHistory();
        if (empty($history)) {
            return ['success' => false, 'message' => 'No hay migraciones registradas para revertir.'];
        }

        // Find latest applied migration
        $targetIndex = null;
        $targetMigration = null;
        foreach ($history as $idx => $entry) {
            if (($entry['status'] ?? '') === 'applied') {
                $targetIndex = $idx;
                $targetMigration = $entry;
                break;
            }
        }

        if (! $targetMigration || ! isset($targetMigration['backup_file'])) {
            return ['success' => false, 'message' => 'No se encontró ninguna migración activa que pueda ser revertida.'];
        }

        $backupDir = config('database.backup_path', storage_path('app/backups'));
        $backupPath = $backupDir.'/'.$targetMigration['backup_file'];

        if (! File::exists($backupPath)) {
            return ['success' => false, 'message' => "El archivo de respaldo ({$targetMigration['backup_file']}) no existe en el servidor."];
        }

        $dbPath = config('database.connections.sqlite.database');
        if (! $dbPath || ! File::exists($dbPath)) {
            return ['success' => false, 'message' => 'Ruta de la base de datos principal no encontrada.'];
        }

        // 1. Create a safeguard snapshot of current state before rollback
        $safeguardFilename = 'reconciliation_before_rollback_'.date('Y-m-d_His').'.sqlite';
        File::copy($dbPath, "{$backupDir}/{$safeguardFilename}");

        // 2. Restore pre-migration database snapshot
        if (! File::copy($backupPath, $dbPath)) {
            return ['success' => false, 'message' => 'Error al restaurar el archivo de base de datos desde el respaldo.'];
        }

        // 3. Update history entry
        $history[$targetIndex]['status'] = 'reverted';
        $history[$targetIndex]['reverted_at'] = now()->toIso8601String();
        File::put($this->historyFile, json_encode($history, JSON_PRETTY_PRINT));

        // 4. Clear application cache
        Artisan::call('optimize:clear');

        return [
            'success' => true,
            'message' => "La migración '{$targetMigration['id']}' fue revertida con éxito. La base de datos ha vuelto al estado previo a la migración.",
        ];
    }

    /**
     * Persist current analysis to JSON storage.
     */
    protected function storeAnalysisCache(array $data): void
    {
        $file = $this->storageDir.'/last_analysis.json';
        File::put($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Retrieve cached analysis.
     */
    public function getCachedAnalysis(): ?array
    {
        $file = $this->storageDir.'/last_analysis.json';
        if (File::exists($file)) {
            $content = File::get($file);

            return json_decode($content, true);
        }

        return null;
    }

    /**
     * Append to migration history.
     */
    protected function recordMigrationHistory(array $entry): void
    {
        $history = $this->getMigrationHistory();
        array_unshift($history, $entry); // Prepend latest
        File::put($this->historyFile, json_encode($history, JSON_PRETTY_PRINT));
    }

    /**
     * Retrieve migration history list.
     */
    public function getMigrationHistory(): array
    {
        if (File::exists($this->historyFile)) {
            $content = File::get($this->historyFile);

            return json_decode($content, true) ?: [];
        }

        return [];
    }

    /**
     * Retrieve analysis meta summary only.
     */
    public function getAnalysisMeta(): ?array
    {
        $cached = $this->getCachedAnalysis();

        return $cached['meta'] ?? null;
    }

    /**
     * Retrieve rows for a specific tab as a continuous list with search and optional limit.
     *
     * @return array{items: array, total: int, displayed: int, hasMore: bool, totalPages: int}
     */
    public function getTabRows(string $tab, string $search = '', int|string $perPage = 50, int $page = 1): array
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return ['items' => [], 'total' => 0, 'displayed' => 0, 'hasMore' => false, 'totalPages' => 1];
        }

        $source = match ($tab) {
            'full_match' => $cached['full_matches'] ?? [],
            'partial_match' => $cached['partial_matches'] ?? [],
            'unmatched' => $cached['unmatched'] ?? [],
            default => [],
        };

        if (! empty($search)) {
            $term = strtolower(trim($search));
            $source = array_values(array_filter($source, function ($item) use ($term) {
                $wo = strtolower((string) ($item['wo_number'] ?? ($item['raw_wo'] ?? '')));
                $comp = strtolower((string) ($item['db_company'] ?? ($item['csv_company'] ?? '')));
                $task = strtolower((string) ($item['db_task'] ?? ($item['csv_task'] ?? '')));

                return str_contains($wo, $term) || str_contains($comp, $term) || str_contains($task, $term);
            }));
        }

        $total = count($source);

        if ($perPage === 'all' || (is_numeric($perPage) && (int) $perPage <= 0) || (is_numeric($perPage) && (int) $perPage >= $total)) {
            return [
                'items' => $source,
                'total' => $total,
                'displayed' => $total,
                'hasMore' => false,
                'totalPages' => 1,
            ];
        }

        $limitInt = max(1, (int) $perPage);
        $items = array_slice($source, 0, $limitInt);

        return [
            'items' => $items,
            'total' => $total,
            'displayed' => count($items),
            'hasMore' => $limitInt < $total,
            'totalPages' => (int) max(1, ceil($total / $limitInt)),
        ];
    }

    /**
     * Delete active cached analysis.
     */
    public function clearAnalysisCache(): void
    {
        $file = $this->storageDir.'/last_analysis.json';
        if (File::exists($file)) {
            File::delete($file);
        }
    }

    /**
     * Apply batch updates and update cached analysis file on server.
     */
    public function applyBatchAndSyncCache(string $batchType, array $selectedIds = []): array
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return ['success' => false, 'message' => 'No hay análisis activo en servidor.'];
        }

        $itemsToUpdate = [];
        if ($batchType === 'all_full') {
            $itemsToUpdate = $cached['full_matches'] ?? [];
        } elseif ($batchType === 'selected_full') {
            $lookup = array_flip($selectedIds);
            foreach ($cached['full_matches'] ?? [] as $item) {
                if (isset($lookup[$item['row_id']])) {
                    $itemsToUpdate[] = $item;
                }
            }
        } elseif ($batchType === 'selected_partial') {
            $lookup = array_flip($selectedIds);
            foreach ($cached['partial_matches'] ?? [] as $item) {
                if (isset($lookup[$item['row_id']])) {
                    $itemsToUpdate[] = $item;
                }
            }
        }

        if (empty($itemsToUpdate)) {
            return ['success' => false, 'message' => 'No hay órdenes seleccionadas para procesar.'];
        }

        $res = $this->applyBatchUpdates($itemsToUpdate, $batchType);
        if ($res['success']) {
            $appliedIds = array_flip(array_column($itemsToUpdate, 'row_id'));
            if (in_array($batchType, ['all_full', 'selected_full'], true)) {
                $cached['full_matches'] = array_values(array_filter(
                    $cached['full_matches'] ?? [],
                    fn ($i) => ! isset($appliedIds[$i['row_id']])
                ));
                $cached['meta']['full_match_count'] = count($cached['full_matches']);
            } else {
                $cached['partial_matches'] = array_values(array_filter(
                    $cached['partial_matches'] ?? [],
                    fn ($i) => ! isset($appliedIds[$i['row_id']])
                ));
                $cached['meta']['partial_match_count'] = count($cached['partial_matches']);
            }
            $this->storeAnalysisCache($cached);
        }

        return $res;
    }

    /**
     * Apply a single partial match and sync cache.
     */
    public function applySinglePartialAndSyncCache(string $rowId): array
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return ['success' => false, 'message' => 'No hay análisis activo.'];
        }

        $target = null;
        foreach ($cached['partial_matches'] ?? [] as $item) {
            if ($item['row_id'] === $rowId) {
                $target = $item;
                break;
            }
        }

        if (! $target) {
            return ['success' => false, 'message' => 'Fila no encontrada.'];
        }

        $res = $this->applyBatchUpdates([$target], 'single_partial');
        if ($res['success']) {
            $cached['partial_matches'] = array_values(array_filter(
                $cached['partial_matches'] ?? [],
                fn ($i) => $i['row_id'] !== $rowId
            ));
            $cached['meta']['partial_match_count'] = count($cached['partial_matches']);
            $this->storeAnalysisCache($cached);
        }

        return $res;
    }

    /**
     * Discard a partial match and move it to unmatched in cache.
     */
    public function discardPartialAndSyncCache(string $rowId): bool
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return false;
        }

        $target = null;
        $remaining = [];
        foreach ($cached['partial_matches'] ?? [] as $item) {
            if ($item['row_id'] === $rowId) {
                $target = $item;
            } else {
                $remaining[] = $item;
            }
        }

        if ($target) {
            $cached['partial_matches'] = $remaining;
            $cached['meta']['partial_match_count'] = count($remaining);
            $cached['unmatched'][] = [
                'row_id' => $target['row_id'],
                'raw_wo' => $target['parsed_data']['raw_wo'] ?? '',
                'csv_company' => $target['csv_company'],
                'csv_task' => $target['csv_task'],
                'parsed_data' => $target['parsed_data'],
                'suggested_trello_link' => null,
            ];
            $cached['meta']['unmatched_count'] = count($cached['unmatched']);
            $this->storeAnalysisCache($cached);

            return true;
        }

        return false;
    }

    /**
     * Link Trello card and remove from unmatched in cache.
     */
    public function linkTrelloAndSyncCache(string $rowId, string $trelloCardId, array $cardDetails): array
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return ['success' => false, 'message' => 'No hay análisis activo.'];
        }

        $target = null;
        foreach ($cached['unmatched'] ?? [] as $item) {
            if ($item['row_id'] === $rowId) {
                $target = $item;
                break;
            }
        }

        if (! $target) {
            return ['success' => false, 'message' => 'Registro no encontrado en lista sin coincidencia.'];
        }

        $res = $this->linkTrelloCard($target['parsed_data'], $trelloCardId, $cardDetails);
        if ($res['success']) {
            $cached['unmatched'] = array_values(array_filter(
                $cached['unmatched'] ?? [],
                fn ($i) => $i['row_id'] !== $rowId
            ));
            $cached['meta']['unmatched_count'] = count($cached['unmatched']);
            $this->storeAnalysisCache($cached);
        }

        return $res;
    }
}
