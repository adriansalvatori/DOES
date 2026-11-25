<div wire:poll.visible.10s class="h-full flex flex-col gap-3 min-h-0 overflow-hidden">

    @if($this->newOrdersCount > 0)
        <div class="flex items-center gap-2 text-body text-ink-2 shrink-0 min-w-0">
            <x-lucide-inbox class="w-4 h-4 text-ink-3 shrink-0" />
            <span class="truncate">{{ trans_choice('{1} :count new order from Trello is waiting for review in the Backlog.|[2,*] :count new orders from Trello are waiting for review in the Backlog.', $this->newOrdersCount) }}</span>
            <a href="{{ route('backlog') }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-ink hover:underline shrink-0">
                {{ __('Ver Nuevas Órdenes') }}
                <x-lucide-arrow-right class="w-3.5 h-3.5" />
            </a>
        </div>
    @endif

    <!-- Header and toolbar -->
    <div id="tour-kanban-header" class="space-y-3 shrink-0">
        @php
            $filterSubstatuses = \App\Models\Substatus::query()->orderBy('sort_order')->orderBy('id')->get();
            $substatusOptions = $filterSubstatuses->isNotEmpty()
                ? $filterSubstatuses->mapWithKeys(fn ($s) => [$s->name => $s->name])->all()
                : collect(\App\Enums\Substatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])->all();

            $filters = [
                ['model' => 'companyFilter', 'value' => $companyFilter, 'all' => __('Empresas (Todas)'), 'chip' => __('Empresa'), 'search' => __('Buscar empresa...'), 'options' => collect($this->existingCompanies)->mapWithKeys(fn ($c) => [(string) $c => (string) $c])->all(), 'hidden' => false, 'id' => null],
                ['model' => 'responsibleFilter', 'value' => $responsibleFilter, 'all' => __('Responsables (Todos)'), 'chip' => __('Responsable'), 'search' => __('Buscar responsable...'), 'options' => collect($this->existingResponsibles)->mapWithKeys(fn ($r) => [(string) $r => (string) $r])->all(), 'hidden' => true, 'id' => null],
                ['model' => 'designerFilter', 'value' => (string) $designerFilter, 'all' => __('Diseñadores (Todos)'), 'chip' => __('Diseñador'), 'search' => __('Buscar diseñador...'), 'options' => $this->designers->mapWithKeys(fn ($d) => [(string) $d->id => $d->name])->all(), 'hidden' => false, 'id' => null],
                ['model' => 'substatusFilter', 'value' => $substatusFilter, 'all' => __('Condición (Todas)'), 'chip' => __('Condición'), 'search' => __('Buscar condición...'), 'options' => $substatusOptions, 'hidden' => false, 'id' => 'tour-kanban-substatus-filter'],
            ];
        @endphp

        <x-ui.page-header :title="__('Kanban Board')" id="tour-kanban-title">
            <x-slot:toolbar>
            <!-- Search Input with Live Occurrences Dropdown -->
            <div class="relative flex-1 min-w-[200px] sm:min-w-[240px] max-w-sm" x-data="{ open: true }" x-dropdown-nav @click.outside="open = false">
                <x-lucide-search class="w-3.5 h-3.5 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none shrink-0 z-10" />
                <input type="text"
                       id="tour-kanban-search"
                       wire:model.live.debounce.200ms="search"
                       @focus="open = true"
                       @input="open = true"
                       placeholder="{{ __('Buscar empresa o tarea...') }}"
                       class="ui-input pl-8">

                @if(strlen(trim($search)) >= 2)
                    <div x-show="open"
                         x-cloak
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-line rounded-card shadow-menu max-h-72 overflow-y-auto p-1.5 text-body"
                         style="display: none;">
                        <div class="px-2 py-1 text-meta text-ink-3 border-b border-line mb-1 flex items-center justify-between">
                            <span>{{ __('Coincidencias') }} ({{ $this->searchResults->count() }})</span>
                            <span>{{ __('Clic para abrir') }}</span>
                        </div>

                        @forelse($this->searchResults as $result)
                            <div role="button"
                                 tabindex="0"
                                 wire:click="selectSearchResult({{ $result->id }})"
                                 @click="open = false"
                                 @keydown.enter="selectSearchResult({{ $result->id }}); open = false;"
                                 class="w-full text-left p-2 rounded-control hover:bg-sunken focus:bg-sunken focus:outline-none transition flex flex-nowrap items-center justify-between gap-2 group cursor-pointer min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        @if($result->wo_number)
                                            <x-wo-badge :number="$result->wo_number" />
                                        @endif
                                        <span class="font-medium text-ink truncate text-body shrink min-w-0">{{ $result->company_name }}</span>
                                        <x-orders.location :location="$result->location_text" />
                                    </div>
                                    <p class="text-meta text-ink-3 truncate mt-0.5" title="{{ $result->task_name }}">{{ $result->task_name }}</p>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0 min-w-0">
                                    <span class="text-meta text-ink-3 shrink-0 max-w-[130px] truncate" title="{{ $result->core_status->label() }}">{{ $result->core_status->label() }}</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-ink-3 text-body">
                                {{ __('No se encontraron coincidencias para') }} "{{ $search }}"
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- Searchable filters -->
            @foreach($filters as $filter)
                @php
                    $isActive = (string) $filter['value'] !== 'all';
                    $currentLabel = $isActive ? ($filter['options'][(string) $filter['value']] ?? $filter['all']) : $filter['all'];
                @endphp
                <div class="{{ $filter['hidden'] ? 'hidden' : '' }} relative flex-1 min-w-[140px] sm:flex-none"
                     x-data="{ open: false, search: '', pick(val) { $wire.set(@js($filter['model']), val); this.open = false; } }"
                     x-dropdown-nav>
                    <button
                        @if($filter['id']) id="{{ $filter['id'] }}" @endif
                        type="button"
                        @click="open = !open"
                        @click.outside="open = false"
                        class="ui-select text-left truncate {{ $isActive ? 'border-ink' : 'text-ink-2' }}">
                        <span class="block truncate">{{ $currentLabel }}</span>
                    </button>

                    <div x-show="open" x-cloak
                         class="absolute left-0 top-full mt-1 z-50 bg-surface border border-line rounded-card shadow-menu w-56 max-h-64 overflow-y-auto text-body"
                         style="display: none;">
                        <div class="p-1.5 sticky top-0 bg-surface border-b border-line">
                            <input type="text" x-model="search" placeholder="{{ $filter['search'] }}"
                                   class="w-full bg-surface border border-line rounded-control px-2 h-7 text-meta text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong">
                        </div>
                        <div class="p-1">
                            <button type="button" @click="pick('all')"
                                    class="w-full text-left px-2 py-1.5 rounded-control hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink transition flex items-center justify-between">
                                <span>{{ $filter['all'] }}</span>
                                @unless($isActive)
                                    <x-lucide-check class="w-3.5 h-3.5 text-ink-2" />
                                @endunless
                            </button>
                            @foreach($filter['options'] as $optValue => $optLabel)
                                <button type="button"
                                        x-show="!search || {{ \Illuminate\Support\Js::from(mb_strtolower($optLabel)) }}.includes(search.toLowerCase())"
                                        @click="pick(@js((string) $optValue))"
                                        class="w-full text-left px-2 py-1.5 rounded-control hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink transition flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $optLabel }}</span>
                                    @if($isActive && (string) $filter['value'] === (string) $optValue)
                                        <x-lucide-check class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
                    </x-slot:toolbar>
        </x-ui.page-header>

        <!-- Active filter chips -->
        @php
            $activeFilters = collect($filters)->filter(fn ($f) => ! $f['hidden'] && (string) $f['value'] !== 'all');
        @endphp
        @if($activeFilters->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1.5" data-testid="kanban-active-filters">
                @foreach($activeFilters as $filter)
                    <span class="inline-flex items-center gap-1 h-6 pl-2 pr-1 rounded-full bg-sunken text-ink-2 text-meta font-medium">
                        <span class="text-ink-3">{{ $filter['chip'] }}:</span>
                        <span class="text-ink truncate max-w-[160px]">{{ $filter['options'][(string) $filter['value']] ?? $filter['value'] }}</span>
                        <button type="button" wire:click="$set('{{ $filter['model'] }}', 'all')" aria-label="{{ __('Quitar filtro') }}"
                                class="p-0.5 rounded-full text-ink-3 hover:text-ink hover:bg-line transition">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Column group segmented control (kept hidden as before) -->
    <div id="tour-kanban-group-tabs" class="hidden items-center justify-between gap-2 overflow-x-auto scrollbar-none text-body shrink-0">
        <div class="inline-flex items-center gap-0.5 p-0.5 rounded-control bg-sunken shrink-0" role="group">
            @foreach([
                'all' => __('Todas las Listas'),
                'incoming' => __('Bloqueadas & Pendientes'),
                'in_progress' => __('En Proceso'),
                'final' => __('Producción & Hold'),
            ] as $groupKey => $groupLabel)
                <button type="button" wire:click="$set('columnGroup', '{{ $groupKey }}')" aria-pressed="{{ $columnGroup === $groupKey ? 'true' : 'false' }}"
                        class="px-3 h-7 rounded-control text-body font-medium transition shrink-0 {{ $columnGroup === $groupKey ? 'bg-surface text-ink border border-line' : 'text-ink-3 hover:text-ink border border-transparent' }}">
                    {{ $groupLabel }}
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="toggleStandaloneTaskCards" class="hidden px-3 h-7 rounded-control font-medium transition items-center gap-1.5 shrink-0 text-body border border-line {{ $showStandaloneTaskCards ? 'bg-sunken text-ink' : 'bg-surface text-ink-2 hover:bg-sunken' }}" title="{{ __('Mostrar u ocultar tarjetas de tareas como elementos independientes en las columnas') }}">
            <x-lucide-list-todo class="w-3.5 h-3.5" />
            <span>{{ $showStandaloneTaskCards ? __('Ocultar Tarjetas de Tareas') : __('Mostrar Tarjetas de Tareas') }}</span>
        </button>
    </div>

    <!-- Alert Flash Message -->
    @if (session()->has('message'))
        <div class="bg-ok-soft text-ok px-3 py-2 rounded-card text-body font-medium flex items-center gap-2 shrink-0">
            <x-lucide-check-circle-2 class="w-4 h-4 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Kanban columns (drag and drop with edge auto-scroll) -->
    <div
        x-data="{
            scrollSpeed: 0,
            scrollInterval: null,
            handleDragOver(e) {
                const container = $el;
                const rect = container.getBoundingClientRect();
                const mouseX = e.clientX;
                const threshold = 100;
                const maxSpeed = 24;

                let speed = 0;
                if (mouseX - rect.left < threshold && mouseX - rect.left > 0) {
                    const intensity = (threshold - (mouseX - rect.left)) / threshold;
                    speed = -Math.max(6, Math.round(intensity * maxSpeed));
                } else if (rect.right - mouseX < threshold && rect.right - mouseX > 0) {
                    const intensity = (threshold - (rect.right - mouseX)) / threshold;
                    speed = Math.max(6, Math.round(intensity * maxSpeed));
                }

                this.scrollSpeed = speed;

                if (speed !== 0 && !this.scrollInterval) {
                    const step = () => {
                        if (this.scrollSpeed !== 0) {
                            container.scrollLeft += this.scrollSpeed;
                            this.scrollInterval = requestAnimationFrame(step);
                        } else {
                            this.scrollInterval = null;
                        }
                    };
                    this.scrollInterval = requestAnimationFrame(step);
                } else if (speed === 0 && this.scrollInterval) {
                    cancelAnimationFrame(this.scrollInterval);
                    this.scrollInterval = null;
                }
            },
            stopAutoScroll() {
                this.scrollSpeed = 0;
                if (this.scrollInterval) {
                    cancelAnimationFrame(this.scrollInterval);
                    this.scrollInterval = null;
                }
            }
        }"
        @dragover="handleDragOver($event)"
        @dragend="stopAutoScroll()"
        @drop="stopAutoScroll()"
        @dragleave.self="stopAutoScroll()"
        class="flex gap-3 overflow-x-auto pb-3 pt-1 custom-horizontal-scrollbar flex-1 min-h-0 w-full"
        id="tour-kanban-board">
        @foreach($columns as $column)
            @if($column === \App\Enums\CoreStatus::ARCHIVED)
                <!-- Archive dropzone (no cards displayed) -->
                <div
                    x-data="{ isTarget: false }"
                    @dragover.prevent="isTarget = true"
                    @dragleave.prevent="isTarget = false"
                    @drop.prevent="
                        isTarget = false;
                        const orderId = event.dataTransfer.getData('text/plain');
                        if (orderId) {
                            $wire.moveOrder(orderId, '{{ $column->value }}');
                        }
                    "
                    :class="isTarget ? 'border-ink-3 bg-sunken' : 'border-line-strong bg-canvas'"
                    class="shrink-0 w-80 border border-dashed rounded-card flex flex-col h-full overflow-hidden transition duration-150">

                    <div class="px-3 pt-3 pb-2 flex items-center justify-between shrink-0">
                        <h3 class="text-body font-semibold text-ink truncate flex items-center gap-1.5">
                            <x-lucide-archive class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                            <span>{{ __('Enviar a archivo') }}</span>
                        </h3>
                        <a href="/archived" wire:navigate title="{{ __('Ver Órdenes Archivadas') }}" class="flex items-center gap-1 text-meta text-ink-3 hover:text-ink transition shrink-0">
                            <span>{{ $this->archivedCount }}</span>
                            <x-lucide-external-link class="w-3 h-3" />
                        </a>
                    </div>

                    <div class="p-6 flex-1 flex flex-col items-center justify-center text-center gap-3 min-h-0">
                        <x-lucide-archive-restore class="w-6 h-6 text-ink-3" />
                        <div class="space-y-1 px-2">
                            <h4 class="text-body font-medium text-ink-2">{{ __('Arrastra aquí para archivar') }}</h4>
                            <p class="text-meta text-ink-3">
                                {{ __('Las órdenes soltadas en esta columna se marcarán como Archivadas y se ocultarán del Workspace activo.') }}
                            </p>
                        </div>
                        <a href="/archived" wire:navigate class="inline-flex items-center gap-1.5 px-3 h-8 rounded-control border border-line bg-surface hover:bg-sunken text-ink-2 font-medium text-body transition">
                            <x-lucide-bar-chart-2 class="w-3.5 h-3.5" />
                            <span>{{ __('Ver Rendimiento') }}</span>
                        </a>
                    </div>
                </div>
            @else
                @php
                    $columnOrders = $this->orders->filter(fn($o) => $o->core_status === $column);
                    $columnTasks = match ($column) {
                        \App\Enums\CoreStatus::TO_DO_TODAY => $this->relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && $t->type !== \App\Enums\RelatedTaskType::BLOCKED),
                        \App\Enums\CoreStatus::ENTRANTE => $this->relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone() && ($t->type === \App\Enums\RelatedTaskType::BLOCKED || $t->type === \App\Enums\RelatedTaskType::RESOLVER)),
                        default => collect(),
                    };
                    $totalItemCount = $columnOrders->count() + $columnTasks->count();
                    $columnLabel = match ($column) {
                        \App\Enums\CoreStatus::EURALIZ_ORDERS_RECEIVED,
                        \App\Enums\CoreStatus::ADRIAN_ORDERS_RECEIVED,
                        \App\Enums\CoreStatus::CESAR_ORDERS_RECEIVED => __(':name · received', ['name' => $column->shortLabel()]),
                        default => \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower($column->label())),
                    };
                @endphp
                <div
                    x-data="{ isTarget: false }"
                    @dragover.prevent="isTarget = true"
                    @dragleave.prevent="isTarget = false"
                    @drop.prevent="
                        isTarget = false;
                        const orderId = event.dataTransfer.getData('text/plain');
                        if (orderId) {
                            $wire.moveOrder(orderId, '{{ $column->value }}');
                        }
                    "
                    :class="isTarget ? 'border-line-strong bg-line/50' : 'border-transparent bg-sunken'"
                    class="shrink-0 w-80 border rounded-card flex flex-col h-full overflow-hidden transition duration-150">

                    <!-- Column header -->
                    <div class="px-3 pt-3 pb-2 flex items-center justify-between sticky top-0 z-10 shrink-0">
                        <h3 class="text-body font-semibold text-ink truncate">
                            {{ $columnLabel }}
                            <span class="font-normal text-ink-3 ml-1">{{ $totalItemCount }}</span>
                        </h3>
                        <button
                            type="button"
                            @click="$dispatch('open-create-order', { initialStatus: '{{ $column->value }}' })"
                            class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-surface transition shrink-0"
                            title="{{ __('Añadir nueva orden a') }} {{ $column->label() }}"
                            aria-label="{{ __('Añadir nueva orden a') }} {{ $column->label() }}">
                            <x-lucide-plus class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Column cards -->
                    <div class="px-2.5 pb-2.5 overflow-y-auto flex-1 space-y-2.5 custom-vertical-scrollbar min-h-0">
                        @php
                            $urgentColumnOrders = $columnOrders->filter(fn($o) => $o->isUrgente());
                            $regularColumnOrders = $columnOrders->filter(fn($o) => !$o->isUrgente());
                        @endphp

                        @if($columnOrders->isEmpty() && $columnTasks->isEmpty())
                            <div class="py-10 text-center border border-dashed border-line-strong rounded-card">
                                <span class="text-meta text-ink-3 block">{{ __('Arrastra una tarjeta aquí') }}</span>
                            </div>
                        @else
                            @foreach($urgentColumnOrders as $order)
                                @include('livewire.kanban.card', ['order' => $order, 'allColumns' => $allColumns])
                            @endforeach

                            @foreach($columnTasks as $task)
                                <div
                                    wire:key="task-card-{{ $task->id }}"
                                    x-data="{ isCompleting: false, isRemoving: false }"
                                    :class="{ 'opacity-0 scale-90 -translate-y-3 pointer-events-none transition-all duration-300 ease-out': isCompleting || isRemoving }"
                                    @if($task->order) @click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" @endif
                                    class="bg-surface border border-line hover:border-line-strong rounded-card p-3 flex flex-col gap-2 transition duration-150 relative select-none cursor-pointer">
                                    <div class="flex items-center justify-between gap-1.5 min-w-0">
                                        <div class="flex flex-wrap items-center gap-1 min-w-0">
                                            <x-ui.tag icon="check-square">{{ __('Tarea vinculada') }}</x-ui.tag>
                                            @if($task->type)
                                                <span class="text-meta text-ink-3">{{ $task->type->label() }}</span>
                                            @endif
                                        </div>
                                        <span class="text-meta text-ink-2 shrink-0 truncate max-w-[110px]">{{ $task->assignee?->name ?? __('Sin Asignar') }}</span>
                                    </div>

                                    <div class="min-w-0">
                                        <h4 class="font-medium text-body text-ink break-words">{{ $task->title }}</h4>
                                        @if($task->order)
                                            <p class="text-meta text-ink-3 truncate mt-0.5 flex items-center gap-1.5">
                                                @if($task->order->wo_number)
                                                    <x-wo-badge :number="$task->order->wo_number" />
                                                @endif
                                                <span class="truncate">{{ $task->order->company_name }}</span>
                                                <x-orders.location :location="$task->order->location_text" />
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-between gap-1.5 min-w-0" @click.stop>
                                        <button
                                            type="button"
                                            @click="isCompleting = true; setTimeout(() => $wire.toggleTaskComplete({{ $task->id }}), 300)"
                                            class="px-2.5 h-7 rounded-control text-meta font-medium bg-surface hover:bg-sunken text-ink border border-line transition flex items-center gap-1">
                                            <x-lucide-check class="w-3 h-3" />
                                            <span>{{ __('Completar') }}</span>
                                        </button>

                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Más acciones') }}" />
                                            <flux:menu>
                                                <flux:menu.item
                                                    icon="trash"
                                                    variant="danger"
                                                    wire:click="deleteTask({{ $task->id }})"
                                                    wire:confirm="{{ __('¿Eliminar esta tarea vinculada?') }}">{{ __('Eliminar tarea') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @endforeach

                            @foreach($regularColumnOrders as $order)
                                @include('livewire.kanban.card', ['order' => $order, 'allColumns' => $allColumns])
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <!-- Create Order Modal is registered in app layout -->

    <x-orders.reason-sheet
        :show="$showOnHoldModal"
        :title="__('Motivo para poner en On Hold')"
        :description="__('Ingresa la razón por la que esta orden entra en pausa.')"
        icon="pause-circle"
        confirm="confirmOnHold" cancel="cancelOnHold"
        :confirm-label="__('Poner en On Hold')"
        reason-model="onHoldReason"
        :reason-label="__('Motivo / Comentario')"
        :reason-placeholder="__('Ej: Esperando confirmación de presupuesto por parte del cliente...')" />

    <x-orders.reason-sheet
        :show="$showResumeModal"
        :title="__('Motivo para reanudar la orden')"
        :description="__('Ingresa el motivo por el cual la orden sale de On Hold y retoma trabajo.')"
        icon="play-circle"
        confirm="confirmResume" cancel="cancelResume"
        :confirm-label="__('Reanudar Orden')"
        reason-model="resumeReason"
        :reason-label="__('Motivo / Nota de reanudación')"
        :reason-placeholder="__('Ej: Cliente aprobó presupuesto / Se recibieron las medidas del cliente...')" />

    <!-- Archive Order Substatus Selection Modal -->
    @include('livewire.orders.partials.archive-modal', [
        'targetOrder' => $pendingArchiveOrder ?? null,
        'archivedSubstatuses' => $archivedSubstatuses ?? null,
    ])

    <x-orders.reason-sheet
        :show="$showBlockModal"
        :title="__('Bloquear Orden')"
        :description="__('Indica la razón por la que esta orden no puede avanzar.')"
        icon="alert-octagon"
        tone="danger"
        confirm="confirmBlock" cancel="cancelBlock"
        :confirm-label="__('Bloquear Orden')"
        :presets="[
            'FALTAN MEDIDAS' => __('Faltan medidas'),
            'FALTA LOGO' => __('Falta logo / arte'),
            'FALTA APROBACIÓN DE ESTIMADO' => __('Falta aprobación de estimado'),
            'ESPERANDO CLIENTE' => __('Esperando cliente'),
            'OTROS' => __('Otro motivo'),
        ]"
        preset-model="blockReason"
        :selected="$blockReason"
        :preset-label="__('Motivo del bloqueo')"
        reason-model="blockReasonOther"
        reason-type="input"
        :reason-visible="$blockReason === 'OTROS'"
        :reason-label="__('Especificar otro motivo')"
        :reason-placeholder="__('Ej: Esperando material especial de proveedor...')">
        <div class="space-y-1">
            <label class="text-meta font-medium text-ink-2 block">{{ __('Detalles o comentarios adicionales (opcional)') }}</label>
            <textarea wire:model="blockComment" rows="2" @keydown.enter.stop placeholder="{{ __('Explica brevemente la situación...') }}" class="w-full bg-surface border border-line rounded-control px-2.5 py-1.5 text-body text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong focus:ring-2 focus:ring-gold/40"></textarea>
        </div>

        <div class="pt-2 border-t border-line">
            <label class="flex items-center gap-2 cursor-pointer text-ink font-medium">
                <input type="checkbox" wire:model="requireCustomerService" class="rounded-control border-line-strong text-ink focus:ring-gold/40 w-4 h-4">
                <span>{{ __('Requiere atención / seguimiento del cliente o responsable') }}</span>
            </label>
            <p class="text-meta text-ink-3 pl-6 mt-0.5">{{ __('Creará una tarea pendiente para dar seguimiento con el contacto o responsable del cliente.') }}</p>
        </div>
    </x-orders.reason-sheet>

    <x-orders.reason-sheet
        :show="$showUnblockModal"
        :title="__('Desbloquear Orden')"
        :description="__('La orden volverá a la lista del diseñador asignado.')"
        icon="check-circle-2"
        tone="ok"
        confirm="confirmUnblock" cancel="cancelUnblock"
        :confirm-label="__('Desbloquear Orden')"
        :presets="[
            'Medidas confirmadas' => __('Medidas confirmadas'),
            'Logo / Arte recibido' => __('Logo / Arte recibido'),
            'Estimado aprobado' => __('Estimado aprobado'),
            'Respuesta recibida del cliente' => __('Respuesta recibida del cliente'),
        ]"
        preset-model="unblockReason"
        :selected="$unblockReason"
        :preset-label="__('¿Cómo se resolvió el bloqueo?')"
        reason-model="unblockReason"
        reason-type="input"
        :reason-placeholder="__('Escribe o selecciona un motivo...')" />

</div>
