<?php

namespace App\Livewire\Overview;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OverviewIndex extends Component
{
    use WithPagination;

    public function mount(): mixed
    {
        $user = Auth::user();
        if ($user && $user->isDesigner()) {
            return redirect()->route('kanban');
        }

        return null;
    }

    #[Computed]
    public function metrics(): array
    {
        $result = Order::query()->selectRaw("
            COUNT(CASE WHEN in_workspace = 1 AND core_status != 'ARCHIVED' THEN 1 END) as totalWorkspaceCount,
            COUNT(CASE WHEN in_workspace = 0 AND core_status != 'ARCHIVED' THEN 1 END) as totalBacklogCount,
            COUNT(CASE WHEN core_status = 'ARCHIVED' THEN 1 END) as totalArchivedCount,
            COUNT(CASE WHEN core_status = 'ARCHIVED' AND substatus = 'FINALIZADA !' THEN 1 END) as archivedFinalizadaCount,
            COUNT(CASE WHEN core_status = 'ARCHIVED' AND substatus IN ('CANCELADA', 'CANCELADA POR CLIENTE', 'CANCELADA POR CAMILA', 'NO REALIZADA / TRANSFERIDA') THEN 1 END) as archivedCanceladaCount,
            COUNT(CASE WHEN core_status = 'ARCHIVED' AND substatus = 'CLIENTE NO RESPONSIVE' THEN 1 END) as archivedNoResponsiveCount,
            COUNT(CASE WHEN in_workspace = 1 AND (wo_number IS NULL OR wo_number = '' OR wo_number LIKE 'WO 00%') THEN 1 END) as missingWoCount,
            COUNT(CASE WHEN core_status = 'EN PRODUCCIÓN' THEN 1 END) as inProductionCount,
            COUNT(CASE WHEN in_workspace = 1 AND core_status = 'EN PRODUCCIÓN' THEN 1 END) as inWorkspaceProductionCount,
            COUNT(CASE WHEN in_workspace = 1 AND done_today = 1 THEN 1 END) as doneTodayCount
        ")->first();

        return [
            'totalWorkspaceCount' => (int) ($result->totalWorkspaceCount ?? 0),
            'totalBacklogCount' => (int) ($result->totalBacklogCount ?? 0),
            'totalArchivedCount' => (int) ($result->totalArchivedCount ?? 0),
            'archivedFinalizadaCount' => (int) ($result->archivedFinalizadaCount ?? 0),
            'archivedCanceladaCount' => (int) ($result->archivedCanceladaCount ?? 0),
            'archivedNoResponsiveCount' => (int) ($result->archivedNoResponsiveCount ?? 0),
            'missingWoCount' => (int) ($result->missingWoCount ?? 0),
            'inProductionCount' => (int) ($result->inProductionCount ?? 0),
            'inWorkspaceProductionCount' => (int) ($result->inWorkspaceProductionCount ?? 0),
            'doneTodayCount' => (int) ($result->doneTodayCount ?? 0),
        ];
    }

    #[On('order-updated')]
    #[On('order-created')]
    #[On('order-deleted')]
    public function handleOrderUpdated(): void
    {
        $this->clearOverviewCache();
    }

    public function clearOverviewCache(): void
    {
        unset($this->metrics);
        if (! Cache::store('file')->has('overview_cache_version')) {
            Cache::store('file')->forever('overview_cache_version', 1);
        }
        Cache::store('file')->increment('overview_cache_version');
    }

    public function refreshCache(): void
    {
        $this->clearOverviewCache();
        $this->dispatch('toast', message: __('Caché en disco renovado exitosamente.'));
    }

    // Filters
    public string $search = '';

    public string $filterWo = '';

    public string $filterClient = '';

    public string $filterDesigner = '';

    public string $filterReviewStatus = '';

    public string $filterInstallation = '';

    public string $filterDateRange = '';

    // Sorting
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // Active View Tab (all, workspace, production, backlog, archived)
    public string $activeTab = 'all';

    public string $archivedSubstatus = 'all';

    // Pagination / Chunked Loading (batches of 100 in background)
    public int $perPage = 0;

    public int $loadedCount = 100;

    public const CHUNK_SIZE = 100;

    public function loadNextChunk(): void
    {
        $this->loadedCount += self::CHUNK_SIZE;
    }

    // Section Visibility Toggles
    public bool $showWorkspace = true;

    public bool $showBacklog = true;

    // Inline Editing State
    public ?int $editingOrderId = null;

    public ?string $editingField = null;

    public mixed $editingValue = null;

    protected $queryString = [
        'activeTab' => ['except' => 'all'],
        'archivedSubstatus' => ['except' => 'all'],
        'perPage' => ['except' => 0],
        'search' => ['except' => ''],
        'filterWo' => ['except' => ''],
        'filterClient' => ['except' => ''],
        'filterDesigner' => ['except' => ''],
        'filterReviewStatus' => ['except' => ''],
        'filterInstallation' => ['except' => ''],
        'filterDateRange' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterWo(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterClient(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterDesigner(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterReviewStatus(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterInstallation(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingFilterDateRange(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function setTab(string $tab, string $substatus = 'all'): void
    {
        if (in_array($tab, ['all', 'workspace', 'production', 'backlog', 'archived'], true)) {
            $this->activeTab = $tab;
            if ($tab === 'archived') {
                $this->archivedSubstatus = $substatus;
            } else {
                $this->archivedSubstatus = 'all';
            }
            $this->loadedCount = self::CHUNK_SIZE;
            $this->resetPage();
        }
    }

    public function setArchivedSubstatus(string $substatus): void
    {
        $this->activeTab = 'archived';
        $this->archivedSubstatus = $substatus;
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function toggleSection(string $section): void
    {
        if ($section === 'workspace') {
            $this->showWorkspace = ! $this->showWorkspace;
        } elseif ($section === 'backlog') {
            $this->showBacklog = ! $this->showBacklog;
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'filterWo',
            'filterClient',
            'filterDesigner',
            'filterReviewStatus',
            'filterInstallation',
            'filterDateRange',
            'archivedSubstatus',
            'sortBy',
            'sortDirection',
            'perPage',
        ]);
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    // Inline Editing Handlers
    public function startEdit(int $orderId, string $field): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $this->editingOrderId = $orderId;
        $this->editingField = $field;

        $this->editingValue = match ($field) {
            'wo_number' => $order->wo_number,
            'task_name' => $order->task_name ?: $order->trello_title,
            'client_id' => $order->client_id,
            'company_name' => $order->company_name,
            'designer_id' => $order->designer_id ?? $order->primary_designer?->id,
            'manual_creation_date' => $order->manual_creation_date ? $order->manual_creation_date->format('Y-m-d') : ($order->created_at ? $order->created_at->format('Y-m-d') : ''),
            'production_sent_at' => $order->production_sent_at ? $order->production_sent_at->format('Y-m-d') : '',
            'email_date' => $order->email_date ? $order->email_date->format('Y-m-d') : '',
            'production_note' => $order->production_note,
            'estimate_invoice_number' => $order->estimate_invoice_number,
            'review_status' => $order->review_status,
            'installation_type' => $order->installation_type,
            'delivery_note' => $order->delivery_note,
            default => null,
        };
    }

    public function cancelEdit(): void
    {
        $this->editingOrderId = null;
        $this->editingField = null;
        $this->editingValue = null;
    }

    public function saveEdit(): void
    {
        if (! $this->editingOrderId || ! $this->editingField) {
            return;
        }

        $order = Order::find($this->editingOrderId);
        if (! $order) {
            $this->cancelEdit();

            return;
        }

        $field = $this->editingField;
        $value = $this->editingValue;

        switch ($field) {
            case 'wo_number':
                $cleanWo = trim((string) $value);
                if (! empty($cleanWo) && ! str_starts_with(strtoupper($cleanWo), 'WO')) {
                    $cleanWo = 'WO '.$cleanWo;
                }
                $order->update(['wo_number' => $cleanWo]);
                break;

            case 'task_name':
                $order->update(['task_name' => trim((string) $value)]);
                break;

            case 'company_name':
                $order->update(['company_name' => trim((string) $value)]);
                break;

            case 'manual_creation_date':
                $order->update(['manual_creation_date' => ! empty($value) ? $value : null]);
                break;

            case 'production_sent_at':
                $order->update(['production_sent_at' => ! empty($value) ? $value : null]);
                break;

            case 'email_date':
                $order->update(['email_date' => ! empty($value) ? $value : null]);
                break;

            case 'production_note':
                $order->update(['production_note' => trim((string) $value)]);
                break;

            case 'estimate_invoice_number':
                $order->update(['estimate_invoice_number' => trim((string) $value)]);
                break;

            case 'delivery_note':
                $order->update(['delivery_note' => trim((string) $value)]);
                break;
        }

        $this->syncTrelloAndLog($order, 'ORDER_UPDATED', "Campo {$field} actualizado a: {$value}");
        $this->clearOverviewCache();
        $this->cancelEdit();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Orden actualizada exitosamente.'));
    }

    public function updateReviewStatus(int $orderId, ?string $status): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->update(['review_status' => $status]);
        $this->clearOverviewCache();
        $this->syncTrelloAndLog($order, 'REVIEW_STATUS_CHANGED', 'Revisión actualizada: '.($status ?? 'Ninguna'));
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Estado de revisión actualizado.'));
    }

    public function updateInstallationType(int $orderId, ?string $type): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->update(['installation_type' => $type]);
        $this->clearOverviewCache();
        $this->syncTrelloAndLog($order, 'INSTALLATION_CHANGED', 'Tipo de instalación actualizado: '.($type ?? 'Vacío'));
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Instalación actualizada.'));
    }

    public function updateSubstatus(int $orderId, ?string $substatusValue): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $substatus = $substatusValue ? (Substatus::tryFrom($substatusValue) ?? $substatusValue) : null;

        if ($substatus instanceof Substatus && $substatus->isGlobal()) {
            $order->toggleFlag($substatus);
            $order->save();
            $label = $substatus->label();
            $this->syncTrelloAndLog($order, 'FLAG_TOGGLED', 'Bandera actualizada: '.$label);
        } else {
            $order->update(['substatus' => $substatus]);
            $label = $substatus instanceof Substatus ? $substatus->label() : ($substatusValue ?? 'Ninguno');
            $this->syncTrelloAndLog($order, 'SUBSTATUS_CHANGED', 'Subestatus actualizado a: '.$label);
        }

        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Subestatus / Bandera actualizada.'));
    }

    public function toggleFlag(int $orderId, string $flagName): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->toggleFlag($flagName);
        $order->save();

        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Bandera actualizada.'));
    }

    public function toggleOverviewChecked(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $newVal = ! (bool) $order->overview_checked;
        $order->update(['overview_checked' => $newVal]);
        $this->clearOverviewCache();
        $this->dispatch('order-updated');
    }

    public function updateDesigner(int $orderId, ?int $designerId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        if ($designerId) {
            $order->syncDesigners([$designerId]);
        } else {
            $order->designers()->detach();
            $order->update(['designer_id' => null]);
        }

        $this->syncTrelloAndLog($order, 'DESIGNER_ASSIGNED', 'Diseñador asignado actualizado');
        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Diseñador actualizado.'));
    }

    public function moveToWorkspace(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->update(['in_workspace' => true]);
        $this->syncTrelloAndLog($order, 'MOVED_TO_WORKSPACE', 'Orden movida al workspace activo');
        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Orden movida al Workspace activo.'));
    }

    public function moveToBacklog(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->update(['in_workspace' => false]);
        $this->syncTrelloAndLog($order, 'MOVED_TO_BACKLOG', 'Orden movida al backlog');
        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Orden movida al Backlog.'));
    }

    private function syncTrelloAndLog(Order $order, string $eventType, string $description): void
    {
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => $eventType,
            'actor' => auth()->user()?->name ?? 'Sistema',
            'metadata' => ['description' => $description],
        ]);

        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
                // Ignore network error silently
            }
        }
    }

    private function applyFilters($query)
    {
        if (! empty(trim($this->search))) {
            $query->search($this->search);
        }

        if (! empty(trim($this->filterWo))) {
            $term = trim($this->filterWo);
            if (strtolower($term) === 'sin wo' || strtolower($term) === 'missing') {
                $query->where(function ($q) {
                    $q->whereNull('wo_number')
                        ->orWhere('wo_number', '')
                        ->orWhere('wo_number', 'like', 'WO 00%');
                });
            } else {
                $query->where('wo_number', 'like', '%'.$term.'%');
            }
        }

        if (! empty(trim($this->filterClient))) {
            $query->where(function ($q) {
                $q->where('client_id', $this->filterClient)
                    ->orWhere('company_name', 'like', '%'.$this->filterClient.'%');
            });
        }

        if (! empty(trim($this->filterDesigner))) {
            $dId = (int) $this->filterDesigner;
            $query->where(function ($q) use ($dId) {
                $q->where('designer_id', $dId)
                    ->orWhereHas('designers', fn ($dq) => $dq->where('designers.id', $dId));
            });
        }

        if (! empty(trim($this->filterReviewStatus))) {
            if ($this->filterReviewStatus === 'NONE') {
                $query->whereNull('review_status');
            } else {
                $query->where('review_status', $this->filterReviewStatus);
            }
        }

        if (! empty(trim($this->filterInstallation))) {
            if ($this->filterInstallation === 'NONE') {
                $query->whereNull('installation_type');
            } else {
                $query->where('installation_type', $this->filterInstallation);
            }
        }

        if (! empty(trim($this->filterDateRange))) {
            match ($this->filterDateRange) {
                'today' => $query->whereDate('created_at', now()->toDateString()),
                'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                default => null,
            };
        }

        // Sorting
        $allowedColumns = [
            'created_at',
            'manual_creation_date',
            'production_sent_at',
            'email_date',
            'wo_number',
            'company_name',
            'task_name',
            'review_status',
            'installation_type',
            'substatus',
        ];
        $sortCol = in_array($this->sortBy, $allowedColumns, true) ? $this->sortBy : 'created_at';
        $dir = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortCol, $dir);
    }

    public function render()
    {
        // Build query exclusively for the active tab (lazy loading)
        $baseQuery = match ($this->activeTab) {
            'workspace' => Order::query()->inWorkspace(),
            'production' => Order::query()->where('core_status', CoreStatus::EN_PRODUCCION),
            'archived' => (function () {
                $query = Order::query()->archived();
                if ($this->archivedSubstatus !== 'all') {
                    match ($this->archivedSubstatus) {
                        'finalizada', Substatus::FINALIZADA->value => $query->where('substatus', Substatus::FINALIZADA->value),
                        'cancelada' => $query->whereIn('substatus', [
                            Substatus::CANCELADA->value,
                            Substatus::CANCELADA_POR_CLIENTE->value,
                            Substatus::CANCELADA_POR_CAMILA->value,
                            Substatus::NO_REALIZADA_TRANSFERIDA->value,
                        ]),
                        'no_responsive', Substatus::CLIENTE_NO_RESPONSIVE->value => $query->where('substatus', Substatus::CLIENTE_NO_RESPONSIVE->value),
                        default => $query->where('substatus', $this->archivedSubstatus),
                    };
                }

                return $query;
            })(),
            'backlog' => Order::query()->inBacklog(),
            default => Order::query(),
        };

        $baseQuery->with(['client', 'designer', 'designers', 'clientLocation']);
        $filteredQuery = $this->applyFilters($baseQuery);

        $totalFilteredCount = (clone $filteredQuery)->count();
        $hasMore = false;

        if ($this->perPage > 0) {
            $orders = $filteredQuery->paginate($this->perPage);
        } else {
            $orders = (clone $filteredQuery)->take($this->loadedCount)->get();
            $hasMore = $this->loadedCount < $totalFilteredCount;
        }

        $clients = Client::orderBy('name')->get();
        $designers = Designer::where('active', true)->orderBy('name')->get();
        $filterDesigners = Designer::where('active', true)->internal()->orderBy('name')->get();
        $substatuses = \App\Models\Substatus::orderBy('sort_order')->get();
        if ($substatuses->isEmpty()) {
            $substatuses = collect(Substatus::cases());
        }

        return view('livewire.overview.overview-index', array_merge([
            'orders' => $orders,
            'clients' => $clients,
            'designers' => $designers,
            'filterDesigners' => $filterDesigners,
            'substatuses' => $substatuses,
            'hasMore' => $hasMore,
            'totalFilteredCount' => $totalFilteredCount,
            'loadedCount' => $this->loadedCount,
            'editingOrderId' => $this->editingOrderId,
            'editingField' => $this->editingField,
            'editingValue' => $this->editingValue,
            'archivedSubstatus' => $this->archivedSubstatus,
        ], $this->metrics))->layout('components.layouts.app', ['title' => 'Overview Operativo']);
    }
}
