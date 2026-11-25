@php
    $stages = $order->stages;
    $allElements = $order->elements;
    $totalElements = $allElements->count();
    $filteredElements = $selectedStageFilter 
        ? $allElements->filter(fn($e) => $e->order_stage_id == $selectedStageFilter)
        : $allElements;
    $altaProgress = $order->alta_progress;
    $altaReady = $altaProgress['ready_for_production'];
    $altaApproved = $altaProgress['approved'];
    $altaPercent = $altaProgress['percentage'];
    
    // Percentages for visual bar segments
    $readyPercent = $totalElements > 0 ? (int) round(($altaReady / $totalElements) * 100) : 0;
    // Approved pieces that haven't been put in Alta yet
    $onlyApprovedCount = max(0, $altaApproved - $altaReady);
    $onlyApprovedPercent = $totalElements > 0 ? (int) round(($onlyApprovedCount / $totalElements) * 100) : 0;
    $pendingCount = max(0, $totalElements - max($altaApproved, $altaReady));
@endphp

<div class="space-y-4">
    <!-- Recycled Section Header -->
    <x-orders.tab-section-header :title="__('Items and stages')">
        @if($totalElements > 0)
            <x-slot:badge>
                <span class="text-meta text-ink-3 tabular-nums">
                    {{ $totalElements }} {{ $totalElements === 1 ? __('item') : __('items') }}
                </span>
            </x-slot:badge>
        @endif
    </x-orders.tab-section-header>

    <!-- Visual Progress (No Box / Border, Thinner Bar like Subtasks) -->
    @if($totalElements > 0)
        <div class="space-y-1.5 pt-0.5">
            <!-- Header Metrics: Current count & Percentage -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-body font-medium text-ink">{{ __('Production progress') }}</span>
                    <x-ui.tag :tone="$altaReady === $totalElements ? 'ok' : 'neutral'">
                        {{ $altaReady }} {{ __('of') }} {{ $totalElements }} {{ __('for print') }}
                    </x-ui.tag>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-meta tabular-nums {{ $altaPercent === 100 ? 'text-ok' : 'text-ink-3' }}">
                        {{ $altaPercent }}%
                    </span>
                    @if($altaPercent === 100)
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-ok-soft text-ok shrink-0">
                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                        </span>
                    @endif
                </div>
            </div>

            <!-- Visual Progress Bar: Thinner (h-1.5), identical to subtasks -->
            <div class="w-full bg-sunken rounded-full h-1.5 overflow-hidden flex gap-0.5">
                <!-- Segment 1: En Alta (Pink gradient or Emerald if 100%) -->
                @if($readyPercent > 0)
                    <div 
                        class="h-full rounded-full transition-all duration-300 bg-ink" 
                        style="width: {{ $readyPercent }}%"
                        title="{{ __('Items for Print:') }} {{ $altaReady }}/{{ $totalElements }} ({{ $readyPercent }}%)"
                    ></div>
                @endif

                <!-- Segment 2: Aprobadas pendientes de poner en alta -->
                @if($onlyApprovedPercent > 0)
                    <div 
                        class="h-full rounded-full transition-all duration-300 bg-ink-3" 
                        style="width: {{ $onlyApprovedPercent }}%"
                        title="{{ __('Approved pending to set for print:') }} {{ $onlyApprovedCount }}/{{ $totalElements }}"
                    ></div>
                @endif
            </div>

            <!-- Single Line Status / Missing Count Indicator (replaces old multi-legend) -->
            <div class="flex items-center justify-between text-meta text-ink-3 pt-0.5">
                <div>
                    @php
                        $missingAlta = max(0, $totalElements - $altaReady);
                    @endphp
                    @if($altaPercent === 100)
                        <span class="text-ok font-semibold text-meta flex items-center gap-1">
                            <x-lucide-sparkles class="w-3.5 h-3.5 text-ok" />
                            <span>{{ __('All items ready for production!') }}</span>
                        </span>
                    @elseif($missingAlta === 1)
                        <span class="text-ink-3 text-meta">
                            {{ __('1 item remaining for print') }}
                        </span>
                    @elseif($missingAlta > 1)
                        <span class="text-ink-3 text-meta">
                            {{ __(':count items remaining for print', ['count' => $missingAlta]) }}
                        </span>
                    @else
                        <span class="text-ink-3 text-meta">
                            {{ __('Pending to send to production') }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- New Stage Form (Collapsible) -->
    @if($showNewStageForm)
        <div class="p-3 bg-sunken border border-line rounded-card space-y-2.5 transition animate-fadeIn">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-xs text-ink-2 flex items-center gap-1.5">
                    <span>{{ __('New production stage') }}</span>
                </span>
                <button type="button" wire:click="$set('showNewStageForm', false)" class="text-ink-3 hover:text-ink-2">
                    <x-lucide-x class="w-3.5 h-3.5" />
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="sm:col-span-2">
                    <input 
                        type="text" 
                        wire:model="newStageName" 
                        placeholder="{{ __('Phase name (e.g. Phase 1 - Exterior Facade)...') }}"
                        @keydown.enter.prevent="$wire.addStage()"
                        class="ui-input"
                    />
                </div>
                <div>
                    <input 
                        type="date" 
                        wire:model="newStageDueDate" 
                        class="ui-input"
                        title="{{ __('Deadline to complete this phase') }}"
                    />
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button 
                    type="button" 
                    wire:click="$set('showNewStageForm', false)" 
                    class="ui-btn ui-btn-ghost ui-btn-sm">
                    {{ __('Cancel') }}
                </button>
                <button 
                    type="button" 
                    wire:click="addStage" 
                    class="ui-btn ui-btn-secondary ui-btn-sm">
                    <span>{{ __('Create stage') }}</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Stages Tabs / Badges -->
    @if($stages->isNotEmpty())
    <div class="flex items-center gap-1.5 flex-wrap">
        <button 
            type="button" 
            wire:click="$set('selectedStageFilter', null)"
            class="ui-chip" aria-pressed="{{ $selectedStageFilter === null ? 'true' : 'false' }}">
            <span>{{ __('All') }}</span>
        </button>

        @foreach($stages as $stage)
            @php
                $stageElems = $stage->elements;
                $stageTotal = $stageElems->count();
                $stageReady = $stageElems->filter(fn($e) => $e->isReadyForProduction())->count();
                $isStageActive = $selectedStageFilter === $stage->id;
            @endphp
            <div 
                wire:key="stage-tab-{{ $stage->id }}" 
                x-data="{ editingStage: false, stageName: @js($stage->name), stageDueDate: @js($stage->due_date?->toDateString()) }" 
                class="relative inline-flex items-center"
            >
                <button 
                    type="button" 
                    wire:click="$set('selectedStageFilter', {{ $stage->id }})"
                    class="ui-chip" aria-pressed="{{ $isStageActive ? 'true' : 'false' }}">
                    <span class="truncate max-w-[140px]">{{ $stage->name }}</span>
                    @if($stage->due_date)
                        <span class="text-meta opacity-70" title="{{ __('Due') }}: {{ $stage->due_date->translatedFormat('d M, Y') }}">
                            {{ $stage->due_date->translatedFormat('d M') }}
                        </span>
                    @endif
                    <span class="text-meta tabular-nums opacity-70">
                        {{ $stageReady }}/{{ $stageTotal }}
                    </span>
                </button>

                <!-- Stage actions popover button -->
                <button 
                    type="button" 
                    @click.stop="editingStage = !editingStage" 
                    class="p-0.5 ml-0.5 rounded text-ink-3 hover:text-ink-2 hover:bg-sunken transition" 
                    title="{{ __('Edit or delete phase') }}">
                    <x-lucide-more-vertical class="w-3 h-3" />
                </button>

                <!-- Stage Edit Modal / Popover -->
                <div 
                    x-show="editingStage" 
                    x-cloak 
                    @click.away="editingStage = false" 
                    class="absolute left-0 top-full mt-1.5 z-40 bg-surface border border-line rounded-card p-3 shadow-menu w-64 space-y-2 text-xs"
                >
                    <div class="font-semibold text-ink border-b border-line pb-1.5 flex items-center justify-between">
                        <span>{{ __('Edit Phase') }}</span>
                        <button type="button" @click="editingStage = false" class="text-ink-3 hover:text-ink-2">
                            <x-lucide-x class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-meta text-ink-3 font-medium">{{ __('Phase name:') }}</label>
                        <input 
                            type="text" 
                            x-model="stageName" 
                            class="w-full bg-canvas border border-line rounded-control p-1.5 text-xs text-ink focus:outline-none focus:border-line-strong"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-meta text-ink-3 font-medium">{{ __('Due Date:') }}</label>
                        <input 
                            type="date" 
                            x-model="stageDueDate" 
                            class="w-full bg-canvas border border-line rounded-control p-1.5 text-xs text-ink focus:outline-none focus:border-line-strong"
                        />
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-line">
                        <button 
                            type="button" 
                            wire:click="deleteStage({{ $stage->id }})" 
                            wire:confirm="{{ __('Delete this phase? Assigned items will remain without a phase.') }}"
                            @click="editingStage = false"
                            class="text-danger hover:text-danger font-semibold text-meta cursor-pointer">
                            {{ __('Delete') }}
                        </button>
                        <button 
                            type="button" 
                            @click="$wire.updateStage({{ $stage->id }}, stageName, stageDueDate); editingStage = false;"
                            class="px-2.5 py-1 rounded bg-ink hover:bg-ink text-white font-semibold text-meta cursor-pointer">
                            {{ __('Save') }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    <!-- Quick Add Element Bar -->
    <div 
        x-data="{ showSpecs: false }" 
        class="bg-canvas border border-line rounded-card p-2.5 space-y-2 "
    >
        <div class="flex items-center gap-2">
            <input 
                type="text" 
                wire:model="newElementName" 
                placeholder="{{ __('Add print item (e.g. Main Wall 4x2.5m, 3D Sign)...') }}"
                @keydown.enter.prevent="$wire.addElement()"
                class="ui-input flex-1"
            />
            
            <button 
                type="button" 
                @click="showSpecs = !showSpecs" 
                class="ui-btn ui-btn-secondary"
                title="{{ __('Add dimensions, material, or phase details') }}">
                <x-lucide-sliders-horizontal />
                <span class="hidden sm:inline">{{ __('Details') }}</span>
            </button>

            <button 
                type="button" 
                wire:click="$toggle('showNewStageForm')"
                class="ui-btn ui-btn-secondary"
                title="{{ __('Create new phase to group items') }}">
                <x-lucide-folder-plus />
                <span>{{ __('New stage') }}</span>
            </button>

            <button 
                type="button" 
                wire:click="addElement" 
                class="ui-btn ui-btn-secondary">
                <x-lucide-plus />
                <span>{{ __('Add item') }}</span>
            </button>
        </div>

        <!-- Optional Specs Collapsible -->
        <div x-show="showSpecs" x-collapse class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-1 border-t border-line/80 text-xs">
            <div>
                <label class="ui-label !mb-1">{{ __('Measurements') }}</label>
                <input 
                    type="text" 
                    wire:model="newElementDimensions" 
                    placeholder="{{ __('e.g. 3.5m x 2.2m') }}"
                    class="ui-input"
                />
            </div>
            <div>
                <label class="ui-label !mb-1">{{ __('Material or substrate') }}</label>
                <input 
                    type="text" 
                    wire:model="newElementMaterial" 
                    placeholder="{{ __('e.g. Matte vinyl, 5mm acrylic') }}"
                    class="ui-input"
                />
            </div>
            <div>
                <label class="ui-label !mb-1">{{ __('Quantity') }}</label>
                <input 
                    type="number" 
                    min="1" 
                    wire:model="newElementQuantity" 
                    class="ui-input"
                />
            </div>
            <div>
                <label class="ui-label !mb-1">{{ __('Assign to stage') }}</label>
                <select 
                    wire:model="newElementStageId" 
                    class="ui-select"
                >
                    <option value="">{{ __('No Phase (General)') }}</option>
                    @foreach($stages as $stg)
                        <option value="{{ $stg->id }}">{{ $stg->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Elements List -->
    <div class="space-y-1.5">
        @forelse($filteredElements as $element)
            <div 
                wire:key="element-item-{{ $element->id }}"
                x-data="{ 
                    editing: false, 
                    copied: false,
                    title: @js($element->name),
                    openPathModal: false,
                    filePath: @js($element->file_url),
                    productionNotes: @js($element->production_notes),
                    saveTitle() {
                        const trimmed = this.title.trim();
                        if (trimmed && trimmed !== @js($element->name)) {
                            $wire.updateElementTitle({{ $element->id }}, trimmed);
                        } else {
                            this.title = @js($element->name);
                        }
                        this.editing = false;
                    },
                    copyPath() {
                        if (this.filePath) {
                            navigator.clipboard.writeText(this.filePath);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        }
                    }
                }"
                class="hover:bg-sunken rounded-control px-2.5 py-2 flex items-center justify-between text-xs gap-2.5 transition group relative"
            >
                <!-- Left: Approval Checkbox & Title & Specs -->
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <!-- Individual Approval Checkbox Button -->
                    <button 
                        type="button"
                        wire:click="toggleElementApproved({{ $element->id }})" 
                        class="w-4 h-4 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $element->is_approved ? 'bg-ok border-ok/30 text-white ' : 'border-line-strong hover:border-ok/30 bg-surface text-transparent hover:text-ok' }}"
                        title="{{ $element->is_approved ? __('Item approved (:type) on :date. Click to uncheck', ['type' => $element->approval_type ?? 'OK', 'date' => $element->approved_at?->translatedFormat('d M') ?? 'sin fecha']) : __('Mark approval for this item') }}">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                    </button>

                    <!-- Element Title (Click to edit inline) -->
                    <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
                        <div x-show="!editing" class="flex items-center gap-1.5 min-w-0">
                            <span 
                                @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                class="font-medium text-ink text-body break-words cursor-pointer hover:underline decoration-line-strong underline-offset-2 transition {{ $element->isReadyForProduction() ? 'text-ink-2' : '' }}" 
                                title="{{ __('Click to edit item name') }}">
                                {{ $element->name }}
                            </span>

                            @if($element->quantity > 1)
                                <span class="text-meta text-ink-3 tabular-nums">
                                    x{{ $element->quantity }}
                                </span>
                            @endif
                        </div>

                        <!-- Inline Edit Input -->
                        <div x-show="editing" x-cloak class="flex items-center gap-1 min-w-0 flex-1">
                            <input 
                                x-ref="editInput"
                                type="text" 
                                x-model="title" 
                                @keydown.enter="saveTitle()" 
                                @keydown.escape="title = @js($element->name); editing = false;"
                                @blur="saveTitle()" 
                                class="ui-input h-7"
                            />
                        </div>

                        <!-- Specs Badges -->
                        @if($element->dimensions)
                            <span class="inline-flex items-center gap-1 text-meta text-ink-3" title="{{ __('Dimensions / Measurements') }}">
                                <x-lucide-ruler class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ $element->dimensions }}</span>
                            </span>
                        @endif

                        @if($element->material)
                            <span class="inline-flex items-center gap-1 text-meta text-ink-3" title="{{ __('Material') }}">
                                <x-lucide-box class="w-3.5 h-3.5 text-ink-3" />
                                <span class="truncate max-w-[120px]">{{ $element->material }}</span>
                            </span>
                        @endif

                        <!-- Stage Assignment Badge -->
                        @if(!$selectedStageFilter && $element->stage)
                            <span class="inline-flex items-center gap-1 text-meta text-ink-3" title="{{ __('Assigned phase') }}">
                                <x-lucide-folder class="w-3.5 h-3.5 text-ink-3" />
                                <span>{{ $element->stage->name }}</span>
                            </span>
                        @endif

                        <!-- Approval Info Badge if Approved -->
                        @if($element->is_approved)
                            <span class="inline-flex items-center gap-1 text-meta font-medium text-ok" title="{{ __('Approved by :type (:date)', ['type' => $element->approval_type ?? 'OK', 'date' => $element->approved_at?->format('d/m/Y') ?? 'Sin fecha']) }}">
                                <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-ok" />
                                <span>{{ $element->approval_type ?? __('Approved') }}</span>
                                @if($element->approved_at)
                                    <span class="font-normal text-ok">({{ $element->approved_at->translatedFormat('d M') }})</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Right: Status Toggle & Path & Actions -->
                <div class="flex items-center gap-1.5 shrink-0">
                    <!-- Alta Status Toggle Button -->
                    <button
                        type="button"
                        wire:click="toggleElementAlta({{ $element->id }})"
                        class="ui-btn ui-btn-sm {{ $element->isReadyForProduction() ? 'ui-btn-ghost' : 'ui-btn-secondary' }}"
                        title="{{ $element->isReadyForProduction() ? __('Item For Print / Production (click to revert)') : ($element->is_approved ? __('Click to mark this item for print') : __('Requires approval before setting for print')) }}">
                        @if($element->isReadyForProduction())
                            <x-lucide-check />
                            <span>{{ __('For Print') }}</span>
                        @elseif($element->is_approved)
                            <span>{{ __('Set for Print') }}</span>
                        @else
                            <span>{{ __('Pending') }}</span>
                        @endif
                    </button>

                    <!-- Network/Server File Path Badge & Popover -->
                    @if($element->file_url)
                        <button 
                            type="button" 
                            @click.stop="copyPath()"
                            class="ui-btn ui-btn-ghost ui-btn-sm select-none"
                            :class="copied ? 'text-ok' : ''"
                            title="{{ __('Click to copy server path: ') . $element->file_url }}"
                        >
                            <template x-if="!copied">
                                <span class="flex items-center gap-1">
                                    <x-lucide-hard-drive class="w-3 h-3 text-ink-2 shrink-0" />
                                    <span class="truncate max-w-[80px] sm:max-w-[120px] text-ink-2 font-semibold">{{ basename(str_replace('\\', '/', $element->file_url)) ?: __('Path') }}</span>
                                    <x-lucide-copy class="w-2.5 h-2.5 text-ink-3 group-hover:text-ink-2" />
                                </span>
                            </template>
                            <template x-if="copied">
                                <span class="flex items-center gap-1 text-ok font-semibold">
                                    <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                    <span>{{ __('Copied!') }}</span>
                                </span>
                            </template>
                        </button>
                    @endif

                    <!-- Path Popover Toggle -->
                    <div class="relative">
                        <button 
                            type="button" 
                            @click="openPathModal = !openPathModal" 
                            class="p-1 rounded text-ink-3 hover:text-ink-2 hover:bg-sunken transition {{ $element->file_url ? 'text-ink-2 hover:text-ink-2' : '' }}" 
                            title="{{ $element->file_url ? __('Edit file path on server') : __('Add file path on server / Kudos network') }}">
                            <x-lucide-folder-input class="w-3.5 h-3.5" />
                        </button>

                        <div 
                            x-show="openPathModal" 
                            x-cloak 
                            @click.away="openPathModal = false" 
                            class="absolute right-0 top-full mt-1.5 z-40 bg-surface border border-line rounded-card p-3 shadow-menu w-80 space-y-2.5 text-xs text-left"
                        >
                            <div class="font-semibold text-ink border-b border-line pb-1.5 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-lucide-hard-drive class="w-4 h-4 text-ink-2" />
                                    <span>{{ __('Server / Kudos Network Path') }}</span>
                                </span>
                                <button type="button" @click="openPathModal = false" class="text-ink-3 hover:text-ink-2 cursor-pointer">
                                    <x-lucide-x class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-meta text-ink-2 font-semibold">{{ __('Local or network path:') }}</label>
                                    <template x-if="filePath">
                                        <button 
                                            type="button" 
                                            @click="navigator.clipboard.writeText(filePath); copied = true; setTimeout(() => copied = false, 2000)" 
                                            class="text-meta text-ink-2 hover:underline flex items-center gap-0.5 cursor-pointer font-medium">
                                            <x-lucide-copy class="w-2.5 h-2.5" />
                                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy path'))"></span>
                                        </button>
                                    </template>
                                </div>
                                <input 
                                    type="text" 
                                    x-model="filePath" 
                                    placeholder="{{ __('e.g. :path', ['path' => '\\\\server\\Kudos\\Clients\\...\\artwork.ai']) }}"
                                    class="w-full bg-canvas border border-line rounded-control p-2 font-mono text-xs text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong"
                                />
                                <span class="text-meta text-ink-3 block">{{ __('Enter network path or shared folder for the print-ready file.') }}</span>
                            </div>
                            <div class="space-y-1">
                                <label class="text-meta text-ink-2 font-semibold">{{ __('Production workshop notes:') }}</label>
                                <textarea 
                                    x-model="productionNotes" 
                                    rows="2"
                                    placeholder="{{ __('Bleed, cutting, material, or color specs...') }}"
                                    class="w-full bg-canvas border border-line rounded-control p-1.5 text-xs text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong resize-none"
                                ></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-line">
                                <button 
                                    type="button" 
                                    @click="openPathModal = false"
                                    class="px-2.5 py-1 rounded text-xs font-medium text-ink-2 hover:bg-sunken transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="$wire.updateElementFileUrl({{ $element->id }}, filePath, productionNotes); openPathModal = false;"
                                    class="px-3 py-1 rounded-control bg-ink hover:bg-ink text-white font-semibold text-xs cursor-pointer  transition">
                                    {{ __('Save Path') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stage Reassign Dropdown -->
                    <select 
                        wire:change="moveElementStage({{ $element->id }}, $event.target.value)" 
                        class="ui-select !h-7 !w-auto max-w-[110px] text-meta"
                        title="{{ __('Move to another phase') }}"
                    >
                        <option value="">{{ __('No Phase') }}</option>
                        @foreach($stages as $stgOpt)
                            <option value="{{ $stgOpt->id }}" {{ $element->order_stage_id === $stgOpt->id ? 'selected' : '' }}>
                                {{ $stgOpt->name }}
                            </option>
                        @endforeach
                    </select>

                    <!-- Delete Button -->
                    <button 
                        type="button" 
                        wire:click="deleteElement({{ $element->id }})" 
                        wire:confirm="{{ __('Delete this item?') }}" 
                        class="p-1 rounded text-ink-3 hover:text-danger hover:bg-danger-soft transition opacity-0 group-hover:opacity-100 cursor-pointer" 
                        title="{{ __('Delete item') }}">
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        @empty
            <div class="text-center py-6 bg-canvas border border-dashed border-line rounded-card text-ink-3 text-xs space-y-1">
                <p>{{ __('No items registered for print in this order.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Piece Approval Modal Dialog -->
    @if($showPieceApprovalModal && $pieceApprovalElementId)
        @php
            $targetElement = \App\Models\OrderElement::find($pieceApprovalElementId);
        @endphp
        @if($targetElement)
            <div 
                class="fixed inset-0 z-[350] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 animate-fadeIn"
                @keydown.window.escape.prevent="$wire.closePieceApprovalModal()">
                <div 
                    @click.away="$wire.closePieceApprovalModal()"
                    class="bg-surface border border-line rounded-card w-full max-w-2xl p-5 sm:p-6 space-y-4 shadow-panel max-h-[92vh] flex flex-col animate-scaleIn text-left"
                >
                    <!-- Header -->
                    <div class="shrink-0 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-ink">
                                {{ $pieceApprovalDirectToAlta ? __('Approve item and set for print') : __('Item Approval Confirmation') }}
                            </h3>
                            <p class="text-xs text-ink-3 mt-0.5">
                                {{ $pieceApprovalDirectToAlta 
                                    ? __('Record required validation to authorize and send this item directly for print.') 
                                    : __('Validate approval and record details to authorize this item.') }}
                            </p>
                        </div>
                        <button 
                            type="button" 
                            wire:click="closePieceApprovalModal" 
                            class="p-1 rounded-control text-ink-3 hover:text-ink-2 hover:bg-sunken transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Scrollable Modal Content -->
                    <div class="overflow-y-auto pr-1 space-y-4 text-xs scrollbar-thin flex-1">
                        <!-- Target Piece Summary Box -->
                        <div class="p-3.5 bg-canvas border border-line rounded-card flex items-center justify-between gap-3 shrink-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-control bg-ok-soft border border-ok/30 flex items-center justify-center shrink-0 text-ok">
                                    <x-lucide-layers class="w-4.5 h-4.5" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-meta text-ink-3   font-semibold block">{{ __('Item to authorize') }}</span>
                                    <span class="text-sm font-semibold text-ink truncate block">{{ $targetElement->name }}</span>
                                    <div class="flex items-center gap-2 mt-0.5 text-meta text-ink-3 font-mono">
                                        @if($targetElement->dimensions) <span class="bg-surface px-1.5 py-0.5 rounded border border-line">{{ $targetElement->dimensions }}</span> @endif
                                        @if($targetElement->material) <span class="bg-surface px-1.5 py-0.5 rounded border border-line">{{ $targetElement->material }}</span> @endif
                                        @if($targetElement->quantity > 1) <span class="bg-surface px-1.5 py-0.5 rounded border border-line font-semibold">{{ __('Qty:') }} {{ $targetElement->quantity }}</span> @endif
                                    </div>
                                </div>
                            </div>
                            @if($targetElement->stage)
                                <span class="text-meta font-semibold text-ink-2 bg-sunken border border-line px-2.5 py-1 rounded-control shrink-0 flex items-center gap-1">
                                    <x-lucide-git-branch class="w-3 h-3 text-ink-2" />
                                    <span>{{ $targetElement->stage->name }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- 1. Who approved? (Camila / Manager / Cliente) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-ink">
                                {{ __('Who validated the approval?') }} <span class="text-danger">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <!-- Camila -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'camila')" 
                                    class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $pieceApprovalType === 'camila' ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ $pieceApprovalType === 'camila' ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                            <span class="text-sm sm:text-base font-semibold {{ $pieceApprovalType === 'camila' ? 'text-ok' : 'text-ink' }} block  mt-0.5 leading-snug">{{ __('Camila') }}</span>
                                        </div>
                                        @if($pieceApprovalType === 'camila')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-meta {{ $pieceApprovalType === 'camila' ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                        {{ __('Internal validation') }}
                                    </div>
                                </button>

                                <!-- Manager -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'manager')" 
                                    class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                            <div class="flex items-baseline gap-1 mt-0.5 flex-wrap">
                                                <span class="text-sm sm:text-base font-semibold {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-ok' : 'text-ink' }}  leading-snug">{{ $managerName ?? \App\Models\Setting::getManagerName() }}</span>
                                                <span class="text-xs font-normal {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-ok' : 'text-ink-3' }}">({{ __('Manager') }})</span>
                                            </div>
                                        </div>
                                        @if($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-meta {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                        {{ __('Manager Validation') }}
                                    </div>
                                </button>

                                <!-- Cliente -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'cliente')" 
                                    class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $pieceApprovalType === 'cliente' ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ $pieceApprovalType === 'cliente' ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                            <span class="text-sm sm:text-base font-semibold {{ $pieceApprovalType === 'cliente' ? 'text-ok' : 'text-ink' }} block  mt-0.5 leading-snug">{{ __('Client') }}</span>
                                        </div>
                                        @if($pieceApprovalType === 'cliente')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-meta {{ $pieceApprovalType === 'cliente' ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                        {{ __('Direct confirmation') }}
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- 2. When / Date (Hoy / Ayer / Otra fecha / Sin fecha) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-ink">
                                {{ __('When was it approved?') }} <span class="text-danger">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'today')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-card border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'today' ? 'border-ok/30 bg-ok-soft text-ok font-semibold ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2 font-medium' }}">
                                    <x-lucide-calendar class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'today' ? 'text-ok' : 'text-ink-3' }}" />
                                    <span>{{ __('Today') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'yesterday')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-card border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'yesterday' ? 'border-ok/30 bg-ok-soft text-ok font-semibold ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2 font-medium' }}">
                                    <x-lucide-history class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'yesterday' ? 'text-ok' : 'text-ink-3' }}" />
                                    <span>{{ __('Yesterday') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'custom')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-card border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'custom' ? 'border-ok/30 bg-ok-soft text-ok font-semibold ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2 font-medium' }}">
                                    <x-lucide-calendar-days class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'custom' ? 'text-ok' : 'text-ink-3' }}" />
                                    <span>{{ __('Specific date') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'none')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-card border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'none' ? 'border-ok/30 bg-ok-soft text-ok font-semibold ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2 font-medium' }}">
                                    <x-lucide-minus-circle class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'none' ? 'text-ok' : 'text-ink-3' }}" />
                                    <span>{{ __('No date') }}</span>
                                </button>
                            </div>

                            @if($pieceApprovalDatePreset === 'custom')
                                <div class="pt-1.5 animate-fadeIn">
                                    <label class="block text-meta text-ink-2 font-medium mb-1">{{ __('Select the approval date:') }}</label>
                                    <input 
                                        type="date" 
                                        wire:model="pieceApprovalCustomDate" 
                                        class="w-full bg-canvas border border-line focus:border-ok/30 focus:ring-2 focus:ring-line-strong rounded-card px-3 py-2 text-xs text-ink font-mono transition outline-none"
                                    />
                                </div>
                            @endif
                        </div>

                        <!-- 3. Support & Additional Notes (Required) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-ink">
                                {{ __('Approval proof and additional notes') }} <span class="text-danger">*</span>
                            </label>
                            <div class="relative border rounded-card p-3 bg-surface transition-all {{ $errors->has('pieceApprovalNote') ? 'border-danger/30 ring-2 ring-line-strong' : 'border-line focus-within:border-ok/30 focus-within:ring-2 focus-within:ring-line-strong' }}">
                                <textarea 
                                    wire:model="pieceApprovalNote" 
                                    rows="2"
                                    placeholder="{{ __('E.g. Via WhatsApp / Email / Confirmed with Camila...') }}"
                                    class="w-full text-xs text-ink placeholder:text-ink-3 bg-transparent resize-none border-0 p-0 focus:outline-none focus:ring-0 leading-relaxed outline-none"></textarea>
                            </div>
                            @error('pieceApprovalNote')
                                <p class="text-xs text-danger font-medium flex items-center gap-1 mt-1">
                                    <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-line shrink-0">
                        <button 
                            type="button" 
                            wire:click="closePieceApprovalModal" 
                            class="px-3.5 py-1.5 rounded-control bg-sunken hover:bg-line text-ink-2 text-xs font-medium transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button 
                            type="button" 
                            wire:click="submitPieceApproval" 
                            wire:loading.attr="disabled"
                            class="px-4 py-1.5 rounded-control bg-ok hover:bg-ok disabled:opacity-50 text-white font-medium text-xs  transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-loader-2 wire:loading wire:target="submitPieceApproval" class="w-3.5 h-3.5 animate-spin" />
                            @if($pieceApprovalDirectToAlta)
                                <x-lucide-rocket wire:loading.remove wire:target="submitPieceApproval" class="w-3.5 h-3.5" />
                                <span wire:loading.remove wire:target="submitPieceApproval">{{ __('Approve and Set for Print') }}</span>
                            @else
                                <x-lucide-check wire:loading.remove wire:target="submitPieceApproval" class="w-3.5 h-3.5 stroke-[3]" />
                                <span wire:loading.remove wire:target="submitPieceApproval">{{ __('Confirm Approval') }}</span>
                            @endif
                            <span wire:loading wire:target="submitPieceApproval">{{ __('Processing...') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
