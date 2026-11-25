<div wire:poll.visible.10s="syncChangedColumns" class="h-full flex flex-col gap-3 min-h-0 overflow-hidden">

    @if($this->newOrdersCount > 0)
        <div class="flex items-center gap-2 text-body text-ink-2 shrink-0 min-w-0">
            <x-lucide-inbox class="w-4 h-4 text-ink-3 shrink-0" />
            <span class="truncate">{{ trans_choice('{1} :count new order from Trello is waiting for review in the Backlog.|[2,*] :count new orders from Trello are waiting for review in the Backlog.', $this->newOrdersCount) }}</span>
            <a href="{{ route('backlog') }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-ink hover:underline shrink-0">
                {{ __('View New Orders') }}
                <x-lucide-arrow-right class="w-3.5 h-3.5" />
            </a>
        </div>
    @endif

    <!-- Header and toolbar -->
    <div id="tour-kanban-header" class="space-y-3 shrink-0">
        @php
            $filterSubstatuses = \App\Models\Substatus::query()->orderBy('sort_order')->orderBy('id')->get();
            $substatusOptions = $filterSubstatuses->isNotEmpty()
                ? $filterSubstatuses->mapWithKeys(fn ($s) => [$s->name => $s->label()])->all()
                : collect(\App\Enums\Substatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();

            $filters = [
                ['model' => 'companyFilter', 'value' => $companyFilter, 'all' => __('Companies (All)'), 'chip' => __('Company'), 'search' => __('Search company...'), 'options' => collect($this->existingCompanies)->mapWithKeys(fn ($c) => [(string) $c => (string) $c])->all(), 'hidden' => false, 'id' => null],
                ['model' => 'responsibleFilter', 'value' => $responsibleFilter, 'all' => __('Account Reps (All)'), 'chip' => __('Account Rep'), 'search' => __('Search account rep...'), 'options' => collect($this->existingResponsibles)->mapWithKeys(fn ($r) => [(string) $r => (string) $r])->all(), 'hidden' => true, 'id' => null],
                ['model' => 'designerFilter', 'value' => (string) $designerFilter, 'all' => __('Designers (All)'), 'chip' => __('Designer'), 'search' => __('Search designer...'), 'options' => $this->designers->mapWithKeys(fn ($d) => [(string) $d->id => $d->name])->all(), 'hidden' => false, 'id' => null],
                ['model' => 'substatusFilter', 'value' => $substatusFilter, 'all' => __('Condition (All)'), 'chip' => __('Condition'), 'search' => __('Search condition...'), 'options' => $substatusOptions, 'hidden' => false, 'id' => 'tour-kanban-substatus-filter'],
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
                       placeholder="{{ __('Search company or task...') }}"
                       class="ui-input pl-8">

                @if(strlen(trim($search)) >= 2)
                    <div x-show="open"
                         x-cloak
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-line rounded-card shadow-menu max-h-72 overflow-y-auto p-1.5 text-body"
                         style="display: none;">
                        <div class="px-2 py-1 text-meta text-ink-3 border-b border-line mb-1 flex items-center justify-between">
                            <span>{{ __('Matches') }} ({{ $this->searchResults->count() }})</span>
                            <span>{{ __('Click to open') }}</span>
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
                                {{ __('No matches found for') }} "{{ $search }}"
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
                     x-data="{
                         open: false,
                         search: '',
                         current: @js((string) $filter['value']),
                         options: @js(collect($filter['options'])->map(fn ($label, $value) => [(string) $value, (string) $label])->values()->all()),
                         get shown() {
                             const term = this.search.toLowerCase();
                             return term ? this.options.filter(([, label]) => label.toLowerCase().includes(term)) : this.options;
                         },
                         pick(val) { $wire.set(@js($filter['model']), val); this.open = false; }
                     }"
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
                            <template x-for="[optValue, optLabel] in shown" :key="optValue">
                                <button type="button"
                                        @click="pick(optValue)"
                                        class="w-full text-left px-2 py-1.5 rounded-control hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink transition flex items-center justify-between gap-2">
                                    <span class="truncate" x-text="optLabel"></span>
                                    <svg x-show="current !== 'all' && current === optValue" class="w-3.5 h-3.5 text-ink-2 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </button>
                            </template>
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
                        <button type="button" wire:click="$set('{{ $filter['model'] }}', 'all')" aria-label="{{ __('Remove filter') }}"
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
                'all' => __('All Lists'),
                'incoming' => __('Blocked & Pending'),
                'in_progress' => __('In Progress'),
                'final' => __('Production & Hold'),
            ] as $groupKey => $groupLabel)
                <button type="button" wire:click="$set('columnGroup', '{{ $groupKey }}')" aria-pressed="{{ $columnGroup === $groupKey ? 'true' : 'false' }}"
                        class="px-3 h-7 rounded-control text-body font-medium transition shrink-0 {{ $columnGroup === $groupKey ? 'bg-surface text-ink border border-line' : 'text-ink-3 hover:text-ink border border-transparent' }}">
                    {{ $groupLabel }}
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="toggleStandaloneTaskCards" class="hidden px-3 h-7 rounded-control font-medium transition items-center gap-1.5 shrink-0 text-body border border-line {{ $showStandaloneTaskCards ? 'bg-sunken text-ink' : 'bg-surface text-ink-2 hover:bg-sunken' }}" title="{{ __('Show or hide task cards as standalone items in the columns') }}">
            <x-lucide-list-todo class="w-3.5 h-3.5" />
            <span>{{ $showStandaloneTaskCards ? __('Hide task cards') : __('Show task cards') }}</span>
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
                            <span>{{ __('Send to archive') }}</span>
                        </h3>
                        <a href="/archived" wire:navigate title="{{ __('View archived orders') }}" class="flex items-center gap-1 text-meta text-ink-3 hover:text-ink transition shrink-0">
                            <span>{{ $this->archivedCount }}</span>
                            <x-lucide-external-link class="w-3 h-3" />
                        </a>
                    </div>

                    <div class="p-6 flex-1 flex flex-col items-center justify-center text-center gap-3 min-h-0">
                        <x-lucide-archive-restore class="w-6 h-6 text-ink-3" />
                        <div class="space-y-1 px-2">
                            <h4 class="text-body font-medium text-ink-2">{{ __('Drag here to archive') }}</h4>
                            <p class="text-meta text-ink-3">
                                {{ __('Orders dropped in this column will be marked as Archived and hidden from the active Workspace.') }}
                            </p>
                        </div>
                        <a href="/archived" wire:navigate class="inline-flex items-center gap-1.5 px-3 h-8 rounded-control border border-line bg-surface hover:bg-sunken text-ink-2 font-medium text-body transition">
                            <x-lucide-bar-chart-2 class="w-3.5 h-3.5" />
                            <span>{{ __('View Performance') }}</span>
                        </a>
                    </div>
                </div>
            @else
                <livewire:kanban.column
                    :key="'kanban-column-'.$column->value"
                    :status="$column->value"
                    :search="$search"
                    :designer-filter="(string) $designerFilter"
                    :substatus-filter="$substatusFilter"
                    :company-filter="$companyFilter"
                    :responsible-filter="$responsibleFilter"
                    :show-standalone-task-cards="$showStandaloneTaskCards" />
            @endif
        @endforeach
    </div>

    <x-kanban.card-menu :columns="$allColumns" />

    <!-- Create Order Modal is registered in app layout -->

    <x-orders.reason-sheet
        :show="$showOnHoldModal"
        :title="__('Reason for putting on hold')"
        :description="__('Enter the reason this order is being paused.')"
        icon="pause-circle"
        confirm="confirmOnHold" cancel="cancelOnHold"
        :confirm-label="__('Put on On Hold')"
        reason-model="onHoldReason"
        :reason-label="__('Reason / comment')"
        :reason-placeholder="__('e.g. Waiting for quote confirmation from client...')" />

    <x-orders.reason-sheet
        :show="$showResumeModal"
        :title="__('Reason for resuming the order')"
        :description="__('Enter why the order leaves On Hold and work resumes.')"
        icon="play-circle"
        confirm="confirmResume" cancel="cancelResume"
        :confirm-label="__('Resume Order')"
        reason-model="resumeReason"
        :reason-label="__('Reason / resume note')"
        :reason-placeholder="__('e.g. Client approved quote / Received client measurements...')" />

    <!-- Archive Order Substatus Selection Modal -->
    @include('livewire.orders.partials.archive-modal', [
        'targetOrder' => $pendingArchiveOrder ?? null,
        'archivedSubstatuses' => $archivedSubstatuses ?? null,
    ])

    <x-orders.reason-sheet
        :show="$showBlockModal"
        :title="__('Block Order')"
        :description="__('Say why this order can\'t move forward.')"
        icon="alert-octagon"
        tone="danger"
        confirm="confirmBlock" cancel="cancelBlock"
        :confirm-label="__('Block Order')"
        :presets="[
            'FALTAN MEDIDAS' => __('Missing measurements'),
            'FALTA LOGO' => __('Missing logo / artwork'),
            'FALTA APROBACIÓN DE ESTIMADO' => __('Missing estimate approval'),
            'ESPERANDO CLIENTE' => __('Waiting on client'),
            'OTROS' => __('Other reason'),
        ]"
        preset-model="blockReason"
        :selected="$blockReason"
        :preset-label="__('Block reason')"
        reason-model="blockReasonOther"
        reason-type="input"
        :reason-visible="$blockReason === 'OTROS'"
        :reason-label="__('Specify other reason')"
        :reason-placeholder="__('e.g. Waiting for special material from supplier...')">
        <div class="space-y-1">
            <label class="text-meta font-medium text-ink-2 block">{{ __('Additional details or comments (optional)') }}</label>
            <textarea wire:model="blockComment" rows="2" @keydown.enter.stop placeholder="{{ __('Briefly explain the situation...') }}" class="w-full bg-surface border border-line rounded-control px-2.5 py-1.5 text-body text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong focus:ring-2 focus:ring-gold/40"></textarea>
        </div>

        <div class="pt-2 border-t border-line">
            <label class="flex items-center gap-2 cursor-pointer text-ink font-medium">
                <input type="checkbox" wire:model="requireCustomerService" class="rounded-control border-line-strong text-ink focus:ring-gold/40 w-4 h-4">
                <span>{{ __('Requires attention / follow-up by client or account rep') }}</span>
            </label>
            <p class="text-meta text-ink-3 pl-6 mt-0.5">{{ __('Creates a pending task to follow up with the client contact or account rep.') }}</p>
        </div>
    </x-orders.reason-sheet>

    <x-orders.reason-sheet
        :show="$showUnblockModal"
        :title="__('Unblock Order')"
        :description="__('The order goes back to the assigned designer\'s list.')"
        icon="check-circle-2"
        tone="ok"
        confirm="confirmUnblock" cancel="cancelUnblock"
        :confirm-label="__('Unblock Order')"
        :presets="[
            'Medidas confirmadas' => __('Measurements confirmed'),
            'Logo / Arte recibido' => __('Logo / artwork received'),
            'Estimado aprobado' => __('Estimate approved'),
            'Respuesta recibida del cliente' => __('Client replied'),
        ]"
        preset-model="unblockReason"
        :selected="$unblockReason"
        :preset-label="__('How was the block resolved?')"
        reason-model="unblockReason"
        reason-type="input"
        :reason-placeholder="__('Type or pick a reason...')" />

</div>
