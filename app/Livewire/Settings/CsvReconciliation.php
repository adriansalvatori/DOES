<?php

namespace App\Livewire\Settings;

use App\Services\OrderReconciliationService;
use App\Services\TrelloSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class CsvReconciliation extends Component
{
    use WithFileUploads;

    // File Upload
    public $csvFile = null;

    // Active Section / Tab (persisted across page reloads via URL query param)
    #[Url(as: 'tab')]
    public string $activeTab = 'full_match'; // 'full_match', 'partial_match', 'unmatched', 'history'

    // Unmatched / Trello Filter
    #[Url(as: 'trello_filter')]
    public string $unmatchedFilter = 'all'; // 'all', 'with_match', 'without_match'

    // Filtering & Search
    public string $search = '';

    public int $threshold = 75; // 75% default

    public int|string $perPage = 50; // 50, 100, 250, 'all'

    // Lightweight Analysis State (Only metadata stored in Livewire payload!)
    public bool $hasAnalysis = false;

    public ?array $meta = null;

    // Selections (only row IDs)
    public array $selectedFull = [];

    public bool $selectAllFull = true;

    public array $selectedPartial = [];

    // WO Conflict Reassignment State
    public array $reassignWoInputs = [];

    public array $activeReassignRows = [];

    // Trello Input & Verification State for Unmatched Rows
    public array $trelloInput = [];

    public array $trelloCardPreview = [];

    public array $trelloChecking = [];

    public array $trelloError = [];

    public bool $isSearchingTrello = false;

    // Modals
    public bool $showBatchConfirmModal = false;

    public string $batchActionType = ''; // 'all_full', 'selected_full', 'selected_partial'

    public bool $showRollbackModal = false;

    // Messages
    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, __('You don\'t have permission to access this section. This tool is for administrators only.'));
        }

        // Load existing analysis metadata if available
        $service = app(OrderReconciliationService::class);
        $meta = $service->getAnalysisMeta();
        if ($meta) {
            $this->meta = $meta;
            $this->hasAnalysis = true;
            $cached = $service->getCachedAnalysis();
            foreach ($cached['unmatched'] ?? [] as $item) {
                if (! empty($item['suggested_trello_card'])) {
                    $this->trelloCardPreview[$item['row_id']] = $item['suggested_trello_card'];
                    $this->trelloInput[$item['row_id']] = $item['suggested_trello_card']['url'] ?? '';
                }
            }

            // If active tab is full_match and has 0 records, automatically route to the first tab with pending records
            if (! request()->has('tab') && $this->activeTab === 'full_match' && ($this->meta['full_match_count'] ?? 0) === 0) {
                if (($this->meta['partial_match_count'] ?? 0) > 0) {
                    $this->activeTab = 'partial_match';
                } elseif (($this->meta['unmatched_count'] ?? 0) > 0) {
                    $this->activeTab = 'unmatched';
                }
            }
        }
    }

    public function updatedCsvFile(): void
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:30720', // Up to 30MB
        ]);

        $this->analyzeUploadedCsv();
    }

    public function analyzeUploadedCsv(): void
    {
        $this->resetMessages();

        if (! $this->csvFile) {
            $this->errorMessage = __('Please select a valid CSV file.');

            return;
        }

        try {
            $service = app(OrderReconciliationService::class);
            $tempPath = $this->csvFile->getRealPath();

            $similarityFloat = $this->threshold / 100.0;
            $analysisResult = $service->analyzeCsv($tempPath, $similarityFloat);

            $this->meta = $analysisResult['meta'];
            $this->hasAnalysis = true;
            $this->perPage = 50;
            $this->selectedFull = [];
            $this->selectedPartial = [];
            $this->selectAllFull = true;

            // Reset Trello inspection state to prevent stale previews across file uploads
            $this->trelloInput = [];
            $this->trelloCardPreview = [];
            $this->trelloChecking = [];
            $this->trelloError = [];

            // Pick the most relevant tab to show first
            if (($this->meta['full_match_count'] ?? 0) > 0) {
                $this->activeTab = 'full_match';
            } elseif (($this->meta['partial_match_count'] ?? 0) > 0) {
                $this->activeTab = 'partial_match';
            } else {
                $this->activeTab = 'unmatched';
            }

            $total = $this->meta['total_rows'] ?? 0;
            $this->successMessage = __('File analyzed successfully. :count rows were processed.', ['count' => $total]);
        } catch (\Throwable $e) {
            $this->errorMessage = __('Error analyzing the CSV file: :error', ['error' => $e->getMessage()]);
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        if ($this->perPage === 'all' || (int) $this->perPage > 250) {
            $this->perPage = 50;
        }
    }

    public function setUnmatchedFilter(string $filter): void
    {
        $this->unmatchedFilter = in_array($filter, ['all', 'with_match', 'without_match'], true) ? $filter : 'all';
        if ($this->perPage === 'all' || (int) $this->perPage > 250) {
            $this->perPage = 50;
        }
    }

    public function updatedSearch(): void
    {
        // Keep current view clean
    }

    public function toggleSelectAllFull(): void
    {
        $this->selectAllFull = ! $this->selectAllFull;
        if (! $this->selectAllFull) {
            $this->selectedFull = [];
        }
    }

    public function openBatchConfirm(string $type): void
    {
        $this->batchActionType = $type;
        $this->showBatchConfirmModal = true;
    }

    public function closeBatchConfirm(): void
    {
        $this->showBatchConfirmModal = false;
        $this->batchActionType = '';
    }

    public function executeBatchApproval(): void
    {
        $this->resetMessages();
        $this->showBatchConfirmModal = false;

        $service = app(OrderReconciliationService::class);

        $selectedIds = match ($this->batchActionType) {
            'selected_full' => $this->selectedFull,
            'selected_partial' => $this->selectedPartial,
            default => [],
        };

        $result = $service->applyBatchAndSyncCache($this->batchActionType, $selectedIds);

        if ($result['success']) {
            $this->successMessage = $result['message'];
            $this->dispatch('toast', message: $result['message']);
            $this->meta = $service->getAnalysisMeta();

            if (in_array($this->batchActionType, ['all_full', 'selected_full'], true)) {
                $this->selectedFull = [];
                $this->selectAllFull = false;
            } else {
                $this->selectedPartial = [];
            }
        } else {
            $this->errorMessage = $result['message'] ?? __('An error occurred while processing the batch.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');
        }
    }

    public function approveSinglePartial(string $rowId): void
    {
        $this->resetMessages();

        $service = app(OrderReconciliationService::class);
        $result = $service->applySinglePartialAndSyncCache($rowId);

        if ($result['success']) {
            $msg = __('Order approved and updated successfully.');
            $this->successMessage = $msg;
            $this->dispatch('toast', message: $msg);
            $this->meta = $service->getAnalysisMeta();
            $this->selectedPartial = array_values(array_diff($this->selectedPartial, [$rowId]));
        } else {
            $this->errorMessage = $result['message'] ?? __('Error updating the order.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');
        }
    }

    public function discardPartialMatch(string $rowId): void
    {
        $service = app(OrderReconciliationService::class);
        if ($service->discardPartialAndSyncCache($rowId)) {
            $this->meta = $service->getAnalysisMeta();
            $this->selectedPartial = array_values(array_diff($this->selectedPartial, [$rowId]));
            $msg = __('Match discarded and moved to No Match to link via Trello.');
            $this->successMessage = $msg;
            $this->dispatch('toast', message: $msg);
        }
    }

    public function toggleReassignWo(string $rowId): void
    {
        $this->resetMessages();
        if (isset($this->activeReassignRows[$rowId])) {
            unset($this->activeReassignRows[$rowId]);
        } else {
            $this->activeReassignRows[$rowId] = true;
            if (! isset($this->reassignWoInputs[$rowId])) {
                $this->reassignWoInputs[$rowId] = '';
            }
        }
    }

    public function executeReassignWo(string $rowId): void
    {
        $this->resetMessages();
        $newWo = $this->reassignWoInputs[$rowId] ?? null;

        $service = app(OrderReconciliationService::class);
        $result = $service->resolveWoConflictAndCreateNewOrder($rowId, $newWo);

        if ($result['success']) {
            $this->successMessage = $result['message'];
            $this->dispatch('toast', message: $result['message']);
            $this->meta = $service->getAnalysisMeta();
            unset($this->activeReassignRows[$rowId], $this->reassignWoInputs[$rowId]);
            $this->selectedPartial = array_values(array_diff($this->selectedPartial, [$rowId]));
        } else {
            $this->errorMessage = $result['message'];
            $this->dispatch('toast', message: $result['message'], type: 'error');
        }
    }

    public function quickCreateClient(string $rowId): void
    {
        $this->resetMessages();
        $service = app(OrderReconciliationService::class);
        $client = $service->quickCreateClientFromRow($rowId);

        if ($client) {
            $msg = __('Client ":name" registered successfully in the official Kudos catalog.', ['name' => $client->name]);
            $this->successMessage = $msg;
            $this->dispatch('toast', message: $msg);
            $this->meta = $service->getAnalysisMeta();
        } else {
            $this->errorMessage = __('Could not create the client for this order.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');
        }
    }

    public function checkTrelloCard(string $rowId): void
    {
        $this->resetMessages();
        $this->trelloChecking[$rowId] = true;
        $this->trelloError[$rowId] = null;
        $this->trelloCardPreview[$rowId] = null;

        $url = trim($this->trelloInput[$rowId] ?? '');
        if (empty($url)) {
            $this->trelloError[$rowId] = __('Please enter the Trello card URL.');
            $this->trelloChecking[$rowId] = false;

            return;
        }

        $cardId = null;
        if (preg_match('/trello\.com\/c\/([a-zA-Z0-9]+)/i', $url, $matches)) {
            $cardId = $matches[1];
        } elseif (preg_match('/^[a-zA-Z0-9]{8,24}$/', $url)) {
            $cardId = $url;
        }

        if (! $cardId) {
            $this->trelloError[$rowId] = __('Invalid Trello URL format. Example: https://trello.com/c/5x8bZ9q1');
            $this->trelloChecking[$rowId] = false;

            return;
        }

        try {
            $trelloService = app(TrelloSyncService::class);
            $res = $trelloService->getCardDetails($cardId);

            if ($res['success'] && ! empty($res['card'])) {
                $card = $res['card'];
                $service = app(OrderReconciliationService::class);
                $cached = $service->getCachedAnalysis();
                $targetItem = collect($cached['unmatched'] ?? [])->firstWhere('row_id', $rowId);
                $rawWo = $targetItem['parsed_data']['raw_wo'] ?? ($targetItem['raw_wo'] ?? '');
                $cleanComp = $targetItem['clean_company'] ?? ($targetItem['csv_company'] ?? '');
                $dedup = $service->inspectTrelloCardDeduplication($card['id'], $rawWo, $cleanComp);

                $this->trelloCardPreview[$rowId] = [
                    'id' => $card['id'],
                    'name' => $card['name'] ?? __('Untitled'),
                    'desc' => $card['desc'] ?? '',
                    'url' => $card['shortUrl'] ?? "https://trello.com/c/{$card['id']}",
                    'is_closed' => (bool) ($card['closed'] ?? false),
                    'dedup_action' => $dedup['action_type'],
                    'dedup_reason' => $dedup['reason'],
                    'existing_order_id' => $dedup['order_id'],
                ];
            } else {
                $this->trelloError[$rowId] = __('Could not get the card information from Trello. Check the permissions or the link.');
            }
        } catch (\Throwable $e) {
            $this->trelloError[$rowId] = __('Error querying Trello: :error', ['error' => $e->getMessage()]);
        } finally {
            $this->trelloChecking[$rowId] = false;
        }
    }

    public function searchSingleTrelloCard(string $rowId): void
    {
        $this->resetMessages();
        $this->trelloChecking[$rowId] = true;
        $this->trelloError[$rowId] = null;

        try {
            $service = app(OrderReconciliationService::class);
            $res = $service->searchTrelloForUnmatchedRow($rowId);
            if ($res['success']) {
                $this->trelloCardPreview[$rowId] = $res['preview'];
                $this->trelloInput[$rowId] = $res['preview']['url'];
            } else {
                $this->trelloError[$rowId] = $res['message'] ?? __('No card found in Trello.');
            }
        } catch (\Throwable $e) {
            $this->trelloError[$rowId] = __('Error searching Trello: :error', ['error' => $e->getMessage()]);
        } finally {
            $this->trelloChecking[$rowId] = false;
        }
    }

    public function autoSearchAllTrello(): void
    {
        @set_time_limit(120);
        $this->resetMessages();
        $this->isSearchingTrello = true;

        try {
            $service = app(OrderReconciliationService::class);
            $res = $service->autoSearchTrelloForUnmatched();

            if ($res['success']) {
                $this->successMessage = $res['message'];
                $cached = $service->getCachedAnalysis();
                foreach ($cached['unmatched'] ?? [] as $item) {
                    if (! empty($item['suggested_trello_card'])) {
                        $this->trelloCardPreview[$item['row_id']] = $item['suggested_trello_card'];
                        $this->trelloInput[$item['row_id']] = $item['suggested_trello_card']['url'] ?? '';
                    }
                }

                if (($res['found_count'] ?? 0) > 0) {
                    $this->unmatchedFilter = 'with_match';
                }
            } else {
                $this->errorMessage = $res['message'] ?? __('Error during the Trello search.');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = __('Error querying Trello: :error', ['error' => $e->getMessage()]);
        } finally {
            $this->isSearchingTrello = false;
        }
    }

    public function linkAllFoundTrello(): void
    {
        $this->resetMessages();
        $service = app(OrderReconciliationService::class);
        $res = $service->linkAllFoundTrelloCards();

        if ($res['success']) {
            $this->successMessage = $res['message'];
            $this->dispatch('toast', message: $res['message']);
            $this->meta = $service->getAnalysisMeta();
            $this->trelloCardPreview = [];
            $this->unmatchedFilter = 'all';
        } else {
            $this->errorMessage = $res['message'] ?? __('Error linking Trello cards.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');
        }
    }

    public function linkAndApproveTrello(string $rowId): void
    {
        $this->resetMessages();

        $preview = $this->trelloCardPreview[$rowId] ?? null;
        if (! $preview || empty($preview['id'])) {
            $this->errorMessage = __('Please verify the Trello card before linking.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');

            return;
        }

        $service = app(OrderReconciliationService::class);
        $result = $service->linkTrelloAndSyncCache($rowId, $preview['id'], $preview);

        if ($result['success']) {
            $this->successMessage = $result['message'];
            $this->dispatch('toast', message: $result['message']);
            $this->meta = $service->getAnalysisMeta();
            unset($this->trelloInput[$rowId], $this->trelloCardPreview[$rowId]);
        } else {
            $this->errorMessage = $result['message'] ?? __('Error linking with Trello.');
            $this->dispatch('toast', message: $this->errorMessage, type: 'error');
        }
    }

    public function triggerRollback(): void
    {
        $this->resetMessages();
        $this->showRollbackModal = false;

        $service = app(OrderReconciliationService::class);
        $result = $service->rollbackLastMigration();

        if ($result['success']) {
            $this->successMessage = $result['message'];
            $this->meta = $service->getAnalysisMeta();
        } else {
            $this->errorMessage = $result['message'];
        }
    }

    public function clearCurrentAnalysis(): void
    {
        $service = app(OrderReconciliationService::class);
        $service->clearAnalysisCache();

        $this->hasAnalysis = false;
        $this->meta = null;
        $this->csvFile = null;
        $this->resetMessages();
        $this->selectedFull = [];
        $this->selectedPartial = [];
    }

    public function loadMore(): void
    {
        if ($this->perPage === 'all') {
            return;
        }

        $this->perPage = ((int) $this->perPage) + 50;
    }

    public function setPerPage(int|string $val): void
    {
        $this->perPage = $val;
    }

    public function getMigrationHistoryProperty(): array
    {
        return app(OrderReconciliationService::class)->getMigrationHistory();
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render(): View
    {
        $service = app(OrderReconciliationService::class);
        $tabData = $service->getTabRows(
            $this->activeTab,
            $this->search,
            $this->perPage,
            1,
            $this->unmatchedFilter,
            $this->trelloCardPreview
        );

        return view('livewire.settings.csv-reconciliation', [
            'paginatedRows' => $tabData['items'],
            'totalCount' => $tabData['total'],
            'displayedCount' => $tabData['displayed'],
            'hasMore' => $tabData['hasMore'],
            'filteredCount' => $tabData['total'],
            'totalPages' => $tabData['totalPages'],
            'migrationHistory' => $this->migrationHistory,
            'unmatchedCounts' => $tabData['unmatchedCounts'] ?? ['total' => 0, 'with_match' => 0, 'without_match' => 0],
        ])->title(__('CSV Reconciliation'));
    }
}
