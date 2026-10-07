<?php

namespace App\Livewire\Overview;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Client;
use App\Models\Designer;
use App\Models\InstallationType;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\AutomationEngine;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
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

        // TEMPORAL: Forzar ordenamiento por número de orden descendente
        $this->sortBy = 'wo_number';
        $this->sortDirection = 'desc';

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
            COUNT(CASE WHEN in_workspace = 1 AND done_today = 1 THEN 1 END) as doneTodayCount,
            COUNT(CASE WHEN core_status != 'ARCHIVED' AND (substatus = 'URGENTE' OR flags LIKE '%\"URGENTE\"%') THEN 1 END) as urgentCount,
            COUNT(CASE WHEN core_status != 'ARCHIVED' AND delivery_due_date IS NOT NULL AND delivery_due_date != '' THEN 1 END) as withDueDateCount
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
            'urgentCount' => (int) ($result->urgentCount ?? 0),
            'withDueDateCount' => (int) ($result->withDueDateCount ?? 0),
        ];
    }

    #[On('order-updated')]
    #[On('order-created')]
    #[On('order-deleted')]
    public function handleOrderUpdated($payload = null, ?string $source = null, ...$rest): void
    {
        $resolvedSource = $source ?? (is_array($payload) ? ($payload['source'] ?? null) : (is_string($payload) ? $payload : null));

        if ($resolvedSource === 'OverviewIndex') {
            $this->skipRender();

            return;
        }

        $this->clearOverviewCache();
    }

    #[Computed]
    public function archivedSubstatusFilters(): array
    {
        $rawCounts = Order::query()
            ->archived()
            ->selectRaw('substatus, count(*) as count')
            ->groupBy('substatus')
            ->pluck('count', 'substatus')
            ->toArray();

        $models = \App\Models\Substatus::where('core_status', CoreStatus::ARCHIVED->value)
            ->where('is_global', false)
            ->orderBy('sort_order')
            ->get();

        $totalAllOrders = Order::query()->count();

        $filters = [];

        // 1. All (Total de TODAS las órdenes de la BD)
        $filters['all'] = [
            'key' => 'all',
            'label' => mb_strtoupper(__('ALL ORDERS')),
            'short_label' => mb_strtoupper(__('ALL ORDERS')),
            'count' => $totalAllOrders,
            'bg_color' => '#F5F5F4',
            'text_color' => '#57534E',
            'border_color' => '#E7E5E4',
            'solid_bg' => '#0E7490',
            'solid_text' => '#FFFFFF',
            'icon' => 'archive',
        ];

        // 2. Subestatus configurados para ARCHIVED en la base de datos
        foreach ($models as $m) {
            $cnt = (int) ($rawCounts[$m->name] ?? 0);
            if ($m->name === 'FINALIZADA !' && isset($rawCounts['FINALIZADA'])) {
                $cnt += (int) $rawCounts['FINALIZADA'];
            } elseif ($m->name === 'FINALIZADA' && isset($rawCounts['FINALIZADA !'])) {
                $cnt += (int) $rawCounts['FINALIZADA !'];
            }
            if ($m->name === 'CLIENTE NO RESPONDIO' && isset($rawCounts['CLIENTE NO RESPONSIVE'])) {
                $cnt += (int) $rawCounts['CLIENTE NO RESPONSIVE'];
            }

            $enum = Substatus::tryFrom($m->name);
            $label = mb_strtoupper($enum?->label() ?? $m->name);
            $pal = \App\Models\Substatus::resolvePalette($m->name);

            $filters[$m->name] = [
                'key' => $m->name,
                'label' => $label,
                'short_label' => $label,
                'count' => $cnt,
                'bg_color' => $pal['bg_color'],
                'text_color' => $pal['text_color'],
                'border_color' => $pal['border_color'],
                'solid_bg' => $pal['solid_bg'],
                'solid_text' => $pal['solid_text'],
                'style_type' => $pal['style_type'],
                'icon' => 'tag',
            ];
        }

        // 3. Subestatus adicionales encontrados en órdenes archivadas de DB
        foreach ($rawCounts as $subName => $cnt) {
            if ($subName === null || $subName === '') {
                continue;
            }

            if (isset($filters[$subName])) {
                continue;
            }
            if ($subName === 'FINALIZADA' && isset($filters['FINALIZADA !'])) {
                continue;
            }
            if ($subName === 'FINALIZADA !' && isset($filters['FINALIZADA'])) {
                continue;
            }
            if ($subName === 'CLIENTE NO RESPONSIVE' && isset($filters['CLIENTE NO RESPONDIO'])) {
                continue;
            }

            $enum = Substatus::tryFrom($subName);
            $label = mb_strtoupper($enum?->label() ?? $subName);
            $pal = \App\Models\Substatus::resolvePalette($subName);

            $filters[$subName] = [
                'key' => $subName,
                'label' => $label,
                'short_label' => $label,
                'count' => (int) $cnt,
                'bg_color' => $pal['bg_color'],
                'text_color' => $pal['text_color'],
                'border_color' => $pal['border_color'],
                'solid_bg' => $pal['solid_bg'],
                'solid_text' => $pal['solid_text'],
                'style_type' => $pal['style_type'],
                'icon' => 'check-circle-2',
            ];
        }

        // 4. Órdenes archivadas sin subestatus -> "OTROS"
        $nullCount = (int) ($rawCounts[''] ?? 0) + (int) ($rawCounts[null] ?? 0);
        if ($nullCount > 0 && ! isset($filters['otros'])) {
            $pal = \App\Models\Substatus::resolvePalette(null);
            $filters['otros'] = [
                'key' => 'otros',
                'label' => mb_strtoupper(__('Otros')),
                'short_label' => mb_strtoupper(__('Otros')),
                'count' => $nullCount,
                'bg_color' => $pal['bg_color'],
                'text_color' => $pal['text_color'],
                'border_color' => $pal['border_color'],
                'solid_bg' => $pal['solid_bg'],
                'solid_text' => $pal['solid_text'],
                'style_type' => $pal['style_type'],
                'icon' => 'help-circle',
            ];
        }

        $all = $filters['all'];
        unset($filters['all']);

        uasort($filters, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return strcmp($a['label'], $b['label']);
            }

            return $b['count'] <=> $a['count'];
        });

        return ['all' => $all] + $filters;
    }

    #[Computed]
    public function groupedProcessSubstatuses(): array
    {
        $substatuses = \App\Models\Substatus::where('is_global', false)
            ->orderBy('sort_order')
            ->get();

        if ($substatuses->isEmpty()) {
            $substatuses = collect(Substatus::cases())->filter(fn ($s) => ! $s->isGlobal());
        }

        $coreOrder = [
            'ENTRANTE' => [
                'title' => __('1. Entrante / Bloqueada'),
                'dot' => 'bg-orange-500',
                'badge' => 'border-orange-200 bg-orange-50 text-orange-900',
            ],
            'EURALIZ ORDERS RECEIVED' => [
                'title' => __('2. Colas de Diseño'),
                'dot' => 'bg-emerald-500',
                'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            ],
            'ENVIADO A CAMILA' => [
                'title' => __('3. Enviado a Camila (QA)'),
                'dot' => 'bg-purple-500',
                'badge' => 'border-purple-200 bg-purple-50 text-purple-900',
            ],
            'ENVIADO AL CLIENTE' => [
                'title' => __('4. Enviado al Cliente'),
                'dot' => 'bg-sky-500',
                'badge' => 'border-sky-200 bg-sky-50 text-sky-900',
            ],
            'ON HOLD' => [
                'title' => __('5. En Pausa (On Hold)'),
                'dot' => 'bg-stone-500',
                'badge' => 'border-stone-300 bg-stone-100 text-stone-900',
            ],
            'EN PRODUCCIÓN' => [
                'title' => __('6. En Producción'),
                'dot' => 'bg-pink-500',
                'badge' => 'border-pink-200 bg-pink-50 text-pink-900',
            ],
            'ARCHIVED' => [
                'title' => __('7. Archivado / Cierre'),
                'dot' => 'bg-teal-500',
                'badge' => 'border-teal-200 bg-teal-50 text-teal-900',
            ],
        ];

        $groups = [];
        foreach ($coreOrder as $coreKey => $meta) {
            $groups[$coreKey] = [
                'key' => $coreKey,
                'title' => $meta['title'],
                'dot' => $meta['dot'],
                'badge' => $meta['badge'],
                'items' => [],
            ];
        }
        $groups['OTROS'] = [
            'key' => 'OTROS',
            'title' => __('8. Otros'),
            'dot' => 'bg-stone-400',
            'badge' => 'border-stone-200 bg-stone-50 text-stone-700',
            'items' => [],
        ];

        foreach ($substatuses as $subItem) {
            $core = null;
            if ($subItem instanceof \App\Models\Substatus) {
                $core = $subItem->core_status ? $subItem->core_status->value : null;
            } elseif ($subItem instanceof Substatus) {
                $core = $subItem->defaultCoreStatus()?->value;
            }
            $core = $core ?: 'OTROS';

            if (in_array($core, ['ADRIAN ORDERS RECEIVED', 'CESAR ORDERS RECEIVED', 'TO DO TODAY'], true)) {
                $core = 'EURALIZ ORDERS RECEIVED';
            }

            if (! isset($groups[$core])) {
                $groups[$core] = [
                    'key' => $core,
                    'title' => $core,
                    'dot' => 'bg-stone-400',
                    'badge' => 'border-stone-200 bg-stone-50 text-stone-700',
                    'items' => [],
                ];
            }

            $groups[$core]['items'][] = $subItem;
        }

        return array_filter($groups, fn ($g) => ! empty($g['items']));
    }

    public function clearOverviewCache(): void
    {
        unset($this->metrics);
        unset($this->archivedSubstatusFilters);
        unset($this->groupedProcessSubstatuses);
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

    /**
     * Polling ultraligero que compara el MAX(updated_at) de las órdenes visibles
     * y sólo serializa el state cuando hubo cambios reales (Renderless sin recargar HTML).
     */
    #[Renderless]
    public function pollOrdersState(array $orderIds, ?int $lastTimestamp = null): array
    {
        $validIds = array_values(array_filter(array_map('intval', $orderIds), fn ($id) => $id > 0));

        if (empty($validIds)) {
            return [
                'has_changes' => false,
                'timestamp' => now()->timestamp,
                'orders' => [],
            ];
        }

        $maxUpdatedAt = Order::query()
            ->whereIn('id', $validIds)
            ->max('updated_at');

        $currentTimestamp = $maxUpdatedAt ? strtotime((string) $maxUpdatedAt) : 0;

        if ($lastTimestamp !== null && $currentTimestamp <= $lastTimestamp) {
            return [
                'has_changes' => false,
                'timestamp' => $currentTimestamp,
            ];
        }

        $orders = Order::query()
            ->whereIn('id', $validIds)
            ->select([
                'id',
                'updated_at',
                'core_status',
                'substatus',
                'flags',
                'designer_id',
                'installation_type',
                'installation_types',
                'review_status',
                'wo_number',
                'production_note',
                'estimate_invoice_number',
                'in_workspace',
                'production_processed_at',
                'delivery_due_date',
            ])
            ->with(['designer', 'designers'])
            ->get();

        return [
            'has_changes' => true,
            'timestamp' => $currentTimestamp,
            'orders' => $this->buildOrdersState($orders),
        ];
    }

    /**
     * Construye un array indexado por ID con los atributos reactivos críticos para el state.
     */
    public function buildOrdersState(iterable $orders): array
    {
        $state = [];
        $substatuses = \App\Models\Substatus::all()->keyBy('name');

        foreach ($orders as $order) {
            $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);
            $subEnum = $subVal ? Substatus::tryFrom($subVal) : null;
            $subModel = $subVal ? ($substatuses->get($subVal)) : null;
            $subLabel = $subEnum?->label() ?? ($subVal ?: '—');

            $subInlineStyle = '';
            if ($subModel && ! empty($subModel->bg_color) && ! empty($subModel->text_color)) {
                $subInlineStyle = "background-color: {$subModel->bg_color}; color: {$subModel->text_color}; border-color: {$subModel->border_color};";
            } elseif ($subEnum) {
                $subInlineStyle = $subEnum->getInlineBadgeStyle();
            }

            $isArchivedOrder = $order->isArchived() || \App\Models\Substatus::isArchivedSubstatus($subVal);
            $effectiveCoreStatus = $isArchivedOrder
                ? CoreStatus::ARCHIVED->value
                : ($order->core_status?->value ?? (is_string($order->core_status) ? $order->core_status : null));

            $state[$order->id] = [
                'id' => $order->id,
                'substatus' => $subVal,
                'substatus_label' => $subLabel,
                'substatus_style' => $subInlineStyle,
                'is_archived' => $isArchivedOrder,
                'flags' => $order->flags ?? [],
                'designer_id' => $order->designer_id ?? $order->primary_designer?->id,
                'designer_name' => $order->designer_name,
                'designer_badge_inline_style' => $order->getDesignerBadgeInlineStyle(),
                'designer_badge_style' => $order->getDesignerBadgeStyle(),
                'review_status' => $order->review_status,
                'installation_type' => $order->installation_type,
                'installation_types' => $order->installation_types_list,
                'core_status' => $effectiveCoreStatus,
                'in_workspace' => (bool) $order->in_workspace,
                'wo_number' => $order->wo_number,
                'production_note' => $order->production_note,
                'estimate_invoice_number' => $order->estimate_invoice_number,
                'production_processed_at' => $order->production_processed_at?->format('Y-m-d'),
                'delivery_due_date' => $order->delivery_due_date?->format('Y-m-d'),
                'updated_at' => $order->updated_at?->timestamp ?? 0,
            ];
        }

        return $state;
    }

    // Filters
    public string $search = '';

    public string $filterWo = '';

    public string $filterClient = '';

    public string $filterDesigner = '';

    public string $filterReviewStatus = '';

    public string $filterInstallation = '';

    public string $filterDateRange = '';

    public bool $filterUrgent = false;

    public bool $filterWithDueDate = false;

    // Sorting
    public string $sortBy = 'wo_number';

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
        'filterUrgent' => ['except' => false],
        'filterWithDueDate' => ['except' => false],
        'sortBy' => ['except' => 'wo_number'],
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
        // TEMPORAL: Deshabilitado temporalmente a petición del usuario.
        // Siempre se mantendrá el orden por número de orden descendente (mayor a menor).
        // La lógica original se conserva intacta a continuación para retomarla después:
        /*
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = $column === 'wo_number' ? 'desc' : 'asc';
        }
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
        */
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
        if ($substatus === 'all') {
            $this->activeTab = 'all';
            $this->archivedSubstatus = 'all';
        } else {
            $this->activeTab = 'archived';
            $this->archivedSubstatus = $substatus;
        }
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
            'filterUrgent',
            'filterWithDueDate',
            'archivedSubstatus',
            'sortBy',
            'sortDirection',
            'perPage',
        ]);
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    public function clearFilter(string $key): void
    {
        if (in_array($key, ['search', 'filterWo', 'filterClient', 'filterDesigner', 'filterReviewStatus', 'filterInstallation', 'filterDateRange'], true)) {
            $this->{$key} = '';
            $this->loadedCount = self::CHUNK_SIZE;
            $this->resetPage();
        } elseif (in_array($key, ['filterUrgent', 'urgent'], true)) {
            $this->filterUrgent = false;
            $this->loadedCount = self::CHUNK_SIZE;
            $this->resetPage();
        } elseif (in_array($key, ['filterWithDueDate', 'withDueDate'], true)) {
            $this->filterWithDueDate = false;
            $this->loadedCount = self::CHUNK_SIZE;
            $this->resetPage();
        }
    }

    public function toggleFilter(string $key): void
    {
        if ($key === 'urgent' || $key === 'filterUrgent') {
            $this->filterUrgent = ! $this->filterUrgent;
        } elseif ($key === 'withDueDate' || $key === 'filterWithDueDate') {
            $this->filterWithDueDate = ! $this->filterWithDueDate;
        }
        $this->loadedCount = self::CHUNK_SIZE;
        $this->resetPage();
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    #[Computed]
    public function appliedFilters(): array
    {
        $filters = [];

        if (! empty(trim($this->search))) {
            $filters[] = [
                'key' => 'search',
                'label' => __('Búsqueda'),
                'value' => trim($this->search),
            ];
        }

        if (! empty(trim($this->filterWo))) {
            $filters[] = [
                'key' => 'filterWo',
                'label' => __('WO'),
                'value' => trim($this->filterWo),
            ];
        }

        if (! empty(trim($this->filterClient))) {
            $clientName = is_numeric($this->filterClient)
                ? Client::find($this->filterClient)?->name
                : $this->filterClient;

            $filters[] = [
                'key' => 'filterClient',
                'label' => __('Cliente'),
                'value' => (string) ($clientName ?: $this->filterClient),
            ];
        }

        if (! empty(trim($this->filterDesigner))) {
            $designerName = Designer::find($this->filterDesigner)?->name;

            $filters[] = [
                'key' => 'filterDesigner',
                'label' => __('Diseñador'),
                'value' => (string) ($designerName ?: $this->filterDesigner),
            ];
        }

        if (! empty(trim($this->filterReviewStatus))) {
            $reviewLabel = match ($this->filterReviewStatus) {
                'CS' => 'CS',
                'CAMILA' => 'Camila',
                'NONE' => __('Sin revisión'),
                default => $this->filterReviewStatus,
            };

            $filters[] = [
                'key' => 'filterReviewStatus',
                'label' => __('Revisión'),
                'value' => $reviewLabel,
            ];
        }

        if (! empty(trim($this->filterInstallation))) {
            $filters[] = [
                'key' => 'filterInstallation',
                'label' => __('Instalación'),
                'value' => $this->filterInstallation === 'NONE' ? __('Sin información') : $this->filterInstallation,
            ];
        }

        if (! empty(trim($this->filterDateRange))) {
            $dateLabel = match ($this->filterDateRange) {
                'today' => __('Hoy'),
                'week' => __('Esta semana'),
                default => $this->filterDateRange,
            };

            $filters[] = [
                'key' => 'filterDateRange',
                'label' => __('Fecha'),
                'value' => $dateLabel,
            ];
        }

        if ($this->filterUrgent) {
            $filters[] = [
                'key' => 'filterUrgent',
                'label' => __('Prioridad'),
                'value' => __('Urgente'),
            ];
        }

        if ($this->filterWithDueDate) {
            $filters[] = [
                'key' => 'filterWithDueDate',
                'label' => __('Entrega'),
                'value' => __('Con Due Date'),
            ];
        }

        return $filters;
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
            'manual_creation_date' => ($order->manual_creation_date ?? $order->trello_created_at ?? $order->created_at)?->format('Y-m-d') ?? '',
            'production_sent_at' => $order->production_sent_at ? $order->production_sent_at->format('Y-m-d') : '',
            'production_processed_at' => $order->production_processed_at ? $order->production_processed_at->format('Y-m-d') : '',
            'delivery_due_date' => $order->delivery_due_date ? $order->delivery_due_date->format('Y-m-d') : '',
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

            case 'production_processed_at':
                $order->update(['production_processed_at' => ! empty($value) ? $value : null]);
                break;

            case 'delivery_due_date':
                $order->update(['delivery_due_date' => ! empty($value) ? $value : null]);
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

    #[Renderless]
    public function quickUpdateField(int $orderId, string $field, ?string $value): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $prevVal = match ($field) {
            'wo_number' => $order->wo_number,
            'task_name' => $order->task_name,
            'company_name' => $order->company_name,
            'manual_creation_date' => $order->manual_creation_date?->format('Y-m-d'),
            'production_sent_at' => $order->production_sent_at?->format('Y-m-d'),
            'production_processed_at' => $order->production_processed_at?->format('Y-m-d'),
            'delivery_due_date' => $order->delivery_due_date?->format('Y-m-d'),
            'email_date' => $order->email_date?->format('Y-m-d'),
            'production_note' => $order->production_note,
            'estimate_invoice_number' => $order->estimate_invoice_number,
            'delivery_note' => $order->delivery_note,
            default => null,
        };

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

            case 'production_processed_at':
                $order->update(['production_processed_at' => ! empty($value) ? $value : null]);
                break;

            case 'delivery_due_date':
                $order->update(['delivery_due_date' => ! empty($value) ? $value : null]);
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

        $this->syncTrelloAndLog($order, 'ORDER_UPDATED', "Campo {$field} actualizado", $prevVal, $value);
        $this->clearOverviewCache();
        $this->dispatch('toast', message: __('Guardado.'));
    }

    #[Renderless]
    public function updateReviewStatus(?int $orderId, ?string $status): void
    {
        if (! $orderId) {
            return;
        }

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

    #[Renderless]
    public function updateInstallationType(?int $orderId, ?string $type): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $clean = $type ? mb_strtoupper(trim($type)) : null;
        $order->installation_types = $clean ? [$clean] : [];
        $order->installation_type = $clean;
        $order->save();

        $this->clearOverviewCache();
        $this->syncTrelloAndLog($order, 'INSTALLATION_CHANGED', 'Tipo de instalación actualizado: '.($clean ?? 'Vacío'));
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Instalación actualizada.'));
    }

    #[Renderless]
    public function toggleInstallationType(?int $orderId, ?string $type): void
    {
        if (! $orderId || ! $type) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $clean = mb_strtoupper(trim($type));
        $order->toggleInstallationType($clean);
        $order->save();

        $this->clearOverviewCache();
        $types = $order->installation_types_list;
        $this->syncTrelloAndLog($order, 'INSTALLATION_CHANGED', 'Instalación actualizada: '.(empty($types) ? 'Vacío' : implode(', ', $types)));
        $this->dispatch('order-updated', source: 'OverviewIndex');
        $this->dispatch('toast', message: __('Instalación actualizada.'));
    }

    #[Renderless]
    public function setInstallationTypes(?int $orderId, array $types): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $cleanTypes = array_values(array_unique(array_filter(array_map(fn ($t) => mb_strtoupper(trim((string) $t)), $types))));
        $order->installation_types = $cleanTypes;
        $order->installation_type = ! empty($cleanTypes) ? $cleanTypes[0] : null;
        $order->save();

        $this->clearOverviewCache();
        $this->syncTrelloAndLog($order, 'INSTALLATION_CHANGED', 'Instalación actualizada: '.(empty($cleanTypes) ? 'Vacío' : implode(', ', $cleanTypes)));
        $this->dispatch('order-updated', source: 'OverviewIndex');
        $this->dispatch('toast', message: __('Instalación actualizada.'));
    }

    #[Renderless]
    public function clearInstallationTypes(?int $orderId): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->installation_types = [];
        $order->installation_type = null;
        $order->save();

        $this->clearOverviewCache();
        $this->syncTrelloAndLog($order, 'INSTALLATION_CHANGED', 'Instalación eliminada');
        $this->dispatch('order-updated', source: 'OverviewIndex');
        $this->dispatch('toast', message: __('Instalación vaciada.'));
    }

    #[Renderless]
    public function updateSubstatus(?int $orderId, ?string $substatusValue): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $previousSub = $order->substatus;
        $prevSubVal = $previousSub instanceof Substatus ? $previousSub->value : (string) $previousSub;
        $substatus = $substatusValue ? (Substatus::tryFrom($substatusValue) ?? $substatusValue) : null;
        $newSubVal = $substatus instanceof Substatus ? $substatus->value : (string) $substatus;

        if ($substatus instanceof Substatus && $substatus->isGlobal()) {
            $order->toggleFlag($substatus);
            $order->save();
            $order->applySubstatusStatusRules();
            $label = $substatus->label();
            $this->syncTrelloAndLog($order, 'FLAG_TOGGLED', 'Bandera actualizada: '.$label, $newSubVal, $prevSubVal);
        } else {
            $isArchivedSub = \App\Models\Substatus::isArchivedSubstatus($substatus);
            $previousStatus = $order->core_status;

            $updates = ['substatus' => $substatus];
            if ($isArchivedSub) {
                $updates['core_status'] = CoreStatus::ARCHIVED;
                if (! $order->archived_at) {
                    $updates['archived_at'] = now();
                }
            }

            $order->update($updates);

            if ($isArchivedSub && $previousStatus !== CoreStatus::ARCHIVED) {
                app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, CoreStatus::ARCHIVED);
            } else {
                $order->fresh()->applySubstatusStatusRules();
            }

            $label = $substatus instanceof Substatus ? $substatus->label() : ($substatusValue ?? 'Ninguno');
            $this->syncTrelloAndLog($order, 'SUBSTATUS_CHANGED', 'Subestatus actualizado a: '.$label, $newSubVal, $prevSubVal);
            app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());
        }

        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Subestatus / Bandera actualizada.'));
    }

    #[Renderless]
    public function toggleFlag(?int $orderId, string $flagName): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $order->toggleFlag($flagName);
        $order->save();
        $order->applySubstatusStatusRules();

        $this->clearOverviewCache();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Bandera actualizada.'));
    }

    #[Renderless]
    public function toggleOverviewChecked(?int $orderId): void
    {
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $newVal = ! (bool) $order->overview_checked;
        $order->update(['overview_checked' => $newVal]);
        $this->clearOverviewCache();
        $this->dispatch('order-updated');
    }

    #[Renderless]
    public function updateDesigner(?int $orderId, ?int $designerId): void
    {
        if (! $orderId) {
            return;
        }

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

    private function syncTrelloAndLog(Order $order, string $eventType, string $description, ?string $newValue = null, ?string $previousValue = null): void
    {
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => $eventType,
            'actor' => auth()->user()?->name ?? 'Usuario',
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'metadata' => ['description' => $description],
        ]);

        if ($order->trello_card_id) {
            $syncCallback = function () use ($order) {
                try {
                    app(TrelloSyncService::class)->updateCardOnTrello($order);
                } catch (\Throwable $e) {
                    // Ignore network error silently
                }
            };

            if (app()->environment('testing')) {
                $syncCallback();
            } else {
                app()->terminating($syncCallback);
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
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('installation_type')
                            ->orWhere('installation_type', '');
                    })->where(function ($sub) {
                        $sub->whereNull('installation_types')
                            ->orWhere('installation_types', '[]')
                            ->orWhere('installation_types', 'null');
                    });
                });
            } else {
                $val = mb_strtoupper(trim($this->filterInstallation));
                $query->where(function ($q) use ($val) {
                    $q->where('installation_type', $val)
                        ->orWhereJsonContains('installation_types', $val);
                });
            }
        }

        if (! empty(trim($this->filterDateRange))) {
            match ($this->filterDateRange) {
                'today' => $query->whereDate('created_at', now()->toDateString()),
                'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                default => null,
            };
        }

        if ($this->filterUrgent) {
            $query->where(function ($q) {
                $q->where('substatus', Substatus::URGENTE->value)
                    ->orWhere('substatus', 'URGENTE')
                    ->orWhereJsonContains('flags', Substatus::URGENTE->value)
                    ->orWhereJsonContains('flags', 'URGENTE');
            });
        }

        if ($this->filterWithDueDate) {
            $query->whereNotNull('delivery_due_date')
                ->where('delivery_due_date', '!=', '');
        }

        // TEMPORAL: Siempre ordenado por número de orden (WO #), de la más reciente (mayor número de WO) a la más antigua.
        // La funcionalidad de ordenamiento dinámico por columnas se preserva comentada abajo para retomarla después.
        return $query->orderByRaw("CASE WHEN wo_number IS NULL OR wo_number = '' THEN 1 ELSE 0 END, CAST(REPLACE(UPPER(wo_number), 'WO', '') AS INTEGER) desc, id desc");

        /*
        // Sorting
        $allowedColumns = [
            'created_at',
            'manual_creation_date',
            'production_sent_at',
            'production_processed_at',
            'delivery_due_date',
            'email_date',
            'wo_number',
            'company_name',
            'task_name',
            'review_status',
            'installation_type',
            'substatus',
        ];
        $sortCol = in_array($this->sortBy, $allowedColumns, true) ? $this->sortBy : 'wo_number';
        $dir = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';

        if ($sortCol === 'manual_creation_date') {
            return $query->orderByRaw("COALESCE(manual_creation_date, trello_created_at, created_at) {$dir}");
        }

        if ($sortCol === 'wo_number') {
            return $query->orderByRaw("CASE WHEN wo_number IS NULL OR wo_number = '' THEN 1 ELSE 0 END, CAST(REPLACE(UPPER(wo_number), 'WO', '') AS INTEGER) {$dir}, id {$dir}");
        }

        return $query->orderBy($sortCol, $dir);
        */
    }

    public function render()
    {
        // Build query exclusively for the active tab (lazy loading)
        $baseQuery = match ($this->activeTab) {
            'workspace' => Order::query()->inWorkspace(),
            'production' => Order::query()->where('core_status', CoreStatus::EN_PRODUCCION),
            'archived' => (function () {
                if ($this->archivedSubstatus === 'all') {
                    return Order::query();
                }

                $query = Order::query()->archived();
                if (in_array($this->archivedSubstatus, ['CLIENTE NO RESPONDIO', 'CLIENTE NO RESPONSIVE', 'no_responsive'], true)) {
                    $query->whereIn('substatus', ['CLIENTE NO RESPONDIO', 'CLIENTE NO RESPONSIVE']);
                } elseif (in_array($this->archivedSubstatus, ['finalizada', Substatus::FINALIZADA->value, 'FINALIZADA'], true)) {
                    $query->whereIn('substatus', [Substatus::FINALIZADA->value, 'FINALIZADA']);
                } elseif ($this->archivedSubstatus === 'cancelada') {
                    $query->whereIn('substatus', [
                        Substatus::CANCELADA->value,
                        Substatus::CANCELADA_POR_CLIENTE->value,
                        Substatus::CANCELADA_POR_CAMILA->value,
                        Substatus::NO_REALIZADA_TRANSFERIDA->value,
                    ]);
                } elseif ($this->archivedSubstatus === 'otros') {
                    $query->where(fn ($q) => $q->whereNull('substatus')->orWhere('substatus', ''));
                } else {
                    $query->where('substatus', $this->archivedSubstatus);
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
            'installationTypes' => InstallationType::getAllCached(),
            'hasMore' => $hasMore,
            'totalFilteredCount' => $totalFilteredCount,
            'loadedCount' => $this->loadedCount,
            'editingOrderId' => $this->editingOrderId,
            'editingField' => $this->editingField,
            'editingValue' => $this->editingValue,
            'archivedSubstatus' => $this->archivedSubstatus,
            'groupedProcessSubstatuses' => $this->groupedProcessSubstatuses,
            'appliedFilters' => $this->appliedFilters,
            'ordersState' => $this->buildOrdersState($orders),
        ], $this->metrics))->layout('components.layouts.app', [
            'title' => 'Overview Operativo',
            'noPadding' => true,
        ]);
    }
}
