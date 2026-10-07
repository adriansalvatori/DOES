<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderReconciliationService
{
    /**
     * Known Georgia cities / local municipalities for location pattern detection.
     */
    public const LOCAL_LOCATIONS = [
        'atlanta', 'duluth', 'lawrenceville', 'norcross', 'lilburn', 'marietta', 'suwanee',
        'buford', 'roswell', 'alpharetta', 'decatur', 'gainesville', 'cumming', 'smyrna',
        'peachtree', 'woodstock', 'snellville', 'conyers', 'kennesaw', 'johns creek',
        'chamblee', 'doraville', 'sandy springs', 'tucker', 'stone mountain', 'flowery branch',
        'braselton', 'cartersville', 'canton', 'forest park', 'riverdale', 'morrow',
        'jonesboro', 'fayetteville', 'newnan', 'carrollton', 'athens', 'macon', 'augusta',
        'savannah', 'columbus', 'buckhead', 'midtown', 'downtown',
    ];

    /**
     * Common product categories to prevent cross-matching incompatible jobs for the same client.
     */
    public const PRODUCT_CATEGORIES = [
        'textile' => ['embroidery', 'bordado', 'shirt', 'shirts', 'camisa', 'camisas', 'hoodie', 'hoodies', 'gorra', 'gorras', 'dtf', 'polo', 'polos', 'playera', 'playeras', 'sueter', 'sweater'],
        'vehicle' => ['wrap', 'van', 'truck', 'trailer', 'vehiculo', 'carro', 'auto', 'camioneta', 'food truck', 'flecha vehicular'],
        'window' => ['window perf', 'microperforado', 'frosted', 'vinil ventana', 'perforated', '50/50'],
        'paper_print' => ['business cards', 'tarjetas', 'flyer', 'flyers', 'menu', 'menus', 'trifold', 'brochure', 'carpetas', 'folder'],
        'signage' => ['channel letters', 'letrero', 'acrilico', 'acrylic', 'caja de luz', 'light box', 'banner', 'lona', 'sala de ventas', 'locacion', 'dimensional letters', 'coroplast', 'yard signs', 'pylon', 'monument'],
    ];

    protected string $storageDir;

    protected string $historyFile;

    public function __construct()
    {
        $this->storageDir = app()->environment('testing')
            ? storage_path('framework/testing/reconciliation')
            : storage_path('app/reconciliation');
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
        // Cache existing DB orders for fast in-memory matching
        $dbOrders = Order::query()
            ->select([
                'id',
                'wo_number',
                'company_name',
                'task_name',
                'client_id',
                'location_name',
                'responsible_person',
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

        // Cache all clients for entity mapping and typo matching
        $clients = Client::all();

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

            // Extract patterns (parentheses, quotes, locations, contacts)
            $compPatterns = $this->parseCompanyPatterns($parsedRow['company_name']);
            $cleanCompany = $compPatterns['clean_name'];

            $taskPatterns = $this->parseTaskPatterns($parsedRow['task_name'], $cleanCompany);
            $cleanTask = $taskPatterns['clean_task'];

            // Resolve against Client model with typo tolerance
            $clientResolution = $this->resolveClientEntity($cleanCompany, $clients);
            $resolvedClient = $clientResolution['client'];

            // Append any non-location/non-contact details to production note
            if (! empty($compPatterns['extracted_detail'])) {
                $detailAppend = "(Detalle: {$compPatterns['extracted_detail']})";
                $parsedRow['production_note'] = $parsedRow['production_note'] ? ($parsedRow['production_note'].' '.$detailAppend) : $detailAppend;
            }

            // Populate enriched parsed data
            $parsedRow['clean_company'] = $cleanCompany;
            $parsedRow['clean_task'] = $cleanTask;
            $parsedRow['extracted_location'] = $compPatterns['extracted_location'];
            $parsedRow['extracted_contact'] = $compPatterns['extracted_contact'];
            $parsedRow['extracted_alias'] = $compPatterns['extracted_alias'];
            $parsedRow['resolved_client_id'] = $resolvedClient?->id;
            $parsedRow['resolved_client_name'] = $resolvedClient?->name;
            $parsedRow['typo_detected'] = $clientResolution['typo_detected'];
            $parsedRow['client_match_type'] = $clientResolution['match_type'];

            $csvWo = $parsedRow['normalized_wo'];
            $csvCompany = $parsedRow['company_name'];
            $csvTask = $parsedRow['task_name'];

            // 1. SUPREME ANCHOR: Try matching by WO number
            if (! empty($csvWo) && isset($ordersByWo[$csvWo])) {
                $matchedOrder = $ordersByWo[$csvWo];

                // Verify company compatibility to detect potential WO typo conflicts
                $compSim = $this->calculateStringSimilarity($cleanCompany, $matchedOrder->company_name);
                $isSameClient = ($resolvedClient && $matchedOrder->client_id === $resolvedClient->id);

                // Extreme conflict condition: two totally unrelated corporations (< 15% similarity)
                $isConflict = (! $isSameClient && $compSim < 0.15 && ! empty($cleanCompany) && ! empty($matchedOrder->company_name)
                    && ! str_contains(strtolower($cleanCompany), strtolower($matchedOrder->company_name))
                    && ! str_contains(strtolower($matchedOrder->company_name), strtolower($cleanCompany)));

                $diffs = $this->calculateOrderDiffs($matchedOrder, $parsedRow);

                if (! $isConflict) {
                    // FULL MATCH: WO is king
                    $fullMatches[] = [
                        'row_id' => $rowId,
                        'order_id' => $matchedOrder->id,
                        'wo_number' => $matchedOrder->wo_number ?? ('WO '.$csvWo),
                        'db_company' => $matchedOrder->company_name,
                        'db_task' => $matchedOrder->task_name,
                        'csv_company' => $csvCompany,
                        'csv_task' => $csvTask,
                        'clean_company' => $cleanCompany,
                        'clean_task' => $cleanTask,
                        'extracted_contact' => $parsedRow['extracted_contact'],
                        'extracted_location' => $parsedRow['extracted_location'],
                        'extracted_alias' => $parsedRow['extracted_alias'],
                        'resolved_client_id' => $parsedRow['resolved_client_id'],
                        'resolved_client_name' => $parsedRow['resolved_client_name'],
                        'typo_detected' => $parsedRow['typo_detected'],
                        'similarity' => 100.0,
                        'parsed_data' => $parsedRow,
                        'diffs' => $diffs,
                        'has_changes' => count($diffs) > 0,
                    ];
                } else {
                    // WO matches, but company is completely different
                    $partialMatches[] = [
                        'row_id' => $rowId,
                        'order_id' => $matchedOrder->id,
                        'wo_number' => $matchedOrder->wo_number ?? ('WO '.$csvWo),
                        'db_company' => $matchedOrder->company_name,
                        'db_task' => $matchedOrder->task_name,
                        'csv_company' => $csvCompany,
                        'csv_task' => $csvTask,
                        'clean_company' => $cleanCompany,
                        'clean_task' => $cleanTask,
                        'extracted_contact' => $parsedRow['extracted_contact'],
                        'extracted_location' => $parsedRow['extracted_location'],
                        'extracted_alias' => $parsedRow['extracted_alias'],
                        'resolved_client_id' => $parsedRow['resolved_client_id'],
                        'resolved_client_name' => $parsedRow['resolved_client_name'],
                        'typo_detected' => $parsedRow['typo_detected'],
                        'similarity' => round($compSim * 100, 1),
                        'reason' => "⚠️ Mismo WO ({$csvWo}), pero la empresa en BD ({$matchedOrder->company_name}) difiere de ({$csvCompany})",
                        'match_type' => 'wo_conflict',
                        'parsed_data' => $parsedRow,
                        'diffs' => $diffs,
                    ];
                }

                continue;
            }

            // 2. SECONDARY ANCHORS for rows without WO:
            $bestCandidate = null;
            $bestReason = '';
            $bestScore = 0.0;

            // Anchor 2A: Match by Estimate/Invoice Number + Client
            $csvEst = $parsedRow['estimate_invoice_number'];
            if (! empty($csvEst)) {
                foreach ($dbOrders as $candidateOrder) {
                    if (! empty($candidateOrder->estimate_invoice_number) && $candidateOrder->estimate_invoice_number === $csvEst) {
                        $compScore = $this->calculateStringSimilarity($cleanCompany, $candidateOrder->company_name);
                        if ($compScore >= 0.3 || ($resolvedClient && $candidateOrder->client_id === $resolvedClient->id)) {
                            // Check product category compatibility
                            if ($this->areTaskCategoriesCompatible($cleanTask, $candidateOrder->task_name)) {
                                $bestCandidate = $candidateOrder;
                                $bestScore = 95.0;
                                $bestReason = "Misma Factura/Estimado (#{$csvEst}) y cliente coincidente ({$candidateOrder->company_name})";
                                break;
                            }
                        }
                    }
                }
            }

            // Anchor 2B: Match by Client + Nearby Date Window (+- 14 days) + Mandatory Task Guard (>= 40%)
            if (! $bestCandidate && $resolvedClient) {
                $targetDate = $parsedRow['production_processed_at'] ?? ($parsedRow['email_date'] ?? $parsedRow['delivery_due_date']);
                if ($targetDate) {
                    try {
                        $targetCarbon = Carbon::parse($targetDate);
                        foreach ($dbOrders as $candidateOrder) {
                            if ($candidateOrder->client_id === $resolvedClient->id || $this->cleanForMatching($candidateOrder->company_name) === $this->cleanForMatching($cleanCompany)) {
                                $cDate = $candidateOrder->production_processed_at ?? ($candidateOrder->email_date ?? $candidateOrder->delivery_due_date);
                                if ($cDate) {
                                    $daysDiff = abs($targetCarbon->diffInDays(Carbon::parse($cDate)));
                                    if ($daysDiff <= 14) {
                                        // 1. Mandatory category check (blocks cross-matching textile with signs/vehicles)
                                        if (! $this->areTaskCategoriesCompatible($cleanTask, $candidateOrder->task_name)) {
                                            continue;
                                        }

                                        // 2. Mandatory Task Similarity check (>= 40%)
                                        $taskScore = $this->calculateStringSimilarity($cleanTask, $candidateOrder->task_name);
                                        if ($taskScore >= 0.40) {
                                            $bestCandidate = $candidateOrder;
                                            $bestScore = round(($taskScore * 100), 1);
                                            $bestReason = "Mismo cliente oficial ({$resolvedClient->name}), tarea compatible (".round($taskScore * 100)."%) y fecha cercana ({$daysDiff}d de diferencia)";
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // Ignore date parse issues
                    }
                }
            }

            if ($bestCandidate) {
                $diffs = $this->calculateOrderDiffs($bestCandidate, $parsedRow);
                $partialMatches[] = [
                    'row_id' => $rowId,
                    'order_id' => $bestCandidate->id,
                    'wo_number' => ! empty($parsedRow['raw_wo']) ? ('WO '.$parsedRow['normalized_wo']) : ($bestCandidate->wo_number ? ($bestCandidate->wo_number.' (en BD)') : 'Sin WO'),
                    'db_company' => $bestCandidate->company_name,
                    'db_task' => $bestCandidate->task_name,
                    'csv_company' => $csvCompany,
                    'csv_task' => $csvTask,
                    'clean_company' => $cleanCompany,
                    'clean_task' => $cleanTask,
                    'extracted_contact' => $parsedRow['extracted_contact'],
                    'extracted_location' => $parsedRow['extracted_location'],
                    'extracted_alias' => $parsedRow['extracted_alias'],
                    'resolved_client_id' => $parsedRow['resolved_client_id'],
                    'resolved_client_name' => $parsedRow['resolved_client_name'],
                    'typo_detected' => $parsedRow['typo_detected'],
                    'similarity' => $bestScore,
                    'reason' => $bestReason,
                    'match_type' => 'secondary_anchor_match',
                    'parsed_data' => $parsedRow,
                    'diffs' => $diffs,
                ];

                continue;
            }

            // 3. Not enough match (Orphan order ready for Trello connection)
            $unmatched[] = [
                'row_id' => $rowId,
                'raw_wo' => $parsedRow['raw_wo'],
                'csv_company' => $csvCompany,
                'csv_task' => $csvTask,
                'clean_company' => $cleanCompany,
                'clean_task' => $cleanTask,
                'extracted_contact' => $parsedRow['extracted_contact'],
                'extracted_location' => $parsedRow['extracted_location'],
                'extracted_alias' => $parsedRow['extracted_alias'],
                'resolved_client_id' => $parsedRow['resolved_client_id'],
                'resolved_client_name' => $parsedRow['resolved_client_name'],
                'typo_detected' => $parsedRow['typo_detected'],
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

        if (! empty($parsed['resolved_client_name']) && empty($order->client_id)) {
            $diffs['client'] = [
                'field' => 'Cliente Oficial',
                'current' => $order->client?->name ?? '-',
                'proposed' => $parsed['resolved_client_name'].(! empty($parsed['typo_detected']) ? ' (Typo corregido)' : ''),
            ];
        }

        if (! empty($parsed['extracted_contact']) && empty($order->responsible_person)) {
            $diffs['responsible_person'] = [
                'field' => 'Contacto / Resp.',
                'current' => $order->responsible_person ?? '-',
                'proposed' => $parsed['extracted_contact'],
            ];
        }

        if (! empty($parsed['extracted_location']) && empty($order->location_name)) {
            $diffs['location_name'] = [
                'field' => 'Locación',
                'current' => $order->location_name ?? '-',
                'proposed' => $parsed['extracted_location'],
            ];
        }

        // Semantic Smart Merge: Harmonize DB task name and CSV task name to prevent data loss
        $dbTask = trim((string) ($order->task_name ?? ''));
        $csvTask = trim((string) ($parsed['task_name'] ?? ''));

        if (! empty($csvTask)) {
            $mergedTask = $this->mergeTaskNames($dbTask, $csvTask);
            if (! empty($mergedTask) && mb_strtolower($dbTask) !== mb_strtolower($mergedTask)) {
                $diffs['task_name'] = [
                    'field' => 'Nombre de Tarea',
                    'current' => $dbTask ?: '-',
                    'proposed' => $mergedTask,
                    'is_smart_merge' => true,
                ];
            }
        }

        return $diffs;
    }

    /**
     * Intelligently merges DB task name and CSV task name, preserving all dimensions,
     * quantities, materials/finishes, and distinct descriptive terms without duplicating words.
     */
    public function mergeTaskNames(?string $dbTask, ?string $csvTask): string
    {
        $db = trim((string) $dbTask);
        $csv = trim((string) $csvTask);

        if ($db === '' && $csv === '') {
            return '';
        }
        if ($db === '') {
            return $csv;
        }
        if ($csv === '') {
            return $db;
        }
        if (mb_strtolower($db) === mb_strtolower($csv)) {
            return $db;
        }

        // 1. Extract Dimensions (e.g. "2x3", "2 x 3", "24x36", "3ft x 5ft", "12\" x 18\"")
        $dimRegex = '/(?:(?<=\s|^)|\()(\d+(?:\.\d+)?)\s*(?:x|\*|by|por)\s*(\d+(?:\.\d+)?)(?:\s*(?:in|ft|cm|mm|pie|pies|pul|pulg|"))?\b(?:\))?/i';
        $dimension = null;
        if (preg_match($dimRegex, $db, $mDb)) {
            $dimension = $mDb[1].'x'.$mDb[2];
            $db = trim(preg_replace($dimRegex, ' ', $db, 1));
        }
        if (preg_match($dimRegex, $csv, $mCsv)) {
            if (! $dimension) {
                $dimension = $mCsv[1].'x'.$mCsv[2];
            }
            $csv = trim(preg_replace($dimRegex, ' ', $csv, 1));
        }

        // 2. Extract Quantities (e.g. "qty. 500", "cant 100", "500 pcs", "x 1000")
        $qtyRegex1 = '/\b(?:qty\.?|cant\.?|cantidad|pcs|unidades|pz|pzs|piezas|x)\s*[:#]?\s*(\d+(?:,\d+)?)\b/i';
        $qtyRegex2 = '/\b(\d+(?:,\d+)?)\s*(?:pcs|unidades|cant|qty|piezas)\b/i';
        $quantity = null;
        if (preg_match($qtyRegex1, $csv, $mQ) || preg_match($qtyRegex2, $csv, $mQ)) {
            $quantity = 'Qty. '.$mQ[1];
            $csv = trim(preg_replace([$qtyRegex1, $qtyRegex2], ' ', $csv, 1));
        } elseif (preg_match($qtyRegex1, $db, $mQ) || preg_match($qtyRegex2, $db, $mQ)) {
            $quantity = 'Qty. '.$mQ[1];
            $db = trim(preg_replace([$qtyRegex1, $qtyRegex2], ' ', $db, 1));
        }

        // 3. Extract Parenthetical Notes (e.g. "(INTERNO)", "(SALA DE VENTAS)")
        $parentheticalNotes = [];
        if (preg_match_all('/\(([^)]+)\)/', $db, $mNotesDb)) {
            foreach ($mNotesDb[0] as $idx => $full) {
                $content = trim($mNotesDb[1][$idx]);
                if (! empty($content)) {
                    $parentheticalNotes[] = '('.ucwords(mb_strtolower($content)).')';
                }
            }
            $db = trim(preg_replace('/\([^)]+\)/', ' ', $db));
        }
        if (preg_match_all('/\(([^)]+)\)/', $csv, $mNotesCsv)) {
            foreach ($mNotesCsv[0] as $idx => $full) {
                $content = trim($mNotesCsv[1][$idx]);
                $formatted = '('.ucwords(mb_strtolower($content)).')';
                if (! empty($content) && ! in_array($formatted, $parentheticalNotes, true)) {
                    $parentheticalNotes[] = $formatted;
                }
            }
            $csv = trim(preg_replace('/\([^)]+\)/', ' ', $csv));
        }

        // 4. Extract Core Product / Item Keyword
        $productKeywords = [
            'sticker', 'stickers', 'banner', 'banners', 'letrero', 'letreros', 'sign', 'signs',
            'menu', 'menus', 'window perf', 'window', 'windows', 'wrap', 'partial wrap', 'full wrap',
            'flyer', 'flyers', 'business cards', 'tarjetas', 'hoodie', 'hoodies', 'shirt', 'shirts',
            'playera', 'playeras', 'gorra', 'gorras', 'polo', 'polos', 'coroplast', 'acrilico',
            'canvas', 'poster', 'posters', 'carpetas', 'folder', 'magnets', 'magnetico',
            'caja de luz', 'light box', 'yard signs', 'cartelera',
        ];

        $matchedProduct = null;
        foreach ($productKeywords as $kw) {
            $pattern = '/\b'.preg_quote($kw, '/').'\b/i';
            if (preg_match($pattern, $db) || preg_match($pattern, $csv)) {
                $matchedProduct = ucwords(mb_strtolower($kw));
                $db = trim(preg_replace($pattern, ' ', $db, 1));
                $csv = trim(preg_replace($pattern, ' ', $csv, 1));
                break;
            }
        }

        // 5. Extract Materials / Finishes
        $finishKeywords = [
            'con ojalillos', 'grommets', 'laminado', 'laminated', 'coroplast', 'acrilico',
            'acrylic', 'aluminio', 'aluminum', 'foam board', 'pvc', 'sintra', 'microperforado',
            'frosted', 'reflectivo', 'reflective', 'gloss', 'matte', 'mate', 'h-stakes',
            'estacas', 'pole pockets', 'bolsillos', 'hemmed', 'costura', 'imantado', 'magnetico', 'alta',
        ];

        $matchedFinishes = [];
        foreach ($finishKeywords as $fk) {
            $pattern = '/\b'.preg_quote($fk, '/').'\b/i';
            if (preg_match($pattern, $db) || preg_match($pattern, $csv)) {
                $formattedFk = ucwords(mb_strtolower($fk));
                if (! in_array($formattedFk, $matchedFinishes, true)) {
                    $matchedFinishes[] = $formattedFk;
                }
                $db = trim(preg_replace($pattern, ' ', $db, 1));
                $csv = trim(preg_replace($pattern, ' ', $csv, 1));
            }
        }

        // 6. Tokenize Remaining Descriptive Words from both DB and CSV
        $cleanPunct = fn (string $s) => trim(preg_replace('/[,\-\/:]+/', ' ', $s));
        $dbClean = $cleanPunct($db);
        $csvClean = $cleanPunct($csv);

        $descriptors = [];
        $seenWords = [];

        if ($matchedProduct) {
            foreach (preg_split('/\s+/', mb_strtolower($matchedProduct)) as $w) {
                $seenWords[$w] = true;
            }
        }
        foreach ($matchedFinishes as $f) {
            foreach (preg_split('/\s+/', mb_strtolower($f)) as $w) {
                $seenWords[$w] = true;
            }
        }

        $addWords = function (string $text) use (&$descriptors, &$seenWords) {
            $words = preg_split('/\s+/', trim($text));
            foreach ($words as $word) {
                $lower = mb_strtolower(trim($word));
                if ($lower === '' || isset($seenWords[$lower])) {
                    continue;
                }
                if (in_array($lower, ['de', 'con', 'y', 'para', 'en', 'el', 'la', 'los', 'las', 'un', 'una'], true) && empty($descriptors)) {
                    continue;
                }
                $seenWords[$lower] = true;
                $descriptors[] = ucwords($lower);
            }
        };

        $addWords($dbClean);
        $addWords($csvClean);

        // 7. Assembly
        $finalParts = [];

        if ($matchedProduct) {
            $finalParts[] = $matchedProduct;
        }

        if (! empty($descriptors)) {
            $finalParts[] = implode(' ', $descriptors);
        }

        if (! empty($matchedFinishes)) {
            $finalParts[] = implode(' ', $matchedFinishes);
        }

        if ($dimension) {
            $finalParts[] = $dimension;
        }

        if ($quantity) {
            $finalParts[] = $quantity;
        }

        if (! empty($parentheticalNotes)) {
            $finalParts[] = implode(' ', $parentheticalNotes);
        }

        $assembled = trim(implode(' ', $finalParts));

        return $assembled !== '' ? $assembled : ($dbTask ?: ($csvTask ?? ''));
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

        // If comparing multi-word strings, do not treat random character coincidence as similarity
        if (count($tokens1) > 1 || count($tokens2) > 1) {
            if ($jaccard === 0.0) {
                // If there is zero word overlap, check if there is at least one close typo token
                $hasCloseToken = false;
                foreach ($tokens1 as $t1) {
                    foreach ($tokens2 as $t2) {
                        if (levenshtein($t1, $t2) <= 2) {
                            $hasCloseToken = true;
                            break 2;
                        }
                    }
                }

                return $hasCloseToken ? ($simText * 0.5) : 0.0;
            }

            return ($jaccard * 0.6) + ($simText * 0.4);
        }

        return max($jaccard, $simText);
    }

    /**
     * Detects product category for a task description.
     */
    public function detectTaskCategory(?string $task): ?string
    {
        if (empty($task)) {
            return null;
        }

        $norm = ' '.$this->cleanForMatching($task).' ';

        foreach (self::PRODUCT_CATEGORIES as $category => $keywords) {
            foreach ($keywords as $kw) {
                $cleanKw = $this->cleanForMatching($kw);
                if (str_contains($norm, ' '.$cleanKw.' ') || str_contains($norm, $cleanKw)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Checks if two task descriptions belong to compatible product categories.
     * Prevents cross-matching e.g. embroidery jobs with sign/storefront jobs for the same client.
     */
    public function areTaskCategoriesCompatible(?string $task1, ?string $task2): bool
    {
        $cat1 = $this->detectTaskCategory($task1);
        $cat2 = $this->detectTaskCategory($task2);

        // If both tasks have an identified category and they differ, they are incompatible
        if ($cat1 !== null && $cat2 !== null && $cat1 !== $cat2) {
            return false;
        }

        return true;
    }

    /**
     * Extracts locations, contacts, quotes/aliases, and clean name from raw company string.
     *
     * @return array{
     *     clean_name: string,
     *     raw_company: string,
     *     extracted_location: ?string,
     *     extracted_contact: ?string,
     *     extracted_alias: ?string,
     *     extracted_detail: ?string
     * }
     */
    public function parseCompanyPatterns(?string $rawCompany): array
    {
        $raw = trim((string) $rawCompany);
        if ($raw === '') {
            return [
                'clean_name' => '',
                'raw_company' => '',
                'extracted_location' => null,
                'extracted_contact' => null,
                'extracted_alias' => null,
                'extracted_detail' => null,
            ];
        }

        $location = null;
        $contact = null;
        $alias = null;
        $detail = null;

        // 1. Quoted segments (e.g. JOSE DIAZ "EL GALLO" or "TACOS EL REY")
        if (preg_match('/["\']([^"\']+)["\']/', $raw, $quoteMatch)) {
            $alias = trim($quoteMatch[1]);
            $raw = trim(str_replace($quoteMatch[0], '', $raw));
        }

        // 2. Parentheses segments (e.g. "(CESAR CHAVEZ)", "(LAWRENCEVILLE)", "(STORE #4)")
        if (preg_match_all('/\(([^)]+)\)/', $raw, $parenMatches)) {
            foreach ($parenMatches[1] as $content) {
                $contentTrim = trim($content);
                $contentLower = strtolower($contentTrim);

                // Check if it's a location (contains store #, suite, or known GA city)
                $isLoc = false;
                if (preg_match('/\b(store\s*#?\d+|ste\s*#?\d+|suite\s*#?\d+|hwy|rd|blvd|ave|mall|plaza|north|south|east|west)\b/i', $contentTrim)) {
                    $isLoc = true;
                } else {
                    foreach (self::LOCAL_LOCATIONS as $locCity) {
                        if (str_contains($contentLower, $locCity)) {
                            $isLoc = true;
                            break;
                        }
                    }
                }

                if ($isLoc) {
                    $location = $location ? ($location.', '.$contentTrim) : $contentTrim;

                    continue;
                }

                // Check if it looks like a person's name (1-3 words, letters only)
                if (! preg_match('/\d/', $contentTrim) && preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s\.\-]+$/', $contentTrim)) {
                    $words = array_values(array_filter(explode(' ', $contentTrim)));
                    $notPersonWords = ['garantia', 'nuevo', 'cliente', 'revisado', 'urgente', 'kudos', 'orden', 'copia', 'cancelado', 'pendiente'];
                    $hasNoise = false;
                    foreach ($words as $w) {
                        if (in_array(strtolower($w), $notPersonWords, true)) {
                            $hasNoise = true;
                            break;
                        }
                    }

                    if (! $hasNoise && count($words) >= 1 && count($words) <= 3) {
                        $contact = $contact ? ($contact.' / '.$contentTrim) : $contentTrim;

                        continue;
                    }
                }

                // Otherwise treat as detail/note
                $detail = $detail ? ($detail.' / '.$contentTrim) : $contentTrim;
            }

            // Remove all parentheses from working string
            $raw = trim(preg_replace('/\s*\([^)]*\)/', '', $raw));
        }

        // 3. Dash or Slash Separators (e.g. "POLLO CAMPERO - DULUTH")
        if (preg_match('/\s+[-–—\/]\s+(.+)$/', $raw, $sepMatch)) {
            $suffix = trim($sepMatch[1]);
            $suffixLower = strtolower($suffix);

            $isSuffixLoc = false;
            foreach (self::LOCAL_LOCATIONS as $locCity) {
                if (str_contains($suffixLower, $locCity)) {
                    $isSuffixLoc = true;
                    break;
                }
            }

            if ($isSuffixLoc) {
                $location = $location ?: $suffix;
                $raw = trim(substr($raw, 0, -strlen($sepMatch[0])));
            }
        }

        // Clean up remaining company name
        $cleanName = trim(preg_replace('/\s+/', ' ', $raw), " \t\n\r\0\x0B-–—/,");

        return [
            'clean_name' => $cleanName ?: trim((string) $rawCompany),
            'raw_company' => trim((string) $rawCompany),
            'extracted_location' => $location,
            'extracted_contact' => $contact,
            'extracted_alias' => $alias,
            'extracted_detail' => $detail,
        ];
    }

    /**
     * Cleans task name and removes redundant company name prefixes.
     */
    public function parseTaskPatterns(?string $rawTask, string $cleanCompany = ''): array
    {
        $task = trim((string) $rawTask);
        if ($task === '') {
            return ['clean_task' => ''];
        }

        if ($cleanCompany !== '') {
            $cLower = strtolower($cleanCompany);
            $tLower = strtolower($task);
            if (str_starts_with($tLower, $cLower)) {
                $remainder = trim(substr($task, strlen($cleanCompany)));
                $remainder = trim(ltrim($remainder, " \t\n\r\0\x0B-–—/:"));
                if (! empty($remainder)) {
                    $task = $remainder;
                }
            }
        }

        return [
            'clean_task' => $task,
        ];
    }

    /**
     * Resolves a raw/clean company name to an existing Client model, with typo tolerance.
     *
     * @param  Collection<int, Client>  $clients
     * @return array{
     *     client: ?Client,
     *     match_type: ?string,
     *     typo_detected: bool,
     *     original_term: ?string
     * }
     */
    public function resolveClientEntity(string $cleanCompany, $clients): array
    {
        $compTrim = trim($cleanCompany);
        if ($compTrim === '') {
            return ['client' => null, 'match_type' => null, 'typo_detected' => false, 'original_term' => null];
        }

        $compNormalized = $this->cleanForMatching($compTrim);
        if ($compNormalized === '') {
            return ['client' => null, 'match_type' => null, 'typo_detected' => false, 'original_term' => null];
        }

        // Stage 1: Exact Name Match (case & legal suffix insensitive)
        foreach ($clients as $client) {
            $clientNorm = $this->cleanForMatching($client->name);
            if ($clientNorm !== '' && $clientNorm === $compNormalized) {
                return [
                    'client' => $client,
                    'match_type' => 'exact',
                    'typo_detected' => false,
                    'original_term' => $compTrim,
                ];
            }
        }

        // Stage 2: Existing Aliases Match
        foreach ($clients as $client) {
            if ($client->matchesNameOrAlias($compTrim)) {
                return [
                    'client' => $client,
                    'match_type' => 'alias',
                    'typo_detected' => false,
                    'original_term' => $compTrim,
                ];
            }
            if (is_array($client->aliases)) {
                foreach ($client->aliases as $alias) {
                    if ($this->cleanForMatching($alias) === $compNormalized) {
                        return [
                            'client' => $client,
                            'match_type' => 'alias',
                            'typo_detected' => false,
                            'original_term' => $compTrim,
                        ];
                    }
                }
            }
        }

        // Stage 3: Fuzzy Match with Typo Tolerance (Levenshtein <= 2 or similar_text >= 88%)
        $bestClient = null;
        $bestScore = 0.0;
        $compLen = strlen($compNormalized);

        foreach ($clients as $client) {
            $clientNorm = $this->cleanForMatching($client->name);
            if ($clientNorm === '') {
                continue;
            }

            // A. Levenshtein Check for Typos
            $lev = levenshtein($compNormalized, $clientNorm);
            if (($compLen > 6 && $lev <= 2) || ($compLen <= 6 && $lev <= 1)) {
                return [
                    'client' => $client,
                    'match_type' => 'typo',
                    'typo_detected' => true,
                    'original_term' => $compTrim,
                ];
            }

            // B. High Token / Substring Similarity
            similar_text($compNormalized, $clientNorm, $simPercent);
            $score = $simPercent / 100.0;
            if ($score >= 0.88 && $score > $bestScore) {
                $bestScore = $score;
                $bestClient = $client;
            }
        }

        if ($bestClient && $bestScore >= 0.88) {
            return [
                'client' => $bestClient,
                'match_type' => 'typo',
                'typo_detected' => true,
                'original_term' => $compTrim,
            ];
        }

        return [
            'client' => null,
            'match_type' => null,
            'typo_detected' => false,
            'original_term' => null,
        ];
    }

    /**
     * Quickly creates a new Client record from an unmapped row in the active analysis cache.
     */
    public function quickCreateClientFromRow(string $rowId): ?Client
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return null;
        }

        $targetItem = null;
        $tabKey = null;

        foreach (['full_matches', 'partial_matches', 'unmatched'] as $tab) {
            foreach ($cached[$tab] as $idx => $item) {
                if (($item['row_id'] ?? '') === $rowId) {
                    $targetItem = $item;
                    $tabKey = $tab;
                    break 2;
                }
            }
        }

        if (! $targetItem) {
            return null;
        }

        $cleanName = $targetItem['clean_company'] ?? ($targetItem['csv_company'] ?? null);
        if (empty($cleanName)) {
            return null;
        }

        $cleanName = mb_strtoupper(trim($cleanName), 'UTF-8');
        $rawName = $targetItem['csv_company'] ?? '';
        $alias = $targetItem['extracted_alias'] ?? null;

        $aliases = [];
        if (! empty($rawName) && mb_strtoupper($rawName, 'UTF-8') !== $cleanName) {
            $aliases[] = $rawName;
        }
        if (! empty($alias) && ! in_array($alias, $aliases, true)) {
            $aliases[] = $alias;
        }

        $client = Client::firstOrCreate(
            ['name' => $cleanName],
            ['aliases' => array_values(array_unique($aliases))]
        );

        // Update all items sharing this clean company across all tabs in cache
        $searchKey = $targetItem['clean_company'] ?? $targetItem['csv_company'];
        foreach (['full_matches', 'partial_matches', 'unmatched'] as $tk) {
            foreach ($cached[$tk] as $k => $item) {
                $itemComp = $item['clean_company'] ?? ($item['csv_company'] ?? '');
                if ($itemComp === $searchKey) {
                    $cached[$tk][$k]['resolved_client_id'] = $client->id;
                    $cached[$tk][$k]['resolved_client_name'] = $client->name;
                    $cached[$tk][$k]['parsed_data']['resolved_client_id'] = $client->id;
                    $cached[$tk][$k]['parsed_data']['resolved_client_name'] = $client->name;
                }
            }
        }

        $this->storeAnalysisCache($cached);

        return $client;
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
                $order = Order::find($orderId);
                if (! empty($parsed['raw_wo']) && $order && empty($order->wo_number)) {
                    $updateData['wo_number'] = 'WO '.$parsed['normalized_wo'];
                }

                // Client, Contact, and Location enrichment
                if ($order) {
                    if (! empty($parsed['resolved_client_id']) && empty($order->client_id)) {
                        $updateData['client_id'] = $parsed['resolved_client_id'];
                    }
                    if (! empty($parsed['extracted_contact']) && empty($order->responsible_person)) {
                        $updateData['responsible_person'] = $parsed['extracted_contact'];
                    }
                    if (! empty($parsed['extracted_location']) && empty($order->location_name)) {
                        $updateData['location_name'] = $parsed['extracted_location'];
                    }
                }

                // Auto-learn typos into client's aliases
                if (! empty($parsed['resolved_client_id']) && ! empty($parsed['typo_detected']) && ! empty($parsed['clean_company'])) {
                    $client = Client::find($parsed['resolved_client_id']);
                    if ($client) {
                        $term = $parsed['clean_company'];
                        $aliases = $client->aliases ?? [];
                        if (! in_array($term, $aliases, true) && mb_strtolower($term, 'UTF-8') !== mb_strtolower($client->name, 'UTF-8')) {
                            $aliases[] = $term;
                            $client->aliases = array_values(array_unique($aliases));
                            $client->save();
                        }
                    }
                }

                // Apply smart merged task name if proposed in diffs
                if (! empty($item['diffs']['task_name']['proposed'])) {
                    $updateData['task_name'] = $item['diffs']['task_name']['proposed'];
                }

                // Strictly ensure company_name is NEVER updated to preserve DB client name
                unset($updateData['company_name']);

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
     * Fail-safe deduplication inspector: Checks whether a Trello card or WO already exists in local DB.
     *
     * @return array{action_type: string, reason: string, order_id: ?int}
     */
    public function inspectTrelloCardDeduplication(string $cardId, ?string $rawWo, ?string $cleanCompany = null): array
    {
        $normWo = $this->normalizeWo($rawWo);

        // Level 1: Check if an Order already has this trello_card_id
        $existingByTrello = Order::where('trello_card_id', trim($cardId))->first();
        if ($existingByTrello) {
            return [
                'action_type' => 'reuse_existing',
                'reason' => "Esta tarjeta ya está vinculada a la orden #{$existingByTrello->id} (".($existingByTrello->wo_number ?: 'Sin WO previo').') en el sistema. Se actualizará sin duplicar.',
                'order_id' => $existingByTrello->id,
            ];
        }

        // Level 2: Check if an Order already exists with this exact WO
        if (! empty($normWo)) {
            $existingByWo = Order::where('wo_number', 'WO '.$normWo)
                ->orWhere('wo_number', $normWo)
                ->first();

            if ($existingByWo) {
                return [
                    'action_type' => 'reuse_existing',
                    'reason' => "Ya existe la orden #{$existingByWo->id} ({$existingByWo->wo_number}) en la base de datos. Se le asignará esta tarjeta de Trello sin duplicar.",
                    'order_id' => $existingByWo->id,
                ];
            }
        }

        // Level 3: 100% Brand New Order
        return [
            'action_type' => 'create_new',
            'reason' => 'Tarjeta no encontrada en la base de datos local. Se creará como nueva orden en Backlog.',
            'order_id' => null,
        ];
    }

    /**
     * Extracts candidate WO numbers (digits without leading zeros) from a Trello card.
     *
     * @param  array<string, mixed>  $card
     * @return array<string>
     */
    public function extractCandidateWosFromCard(array $card): array
    {
        $wos = [];
        $title = (string) ($card['name'] ?? '');
        $desc = (string) ($card['desc'] ?? '');

        // 1. Explicit WO / OT / # prefix in title: e.g. "WO 13919", "WO#13919", "OT 12345"
        if (preg_match_all('/(?:WO|W\.O\.|OT|O\.T\.|#)\s*[:#-]?\s*(\d{4,6})\b/i', $title, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = ltrim($m, '0');
                if ($clean !== '') {
                    $wos[$clean] = true;
                }
            }
        }

        // 2. Leading digits in title: e.g. "13919 - Nike", "13919 Nike"
        if (preg_match('/^\s*(\d{4,6})\b/', $title, $matches)) {
            $clean = ltrim($matches[1], '0');
            if ($clean !== '') {
                $wos[$clean] = true;
            }
        }

        // 3. Any 4-6 digit standalone number in title (filtering out common years 2020-2030 unless explicit)
        if (preg_match_all('/\b(\d{4,6})\b/', $title, $matches)) {
            foreach ($matches[1] as $m) {
                $num = (int) $m;
                if ($num >= 2020 && $num <= 2030) {
                    continue; // Skip probable year
                }
                $clean = ltrim($m, '0');
                if ($clean !== '') {
                    $wos[$clean] = true;
                }
            }
        }

        // 4. In description: only look for explicit WO / OT / # patterns
        if (preg_match_all('/(?:WO|W\.O\.|OT|O\.T\.|#|Work Order|Orden)\s*[:#-]?\s*(\d{4,6})\b/i', $desc, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = ltrim($m, '0');
                if ($clean !== '') {
                    $wos[$clean] = true;
                }
            }
        }

        return array_keys($wos);
    }

    /**
     * Picks the best candidate card from an array of matching Trello cards.
     * Prioritizes active (non-closed) cards, company name match in title, and recent activity.
     *
     * @param  array<array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    public function pickBestMatchingTrelloCard(array $cards, ?string $companyName = null): array
    {
        if (count($cards) === 1) {
            return $cards[0];
        }

        $cleanComp = mb_strtolower(trim($companyName ?? ''));

        $bestCard = $cards[0];
        $bestScore = -1.0;

        foreach ($cards as $card) {
            $score = 0.0;

            // Prioritize active cards (+100)
            if (empty($card['closed'])) {
                $score += 100.0;
            }

            // Company match in title (+50)
            if ($cleanComp !== '') {
                $cardTitle = mb_strtolower((string) ($card['name'] ?? ''));
                if (str_contains($cardTitle, $cleanComp)) {
                    $score += 50.0;
                }
            }

            // Tie breaker: most recent activity
            $lastActivity = ! empty($card['dateLastActivity']) ? strtotime($card['dateLastActivity']) : 0;
            $score += ($lastActivity / 10000000000.0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCard = $card;
            }
        }

        return $bestCard;
    }

    /**
     * Search Trello for a specific unmatched row using its WO number and apply fail-safe inspection.
     *
     * @return array{success: bool, preview?: array, message?: string}
     */
    public function searchTrelloForUnmatchedRow(string $rowId): array
    {
        @set_time_limit(60);

        $cached = $this->getCachedAnalysis();
        if (! $cached || empty($cached['unmatched'])) {
            return ['success' => false, 'message' => 'No hay análisis activo.'];
        }

        $target = null;
        foreach ($cached['unmatched'] as $item) {
            if ($item['row_id'] === $rowId) {
                $target = $item;
                break;
            }
        }

        if (! $target) {
            return ['success' => false, 'message' => 'Fila no encontrada en lista sin coincidencia.'];
        }

        $rawWo = $target['parsed_data']['raw_wo'] ?? ($target['raw_wo'] ?? '');
        $normWo = $this->normalizeWo($rawWo);

        if (empty($normWo)) {
            return ['success' => false, 'message' => 'Esta orden no tiene número de WO en el CSV para buscar en Trello.'];
        }

        $trelloService = app(TrelloSyncService::class);
        $bestCard = null;

        // 1. Fast lookup from cached board cards if available
        $boardRes = $trelloService->fetchAllBoardCardsForLookup();
        if ($boardRes['success'] && ! empty($boardRes['cards'])) {
            $candidates = [];
            foreach ($boardRes['cards'] as $card) {
                $wos = $this->extractCandidateWosFromCard($card);
                if (in_array($normWo, $wos, true)) {
                    $candidates[] = $card;
                }
            }
            if (! empty($candidates)) {
                $bestCard = $this->pickBestMatchingTrelloCard($candidates, $target['clean_company'] ?? '');
            }
        }

        // 2. Fallback to targeted search API if not found in board cards
        if (! $bestCard) {
            $res = $trelloService->searchCards($normWo);
            if ($res['success'] && ! empty($res['cards'])) {
                $cards = $res['cards'];
                $bestCard = $cards[0];
                foreach ($cards as $c) {
                    if (str_contains(strval($c['name'] ?? ''), $normWo)) {
                        $bestCard = $c;
                        break;
                    }
                }
            }
        }

        if (! $bestCard) {
            return ['success' => false, 'message' => "No se encontró ninguna tarjeta en Trello con el WO '{$normWo}'."];
        }

        $dedup = $this->inspectTrelloCardDeduplication($bestCard['id'], $normWo, $target['clean_company'] ?? '');

        $preview = [
            'id' => $bestCard['id'],
            'name' => $bestCard['name'] ?? 'Sin título',
            'desc' => $bestCard['desc'] ?? '',
            'url' => $bestCard['shortUrl'] ?? "https://trello.com/c/{$bestCard['id']}",
            'is_closed' => (bool) ($bestCard['closed'] ?? false),
            'dedup_action' => $dedup['action_type'],
            'dedup_reason' => $dedup['reason'],
            'existing_order_id' => $dedup['order_id'],
        ];

        // Store suggested card in cached item
        foreach ($cached['unmatched'] as $k => $item) {
            if ($item['row_id'] === $rowId) {
                $cached['unmatched'][$k]['suggested_trello_card'] = $preview;
                break;
            }
        }
        $this->storeAnalysisCache($cached);

        return [
            'success' => true,
            'preview' => $preview,
        ];
    }

    /**
     * Safely and instantly auto-searches Trello for all unmatched rows that have a WO number.
     * Uses bulk board card fetching (1 single HTTP call) indexed in-memory to prevent timeouts.
     */
    public function autoSearchTrelloForUnmatched(): array
    {
        @set_time_limit(120);

        $cached = $this->getCachedAnalysis();
        if (! $cached || empty($cached['unmatched'])) {
            return ['success' => false, 'message' => 'No hay órdenes sin coincidencia disponibles.'];
        }

        $trelloService = app(TrelloSyncService::class);
        $totalChecked = 0;
        $foundCount = 0;
        $reusedCount = 0;
        $newCount = 0;

        // 1. Fetch all board cards in 1 single HTTP request
        $boardResult = $trelloService->fetchAllBoardCardsForLookup();

        if ($boardResult['success'] && ! empty($boardResult['cards'])) {
            // High-speed in-memory indexing: [normalized_wo => [card, ...]]
            $cardsByWo = [];
            foreach ($boardResult['cards'] as $card) {
                $candidateWos = $this->extractCandidateWosFromCard($card);
                foreach ($candidateWos as $wo) {
                    $cardsByWo[$wo][] = $card;
                }
            }

            foreach ($cached['unmatched'] as $k => $item) {
                $rawWo = $item['parsed_data']['raw_wo'] ?? ($item['raw_wo'] ?? '');
                $normWo = $this->normalizeWo($rawWo);

                if (empty($normWo)) {
                    continue;
                }

                $totalChecked++;

                $matchedCards = $cardsByWo[$normWo] ?? [];
                if (! empty($matchedCards)) {
                    $bestCard = $this->pickBestMatchingTrelloCard($matchedCards, $item['clean_company'] ?? '');
                    $dedup = $this->inspectTrelloCardDeduplication($bestCard['id'], $normWo, $item['clean_company'] ?? '');

                    $preview = [
                        'id' => $bestCard['id'],
                        'name' => $bestCard['name'] ?? 'Sin título',
                        'desc' => $bestCard['desc'] ?? '',
                        'url' => $bestCard['shortUrl'] ?? "https://trello.com/c/{$bestCard['id']}",
                        'is_closed' => (bool) ($bestCard['closed'] ?? false),
                        'dedup_action' => $dedup['action_type'],
                        'dedup_reason' => $dedup['reason'],
                        'existing_order_id' => $dedup['order_id'],
                    ];

                    $cached['unmatched'][$k]['suggested_trello_card'] = $preview;
                    $foundCount++;

                    if ($dedup['action_type'] === 'reuse_existing') {
                        $reusedCount++;
                    } else {
                        $newCount++;
                    }
                }
            }
        } else {
            // Fallback for mocked test environments or if board cards endpoint is unavailable
            foreach ($cached['unmatched'] as $k => $item) {
                $rawWo = $item['parsed_data']['raw_wo'] ?? ($item['raw_wo'] ?? '');
                $normWo = $this->normalizeWo($rawWo);

                if (empty($normWo)) {
                    continue;
                }

                $totalChecked++;

                $res = $trelloService->searchCards($normWo);
                if ($res['success'] && ! empty($res['cards'])) {
                    $cards = $res['cards'];
                    $bestCard = $cards[0];
                    foreach ($cards as $c) {
                        if (str_contains(strval($c['name'] ?? ''), $normWo)) {
                            $bestCard = $c;
                            break;
                        }
                    }

                    $dedup = $this->inspectTrelloCardDeduplication($bestCard['id'], $normWo, $item['clean_company'] ?? '');

                    $preview = [
                        'id' => $bestCard['id'],
                        'name' => $bestCard['name'] ?? 'Sin título',
                        'desc' => $bestCard['desc'] ?? '',
                        'url' => $bestCard['shortUrl'] ?? "https://trello.com/c/{$bestCard['id']}",
                        'is_closed' => (bool) ($bestCard['closed'] ?? false),
                        'dedup_action' => $dedup['action_type'],
                        'dedup_reason' => $dedup['reason'],
                        'existing_order_id' => $dedup['order_id'],
                    ];

                    $cached['unmatched'][$k]['suggested_trello_card'] = $preview;
                    $foundCount++;

                    if ($dedup['action_type'] === 'reuse_existing') {
                        $reusedCount++;
                    } else {
                        $newCount++;
                    }
                }
            }
        }

        $this->storeAnalysisCache($cached);

        return [
            'success' => true,
            'total_checked' => $totalChecked,
            'found_count' => $foundCount,
            'reused_count' => $reusedCount,
            'new_count' => $newCount,
            'message' => "Búsqueda instantánea completada: Se revisaron {$totalChecked} órdenes con WO y se localizaron {$foundCount} tarjetas en Trello ({$reusedCount} para actualizar existentes, {$newCount} nuevas en Backlog).",
        ];
    }

    /**
     * Batch links and approves all unmatched rows where a Trello card has been found and previewed.
     */
    public function linkAllFoundTrelloCards(): array
    {
        $cached = $this->getCachedAnalysis();
        if (! $cached || empty($cached['unmatched'])) {
            return ['success' => false, 'message' => 'No hay órdenes sin coincidencia.'];
        }

        $foundRows = [];
        foreach ($cached['unmatched'] as $item) {
            if (! empty($item['suggested_trello_card'])) {
                $foundRows[] = $item;
            }
        }

        if (empty($foundRows)) {
            return ['success' => false, 'message' => 'No hay tarjetas de Trello localizadas pendientes de vincular.'];
        }

        $backupFile = $this->createSafetyBackup();
        $linkedOrderIds = [];
        $reusedCount = 0;
        $newCount = 0;

        DB::transaction(function () use ($foundRows, &$linkedOrderIds, &$reusedCount, &$newCount) {
            foreach ($foundRows as $item) {
                $card = $item['suggested_trello_card'];
                $parsed = $item['parsed_data'];
                $res = $this->linkTrelloCard($parsed, $card['id'], $card, isBatch: true);
                if ($res['success']) {
                    $linkedOrderIds[] = $res['order_id'];
                    if (! empty($res['is_new'])) {
                        $newCount++;
                    } else {
                        $reusedCount++;
                    }
                }
            }
        });

        // Record single consolidated migration history
        $this->recordMigrationHistory([
            'id' => 'mig_trello_batch_'.date('Ymd_His'),
            'type' => 'trello_batch_link',
            'user_id' => Auth::id() ?? 1,
            'user_name' => Auth::user()?->name ?? 'Admin',
            'updated_count' => count($linkedOrderIds),
            'order_ids' => $linkedOrderIds,
            'backup_file' => $backupFile,
            'created_at' => now()->toIso8601String(),
            'status' => 'applied',
        ]);

        // Remove linked items from cache
        $linkedRowIds = array_flip(array_column($foundRows, 'row_id'));
        $cached['unmatched'] = array_values(array_filter(
            $cached['unmatched'],
            fn ($i) => ! isset($linkedRowIds[$i['row_id']])
        ));
        $cached['meta']['unmatched_count'] = count($cached['unmatched']);
        $this->storeAnalysisCache($cached);

        return [
            'success' => true,
            'linked_count' => count($linkedOrderIds),
            'reused_count' => $reusedCount,
            'new_count' => $newCount,
            'message' => 'Se vincularon exitosamente '.count($linkedOrderIds)." órdenes ({$reusedCount} existentes actualizadas, {$newCount} nuevas en Backlog) sin duplicados.",
        ];
    }

    /**
     * Link an unmatched CSV row to a Trello card, verifying card and saving silently.
     */
    public function linkTrelloCard(array $parsedData, string $trelloCardId, array $cardDetails, bool $isBatch = false): array
    {
        $backupFile = null;
        if (! $isBatch) {
            $backupFile = $this->createSafetyBackup();
        }

        $cleanCardId = trim($trelloCardId);

        // Level 1: Find by trello_card_id
        $order = Order::where('trello_card_id', $cleanCardId)->first();

        // Level 2: Find by exact normalized WO
        if (! $order && ! empty($parsedData['normalized_wo'])) {
            $norm = $parsedData['normalized_wo'];
            $order = Order::where('wo_number', 'WO '.$norm)
                ->orWhere('wo_number', $norm)
                ->first();
        }

        $isNew = false;

        $saveOperation = function () use (&$order, &$isNew, $cleanCardId, $parsedData, $cardDetails) {
            $woNumber = ! empty($parsedData['normalized_wo']) ? ('WO '.$parsedData['normalized_wo']) : null;

            if ($order) {
                // Order already exists in DB with this Trello card or WO: update silently without duplicating!
                $updateData = [
                    'trello_card_id' => $cleanCardId,
                    'in_workspace' => false,
                ];

                if ($woNumber && empty($order->wo_number)) {
                    $updateData['wo_number'] = $woNumber;
                }
                if (! empty($parsedData['resolved_client_id']) && empty($order->client_id)) {
                    $updateData['client_id'] = $parsedData['resolved_client_id'];
                }
                if (! empty($parsedData['extracted_contact']) && empty($order->responsible_person)) {
                    $updateData['responsible_person'] = $parsedData['extracted_contact'];
                }
                if (! empty($parsedData['extracted_location']) && empty($order->location_name)) {
                    $updateData['location_name'] = $parsedData['extracted_location'];
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
                    'company_name' => $parsedData['clean_company'] ?: ($parsedData['company_name'] ?: ($cardDetails['name'] ?? 'Empresa')),
                    'client_id' => $parsedData['resolved_client_id'] ?? null,
                    'responsible_person' => $parsedData['extracted_contact'] ?? null,
                    'location_name' => $parsedData['extracted_location'] ?? null,
                    'task_name' => $parsedData['clean_task'] ?: ($parsedData['task_name'] ?: $cardName),
                    'wo_number' => $woNumber,
                    'in_workspace' => false, // strictly in backlog inbox
                    'core_status' => CoreStatus::ENTRANTE->value,
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
        };

        if ($isBatch) {
            $saveOperation();
        } else {
            DB::transaction($saveOperation);

            // Record migration history only for standalone links
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
        }

        return [
            'success' => true,
            'is_new' => $isNew,
            'order_id' => $order->id,
            'order_wo' => $order->wo_number,
            'message' => $isNew
                ? "Orden #{$order->id} creada y vinculada con Trello exitosamente."
                : "Orden existente #{$order->id} (".($order->wo_number ?: 'Sin WO previo').') actualizada y vinculada con Trello sin duplicar.',
        ];
    }

    /**
     * Create safety SQLite snapshot backup prior to any batch changes.
     */
    public function createSafetyBackup(): string
    {
        $backupDir = config('database.backup_path', storage_path('app/backups'));
        File::ensureDirectoryExists($backupDir);

        $timestamp = date('Y-m-d_His').'_'.substr(bin2hex(random_bytes(4)), 0, 6);
        $filename = "reconciliation_pre_migration_{$timestamp}.sqlite";
        $targetPath = "{$backupDir}/{$filename}";

        $dbPath = config('database.connections.sqlite.database');
        if ($dbPath && File::exists($dbPath)) {
            try {
                // VACUUM INTO is SQLite's built-in atomic online backup that avoids file locking
                DB::connection('sqlite')->statement("VACUUM INTO '{$targetPath}'");
            } catch (\Throwable $e) {
                File::copy($dbPath, $targetPath);
            }
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
    public function storeAnalysisCache(array $data): void
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
     * @return array{items: array, total: int, displayed: int, hasMore: bool, totalPages: int, unmatchedCounts: array}
     */
    public function getTabRows(
        string $tab,
        string $search = '',
        int|string $perPage = 50,
        int $page = 1,
        string $unmatchedFilter = 'all',
        array $trelloCardPreviews = []
    ): array {
        $cached = $this->getCachedAnalysis();
        if (! $cached) {
            return [
                'items' => [],
                'total' => 0,
                'displayed' => 0,
                'hasMore' => false,
                'totalPages' => 1,
                'unmatchedCounts' => ['total' => 0, 'with_match' => 0, 'without_match' => 0],
            ];
        }

        $source = match ($tab) {
            'full_match' => $cached['full_matches'] ?? [],
            'partial_match' => $cached['partial_matches'] ?? [],
            'unmatched' => $cached['unmatched'] ?? [],
            default => [],
        };

        $unmatchedCounts = [
            'total' => count($cached['unmatched'] ?? []),
            'with_match' => 0,
            'without_match' => 0,
        ];

        if ($tab === 'unmatched') {
            foreach ($source as $item) {
                $hasMatch = ! empty($item['suggested_trello_card']) || ! empty($trelloCardPreviews[$item['row_id']]);
                if ($hasMatch) {
                    $unmatchedCounts['with_match']++;
                } else {
                    $unmatchedCounts['without_match']++;
                }
            }

            if ($unmatchedFilter === 'with_match') {
                $source = array_values(array_filter($source, function ($item) use ($trelloCardPreviews) {
                    return ! empty($item['suggested_trello_card']) || ! empty($trelloCardPreviews[$item['row_id']]);
                }));
            } elseif ($unmatchedFilter === 'without_match') {
                $source = array_values(array_filter($source, function ($item) use ($trelloCardPreviews) {
                    return empty($item['suggested_trello_card']) && empty($trelloCardPreviews[$item['row_id']]);
                }));
            }
        }

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
                'unmatchedCounts' => $unmatchedCounts,
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
            'unmatchedCounts' => $unmatchedCounts,
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
