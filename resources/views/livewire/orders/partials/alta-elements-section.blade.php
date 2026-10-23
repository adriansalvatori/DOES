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
    <x-orders.tab-section-header :title="__('PIEZAS Y ETAPAS')">
        <x-slot:icon>
            <x-lucide-layers class="w-4.5 h-4.5 text-pink-600 shrink-0" />
        </x-slot:icon>
        @if($totalElements > 0)
            <x-slot:badge>
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-stone-100 text-zinc-600 border border-stone-200">
                    {{ $totalElements }} {{ $totalElements === 1 ? __('pieza') : __('piezas') }}
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
                    <span class="text-xs font-bold text-zinc-900 flex items-center gap-1.5">
                        <x-lucide-rocket class="w-3.5 h-3.5 text-pink-600" />
                        <span>{{ __('Progreso de Producción') }}</span>
                    </span>
                    <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded-md border transition {{ $altaReady === $totalElements ? 'bg-emerald-50 text-emerald-800 border-emerald-300 shadow-2xs' : 'bg-pink-50 text-pink-800 border-pink-200' }}">
                        {{ $altaReady }} {{ __('de') }} {{ $totalElements }} {{ __('en alta') }}
                    </span>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-xs font-mono font-bold tracking-tight {{ $altaPercent === 100 ? 'text-emerald-600' : 'text-zinc-700' }}">
                        {{ $altaPercent }}%
                    </span>
                    @if($altaPercent === 100)
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 shrink-0">
                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                        </span>
                    @endif
                </div>
            </div>

            <!-- Visual Progress Bar: Thinner (h-1.5), identical to subtasks -->
            <div class="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden flex gap-0.5">
                <!-- Segment 1: En Alta (Pink gradient or Emerald if 100%) -->
                @if($readyPercent > 0)
                    <div 
                        class="h-full rounded-full transition-all duration-300 {{ $altaPercent === 100 ? 'bg-emerald-500' : 'bg-pink-500' }}" 
                        style="width: {{ $readyPercent }}%"
                        title="{{ __('Piezas en Alta:') }} {{ $altaReady }}/{{ $totalElements }} ({{ $readyPercent }}%)"
                    ></div>
                @endif

                <!-- Segment 2: Aprobadas pendientes de poner en alta -->
                @if($onlyApprovedPercent > 0)
                    <div 
                        class="h-full rounded-full transition-all duration-300 bg-amber-400" 
                        style="width: {{ $onlyApprovedPercent }}%"
                        title="{{ __('Aprobadas pendientes de subir en alta:') }} {{ $onlyApprovedCount }}/{{ $totalElements }}"
                    ></div>
                @endif
            </div>

            <!-- Single Line Status / Missing Count Indicator (replaces old multi-legend) -->
            <div class="flex items-center justify-between text-[11px] text-zinc-500 pt-0.5">
                <div>
                    @php
                        $missingAlta = max(0, $totalElements - $altaReady);
                    @endphp
                    @if($altaPercent === 100)
                        <span class="text-emerald-700 font-semibold text-[11px] flex items-center gap-1">
                            <x-lucide-sparkles class="w-3.5 h-3.5 text-emerald-600" />
                            <span>{{ __('¡Todas las piezas listas para taller!') }}</span>
                        </span>
                    @elseif($missingAlta === 1)
                        <span class="text-zinc-500 text-[11px]">
                            {{ __('Falta 1 por poner en alta') }}
                        </span>
                    @elseif($missingAlta > 1)
                        <span class="text-zinc-500 text-[11px]">
                            {{ __('Faltan :count por poner en alta', ['count' => $missingAlta]) }}
                        </span>
                    @else
                        <span class="text-zinc-400 text-[11px]">
                            {{ __('Pendiente de enviar a producción') }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- New Stage Form (Collapsible) -->
    @if($showNewStageForm)
        <div class="p-3 bg-pink-50/50 border border-pink-200 rounded-xl space-y-2.5 transition animate-fadeIn">
            <div class="flex items-center justify-between">
                <span class="font-bold text-xs text-pink-950 flex items-center gap-1.5">
                    <x-lucide-calendar class="w-3.5 h-3.5 text-pink-600" />
                    <span>{{ __('Nueva Etapa de Producción') }}</span>
                </span>
                <button type="button" wire:click="$set('showNewStageForm', false)" class="text-zinc-400 hover:text-zinc-600">
                    <x-lucide-x class="w-3.5 h-3.5" />
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="sm:col-span-2">
                    <input 
                        type="text" 
                        wire:model="newStageName" 
                        placeholder="{{ __('Nombre de la etapa (ej: Etapa 1 - Fachada Exterior)...') }}"
                        @keydown.enter.prevent="$wire.addStage()"
                        class="w-full bg-white border border-pink-200 rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-pink-500"
                    />
                </div>
                <div>
                    <input 
                        type="date" 
                        wire:model="newStageDueDate" 
                        class="w-full bg-white border border-pink-200 rounded-lg px-2.5 py-1.5 text-xs text-zinc-700 focus:outline-none focus:border-pink-500"
                        title="{{ __('Fecha límite para completar esta etapa') }}"
                    />
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button 
                    type="button" 
                    wire:click="$set('showNewStageForm', false)" 
                    class="px-2.5 py-1 rounded-lg text-xs font-medium text-zinc-600 hover:bg-stone-100 transition cursor-pointer">
                    {{ __('Cancelar') }}
                </button>
                <button 
                    type="button" 
                    wire:click="addStage" 
                    class="px-3 py-1 rounded-lg bg-pink-600 hover:bg-pink-700 text-white text-xs font-bold transition shadow-2xs cursor-pointer flex items-center gap-1">
                    <x-lucide-plus class="w-3.5 h-3.5" />
                    <span>{{ __('Crear Etapa') }}</span>
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
            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer border flex items-center gap-1.5 {{ $selectedStageFilter === null ? 'bg-zinc-900 text-white border-zinc-900 shadow-2xs' : 'bg-white hover:bg-stone-50 border-[#e9e9e7] text-zinc-600' }}">
            <span>{{ __('Todas') }}</span>
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
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer border flex items-center gap-1.5 {{ $isStageActive ? 'bg-pink-600 text-white border-pink-600 shadow-2xs' : 'bg-white hover:bg-stone-50 border-[#e9e9e7] text-zinc-700' }}">
                    <span class="truncate max-w-[140px]">{{ $stage->name }}</span>
                    @if($stage->due_date)
                        <span class="text-[9.5px] font-mono px-1 py-0.2 rounded {{ $isStageActive ? 'bg-pink-700 text-pink-100' : 'bg-stone-100 text-stone-600' }}" title="{{ __('Vence') }}: {{ $stage->due_date->format('d M, Y') }}">
                            {{ $stage->due_date->format('d M') }}
                        </span>
                    @endif
                    <span class="text-[10px] font-mono px-1 rounded {{ $isStageActive ? 'bg-pink-700 text-white' : ($stageReady === $stageTotal && $stageTotal > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-zinc-500') }}">
                        {{ $stageReady }}/{{ $stageTotal }}
                    </span>
                </button>

                <!-- Stage actions popover button -->
                <button 
                    type="button" 
                    @click.stop="editingStage = !editingStage" 
                    class="p-0.5 ml-0.5 rounded text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition" 
                    title="{{ __('Editar o eliminar etapa') }}">
                    <x-lucide-more-vertical class="w-3 h-3" />
                </button>

                <!-- Stage Edit Modal / Popover -->
                <div 
                    x-show="editingStage" 
                    x-cloak 
                    @click.away="editingStage = false" 
                    class="absolute left-0 top-full mt-1.5 z-40 bg-white border border-[#e9e9e7] rounded-xl p-3 shadow-xl w-64 space-y-2 text-xs"
                >
                    <div class="font-bold text-zinc-900 border-b border-[#e9e9e7] pb-1.5 flex items-center justify-between">
                        <span>{{ __('Editar Etapa') }}</span>
                        <button type="button" @click="editingStage = false" class="text-zinc-400 hover:text-zinc-600">
                            <x-lucide-x class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-zinc-500 font-medium">{{ __('Nombre de la etapa:') }}</label>
                        <input 
                            type="text" 
                            x-model="stageName" 
                            class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] text-zinc-500 font-medium">{{ __('Fecha límite (Due Date):') }}</label>
                        <input 
                            type="date" 
                            x-model="stageDueDate" 
                            class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-1.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                        />
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-[#e9e9e7]">
                        <button 
                            type="button" 
                            wire:click="deleteStage({{ $stage->id }})" 
                            wire:confirm="{{ __('¿Eliminar esta etapa? Las piezas asignadas quedarán sin etapa.') }}"
                            @click="editingStage = false"
                            class="text-rose-600 hover:text-rose-800 font-semibold text-[11px] cursor-pointer">
                            {{ __('Eliminar') }}
                        </button>
                        <button 
                            type="button" 
                            @click="$wire.updateStage({{ $stage->id }}, stageName, stageDueDate); editingStage = false;"
                            class="px-2.5 py-1 rounded bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-[11px] cursor-pointer">
                            {{ __('Guardar') }}
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
        class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl p-2.5 space-y-2 shadow-2xs"
    >
        <div class="flex items-center gap-2">
            <input 
                type="text" 
                wire:model="newElementName" 
                placeholder="{{ __('Añadir pieza en alta (ej. Pared Principal 4x2.5m, Aviso 3D)...') }}"
                @keydown.enter.prevent="$wire.addElement()"
                class="flex-1 bg-white border border-[#e9e9e7] rounded-lg px-2.5 py-1.5 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400"
            />
            
            <button 
                type="button" 
                @click="showSpecs = !showSpecs" 
                class="px-2 py-1.5 rounded-lg border border-[#e9e9e7] bg-white hover:bg-stone-50 text-[11px] font-medium text-zinc-600 flex items-center gap-1 transition shrink-0 cursor-pointer"
                title="{{ __('Añadir detalles de medidas, material o etapa') }}">
                <x-lucide-sliders-horizontal class="w-3.5 h-3.5 text-zinc-500" />
                <span class="hidden sm:inline">{{ __('Detalles') }}</span>
            </button>

            <button 
                type="button" 
                wire:click="$toggle('showNewStageForm')"
                class="px-2.5 py-1.5 rounded-lg border border-stone-200 hover:border-pink-200 bg-white hover:bg-pink-50/50 text-zinc-700 hover:text-pink-700 font-semibold text-xs transition cursor-pointer flex items-center gap-1.5 shrink-0 shadow-2xs"
                title="{{ __('Crear nueva etapa para agrupar piezas') }}">
                <x-lucide-folder-plus class="w-3.5 h-3.5 text-pink-600" />
                <span>{{ __('+ Nueva Etapa') }}</span>
            </button>

            <button 
                type="button" 
                wire:click="addElement" 
                class="px-3.5 py-1.5 rounded-lg bg-pink-600 hover:bg-pink-700 text-white font-bold text-xs shrink-0 shadow-2xs transition cursor-pointer flex items-center gap-1">
                <x-lucide-plus class="w-3.5 h-3.5 stroke-[2.5]" />
                <span>{{ __('+ Añadir Pieza') }}</span>
            </button>
        </div>

        <!-- Optional Specs Collapsible -->
        <div x-show="showSpecs" x-collapse class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-1 border-t border-[#e9e9e7]/80 text-xs">
            <div>
                <label class="block text-[10px] text-zinc-500 font-medium mb-0.5">{{ __('Medidas:') }}</label>
                <input 
                    type="text" 
                    wire:model="newElementDimensions" 
                    placeholder="{{ __('ej: 3.5m x 2.2m') }}"
                    class="w-full bg-white border border-[#e9e9e7] rounded-lg px-2 py-1 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400"
                />
            </div>
            <div>
                <label class="block text-[10px] text-zinc-500 font-medium mb-0.5">{{ __('Material / Sustrato:') }}</label>
                <input 
                    type="text" 
                    wire:model="newElementMaterial" 
                    placeholder="{{ __('ej: Vinilo mate, Acrílico 5mm') }}"
                    class="w-full bg-white border border-[#e9e9e7] rounded-lg px-2 py-1 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400"
                />
            </div>
            <div>
                <label class="block text-[10px] text-zinc-500 font-medium mb-0.5">{{ __('Cantidad:') }}</label>
                <input 
                    type="number" 
                    min="1" 
                    wire:model="newElementQuantity" 
                    class="w-full bg-white border border-[#e9e9e7] rounded-lg px-2 py-1 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                />
            </div>
            <div>
                <label class="block text-[10px] text-zinc-500 font-medium mb-0.5">{{ __('Asignar a Etapa:') }}</label>
                <select 
                    wire:model="newElementStageId" 
                    class="w-full bg-white border border-[#e9e9e7] rounded-lg px-2 py-1 text-xs text-zinc-800 focus:outline-none focus:border-stone-400"
                >
                    <option value="">{{ __('Sin Etapa (General)') }}</option>
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
                class="hover:bg-stone-100/70 rounded-lg px-2.5 py-2 flex items-center justify-between text-xs gap-2.5 transition group relative"
            >
                <!-- Left: Approval Checkbox & Title & Specs -->
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <!-- Individual Approval Checkbox Button -->
                    <button 
                        type="button"
                        wire:click="toggleElementApproved({{ $element->id }})" 
                        class="w-4 h-4 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $element->is_approved ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                        title="{{ $element->is_approved ? __('Pieza aprobada (:type) el :date. Clic para desmarcar', ['type' => $element->approval_type ?? 'OK', 'date' => $element->approved_at?->format('d M') ?? 'sin fecha']) : __('Marcar aprobación de esta pieza') }}">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                    </button>

                    <!-- Element Title (Click to edit inline) -->
                    <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
                        <div x-show="!editing" class="flex items-center gap-1.5 min-w-0">
                            <span 
                                @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                class="font-semibold text-zinc-900 text-xs break-words cursor-pointer hover:text-pink-600 hover:underline decoration-stone-300 underline-offset-2 transition {{ $element->isReadyForProduction() ? 'text-zinc-700' : '' }}" 
                                title="{{ __('Clic para editar el nombre de la pieza') }}">
                                {{ $element->name }}
                            </span>

                            @if($element->quantity > 1)
                                <span class="px-1.5 py-0.2 rounded bg-stone-100 text-stone-700 font-mono text-[10px] font-bold border border-stone-200">
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
                                class="w-full bg-white border border-stone-300 rounded px-2 py-0.5 text-xs text-zinc-800 focus:outline-none focus:border-stone-500"
                            />
                        </div>

                        <!-- Specs Badges -->
                        @if($element->dimensions)
                            <span class="inline-flex items-center gap-1 text-[9.5px] font-mono text-zinc-600 bg-zinc-100 px-1.5 py-0.2 rounded border border-zinc-200" title="{{ __('Dimensiones / Medidas') }}">
                                <x-lucide-ruler class="w-2.5 h-2.5 text-zinc-500" />
                                <span>{{ $element->dimensions }}</span>
                            </span>
                        @endif

                        @if($element->material)
                            <span class="inline-flex items-center gap-1 text-[9.5px] text-zinc-600 bg-zinc-100 px-1.5 py-0.2 rounded border border-zinc-200" title="{{ __('Material') }}">
                                <x-lucide-box class="w-2.5 h-2.5 text-zinc-500" />
                                <span class="truncate max-w-[120px]">{{ $element->material }}</span>
                            </span>
                        @endif

                        <!-- Stage Assignment Badge -->
                        @if(!$selectedStageFilter && $element->stage)
                            <span class="inline-flex items-center gap-1 text-[9px] font-semibold text-pink-700 bg-pink-50 px-1.5 py-0.2 rounded border border-pink-200" title="{{ __('Etapa asignada') }}">
                                <x-lucide-folder class="w-2.5 h-2.5 text-pink-600" />
                                <span>{{ $element->stage->name }}</span>
                            </span>
                        @endif

                        <!-- Approval Info Badge if Approved -->
                        @if($element->is_approved)
                            <span class="inline-flex items-center gap-1 text-[9px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200" title="{{ __('Aprobado por :type (:date)', ['type' => $element->approval_type ?? 'OK', 'date' => $element->approved_at?->format('d/m/Y') ?? 'Sin fecha']) }}">
                                <x-lucide-check-circle-2 class="w-2.5 h-2.5 text-emerald-600" />
                                <span class="capitalize">{{ $element->approval_type ?? 'Aprobada' }}</span>
                                @if($element->approved_at)
                                    <span class="font-normal text-emerald-600">({{ $element->approved_at->format('d M') }})</span>
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
                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer flex items-center gap-1.5 shadow-2xs {{ $element->isReadyForProduction() ? 'bg-pink-50 hover:bg-pink-100 text-pink-700 border-pink-300' : ($element->is_approved ? 'bg-white hover:bg-pink-50/50 text-zinc-700 hover:text-pink-700 border-stone-200 hover:border-pink-300 font-semibold' : 'bg-stone-50 hover:bg-stone-100 text-stone-600 border-stone-200') }}"
                        title="{{ $element->isReadyForProduction() ? __('Pieza en Alta / Producción (clic para revertir)') : ($element->is_approved ? __('Clic para marcar esta pieza en Alta') : __('Requiere registrar aprobación antes de poner en alta')) }}">
                        @if($element->isReadyForProduction())
                            <x-lucide-rocket class="w-3 h-3 text-pink-600" />
                            <span>{{ __('En Alta') }}</span>
                        @elseif($element->is_approved)
                            <x-lucide-palette class="w-3 h-3 text-zinc-500" />
                            <span>{{ __('+ Poner en Alta') }}</span>
                        @else
                            <x-lucide-clock class="w-3 h-3 text-stone-400" />
                            <span>{{ __('Pendiente') }}</span>
                        @endif
                    </button>

                    <!-- Network/Server File Path Badge & Popover -->
                    @if($element->file_url)
                        <button 
                            type="button" 
                            @click.stop="copyPath()"
                            class="px-2 py-0.5 rounded text-[10px] font-mono border transition flex items-center gap-1 cursor-pointer select-none"
                            :class="copied ? 'bg-emerald-50 text-emerald-800 border-emerald-300 shadow-2xs' : 'bg-stone-100 hover:bg-stone-200/80 text-zinc-700 border-stone-200 hover:border-stone-300'"
                            title="{{ __('Clic para copiar ruta del servidor: ') . $element->file_url }}"
                        >
                            <template x-if="!copied">
                                <span class="flex items-center gap-1">
                                    <x-lucide-hard-drive class="w-3 h-3 text-pink-600 shrink-0" />
                                    <span class="truncate max-w-[80px] sm:max-w-[120px] text-zinc-600 font-semibold">{{ basename(str_replace('\\', '/', $element->file_url)) ?: 'Ruta' }}</span>
                                    <x-lucide-copy class="w-2.5 h-2.5 text-zinc-400 group-hover:text-zinc-600" />
                                </span>
                            </template>
                            <template x-if="copied">
                                <span class="flex items-center gap-1 text-emerald-700 font-bold">
                                    <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                    <span>{{ __('¡Copiada!') }}</span>
                                </span>
                            </template>
                        </button>
                    @endif

                    <!-- Path Popover Toggle -->
                    <div class="relative">
                        <button 
                            type="button" 
                            @click="openPathModal = !openPathModal" 
                            class="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition {{ $element->file_url ? 'text-pink-600 hover:text-pink-800' : '' }}" 
                            title="{{ $element->file_url ? __('Editar ruta de archivo en servidor') : __('Añadir ruta de archivo en servidor / red Kudos') }}">
                            <x-lucide-folder-input class="w-3.5 h-3.5" />
                        </button>

                        <div 
                            x-show="openPathModal" 
                            x-cloak 
                            @click.away="openPathModal = false" 
                            class="absolute right-0 top-full mt-1.5 z-40 bg-white border border-[#e9e9e7] rounded-xl p-3 shadow-xl w-80 space-y-2.5 text-xs text-left"
                        >
                            <div class="font-bold text-zinc-900 border-b border-[#e9e9e7] pb-1.5 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-lucide-hard-drive class="w-4 h-4 text-pink-600" />
                                    <span>{{ __('Ruta en Servidor / Red Kudos') }}</span>
                                </span>
                                <button type="button" @click="openPathModal = false" class="text-zinc-400 hover:text-zinc-600 cursor-pointer">
                                    <x-lucide-x class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-[10px] text-zinc-600 font-semibold">{{ __('Ruta local o de red:') }}</label>
                                    <template x-if="filePath">
                                        <button 
                                            type="button" 
                                            @click="navigator.clipboard.writeText(filePath); copied = true; setTimeout(() => copied = false, 2000)" 
                                            class="text-[10px] text-pink-600 hover:underline flex items-center gap-0.5 cursor-pointer font-medium">
                                            <x-lucide-copy class="w-2.5 h-2.5" />
                                            <span x-text="copied ? '¡Copiado!' : 'Copiar ruta'"></span>
                                        </button>
                                    </template>
                                </div>
                                <input 
                                    type="text" 
                                    x-model="filePath" 
                                    placeholder="\\servidor\Kudos\Clientes\...\arte.ai"
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2 font-mono text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400"
                                />
                                <span class="text-[10px] text-zinc-400 block">{{ __('Introduce la ruta de red o carpeta compartida del archivo en alta.') }}</span>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] text-zinc-600 font-semibold">{{ __('Notas para el taller de producción:') }}</label>
                                <textarea 
                                    x-model="productionNotes" 
                                    rows="2"
                                    placeholder="{{ __('Especificaciones de sangría, corte, material o colores...') }}"
                                    class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-1.5 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400 resize-none"
                                ></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-[#e9e9e7]">
                                <button 
                                    type="button" 
                                    @click="openPathModal = false"
                                    class="px-2.5 py-1 rounded text-xs font-medium text-zinc-600 hover:bg-stone-100 transition cursor-pointer">
                                    {{ __('Cancelar') }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="$wire.updateElementFileUrl({{ $element->id }}, filePath, productionNotes); openPathModal = false;"
                                    class="px-3 py-1 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs cursor-pointer shadow-2xs transition">
                                    {{ __('Guardar Ruta') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stage Reassign Dropdown -->
                    <select 
                        wire:change="moveElementStage({{ $element->id }}, $event.target.value)" 
                        class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded text-[10px] font-medium text-zinc-600 py-0.5 px-1 focus:outline-none max-w-[90px] truncate"
                        title="{{ __('Mover a otra etapa') }}"
                    >
                        <option value="">{{ __('Sin Etapa') }}</option>
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
                        wire:confirm="{{ __('¿Eliminar esta pieza?') }}" 
                        class="p-1 rounded text-zinc-400 hover:text-rose-600 hover:bg-rose-50 transition opacity-0 group-hover:opacity-100 cursor-pointer" 
                        title="{{ __('Eliminar pieza') }}">
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        @empty
            <div class="text-center py-6 bg-[#fbfbfa] border border-dashed border-[#e9e9e7] rounded-xl text-zinc-400 text-xs space-y-1">
                <x-lucide-layers class="w-5 h-5 mx-auto text-zinc-300" />
                <p>{{ __('No hay piezas registradas en alta para esta orden.') }}</p>
                <p class="text-[10px] text-zinc-400">{{ __('Utiliza la barra superior para agregar los elementos que el diseñador pondrá en alta.') }}</p>
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
                    class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-2xl p-5 sm:p-6 space-y-4 shadow-2xl max-h-[92vh] flex flex-col animate-scaleIn text-left"
                >
                    <!-- Header -->
                    <div class="shrink-0 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900">
                                {{ $pieceApprovalDirectToAlta ? __('Aprobar pieza y poner en Alta') : __('Confirmación de Aprobación de Pieza') }}
                            </h3>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                {{ $pieceApprovalDirectToAlta 
                                    ? __('Registra la validación requerida para autorizar y enviar esta pieza directamente a alta.') 
                                    : __('Valida el visto bueno y registra los datos de aprobación para autorizar esta pieza.') }}
                            </p>
                        </div>
                        <button 
                            type="button" 
                            wire:click="closePieceApprovalModal" 
                            class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-stone-100 transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Scrollable Modal Content -->
                    <div class="overflow-y-auto pr-1 space-y-4 text-xs scrollbar-thin flex-1">
                        <!-- Target Piece Summary Box -->
                        <div class="p-3.5 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl flex items-center justify-between gap-3 shrink-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-200/60 flex items-center justify-center shrink-0 text-emerald-700">
                                    <x-lucide-layers class="w-4.5 h-4.5" />
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-bold block">{{ __('Pieza a autorizar') }}</span>
                                    <span class="text-sm font-bold text-zinc-900 truncate block">{{ $targetElement->name }}</span>
                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-zinc-500 font-mono">
                                        @if($targetElement->dimensions) <span class="bg-white px-1.5 py-0.5 rounded border border-stone-200">{{ $targetElement->dimensions }}</span> @endif
                                        @if($targetElement->material) <span class="bg-white px-1.5 py-0.5 rounded border border-stone-200">{{ $targetElement->material }}</span> @endif
                                        @if($targetElement->quantity > 1) <span class="bg-white px-1.5 py-0.5 rounded border border-stone-200 font-semibold">{{ __('Cant:') }} {{ $targetElement->quantity }}</span> @endif
                                    </div>
                                </div>
                            </div>
                            @if($targetElement->stage)
                                <span class="text-[10px] font-semibold text-pink-700 bg-pink-50 border border-pink-200 px-2.5 py-1 rounded-lg shrink-0 flex items-center gap-1">
                                    <x-lucide-git-branch class="w-3 h-3 text-pink-500" />
                                    <span>{{ $targetElement->stage->name }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- 1. Who approved? (Camila / Manager / Cliente) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-zinc-800">
                                {{ __('¿Quién validó la aprobación?') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <!-- Camila -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'camila')" 
                                    class="relative p-3.5 sm:p-4 rounded-xl border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $pieceApprovalType === 'camila' ? 'border-emerald-500 bg-emerald-50/70 ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ $pieceApprovalType === 'camila' ? 'text-emerald-700 font-medium' : 'text-zinc-500 font-normal' }} block">{{ __('Aprobado por') }}</span>
                                            <span class="text-sm sm:text-base font-bold {{ $pieceApprovalType === 'camila' ? 'text-emerald-950' : 'text-zinc-900' }} block tracking-tight mt-0.5 leading-snug">{{ __('Camila') }}</span>
                                        </div>
                                        @if($pieceApprovalType === 'camila')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white shrink-0 shadow-2xs mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] {{ $pieceApprovalType === 'camila' ? 'text-emerald-700 font-medium' : 'text-zinc-400' }} leading-tight mt-2.5">
                                        {{ __('Validación interna') }}
                                    </div>
                                </button>

                                <!-- Manager -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'manager')" 
                                    class="relative p-3.5 sm:p-4 rounded-xl border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'border-emerald-500 bg-emerald-50/70 ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-emerald-700 font-medium' : 'text-zinc-500 font-normal' }} block">{{ __('Aprobado por') }}</span>
                                            <div class="flex items-baseline gap-1 mt-0.5 flex-wrap">
                                                <span class="text-sm sm:text-base font-bold {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-emerald-950' : 'text-zinc-900' }} tracking-tight leading-snug">{{ $managerName ?? \App\Models\Setting::getManagerName() }}</span>
                                                <span class="text-xs font-normal {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-emerald-700' : 'text-zinc-500' }}">({{ __('Manager') }})</span>
                                            </div>
                                        </div>
                                        @if($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white shrink-0 shadow-2xs mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] {{ ($pieceApprovalType === 'manager' || $pieceApprovalType === 'gabriela') ? 'text-emerald-700 font-medium' : 'text-zinc-400' }} leading-tight mt-2.5">
                                        {{ __('Validación Manager') }}
                                    </div>
                                </button>

                                <!-- Cliente -->
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalType', 'cliente')" 
                                    class="relative p-3.5 sm:p-4 rounded-xl border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $pieceApprovalType === 'cliente' ? 'border-emerald-500 bg-emerald-50/70 ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700' }}">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs {{ $pieceApprovalType === 'cliente' ? 'text-emerald-700 font-medium' : 'text-zinc-500 font-normal' }} block">{{ __('Aprobado por') }}</span>
                                            <span class="text-sm sm:text-base font-bold {{ $pieceApprovalType === 'cliente' ? 'text-emerald-950' : 'text-zinc-900' }} block tracking-tight mt-0.5 leading-snug">{{ __('Cliente') }}</span>
                                        </div>
                                        @if($pieceApprovalType === 'cliente')
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white shrink-0 shadow-2xs mt-0.5">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] {{ $pieceApprovalType === 'cliente' ? 'text-emerald-700 font-medium' : 'text-zinc-400' }} leading-tight mt-2.5">
                                        {{ __('Confirmación directa') }}
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- 2. When / Date (Hoy / Ayer / Otra fecha / Sin fecha) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-zinc-800">
                                {{ __('¿Cuándo se aprobó?') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'today')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'today' ? 'border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700 font-medium' }}">
                                    <x-lucide-calendar class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'today' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                                    <span>{{ __('Hoy') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'yesterday')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'yesterday' ? 'border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700 font-medium' }}">
                                    <x-lucide-history class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'yesterday' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                                    <span>{{ __('Ayer') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'custom')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'custom' ? 'border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700 font-medium' }}">
                                    <x-lucide-calendar-days class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'custom' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                                    <span>{{ __('Fecha concreta') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('pieceApprovalDatePreset', 'none')" 
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs transition cursor-pointer select-none {{ $pieceApprovalDatePreset === 'none' ? 'border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold ring-2 ring-emerald-500/20 shadow-xs' : 'border-[#e9e9e7] bg-[#fbfbfa] hover:bg-stone-50 hover:border-stone-300 text-zinc-700 font-medium' }}">
                                    <x-lucide-minus-circle class="w-3.5 h-3.5 {{ $pieceApprovalDatePreset === 'none' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                                    <span>{{ __('Sin fecha') }}</span>
                                </button>
                            </div>

                            @if($pieceApprovalDatePreset === 'custom')
                                <div class="pt-1.5 animate-fadeIn">
                                    <label class="block text-[11px] text-zinc-600 font-medium mb-1">{{ __('Selecciona la fecha de aprobación:') }}</label>
                                    <input 
                                        type="date" 
                                        wire:model="pieceApprovalCustomDate" 
                                        class="w-full bg-[#fbfbfa] border border-[#e9e9e7] focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl px-3 py-2 text-xs text-zinc-800 font-mono transition outline-none"
                                    />
                                </div>
                            @endif
                        </div>

                        <!-- 3. Support & Additional Notes (Required) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-zinc-800">
                                {{ __('Soporte de aprobación y notas adicionales') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative border rounded-xl p-3 bg-white transition-all {{ $errors->has('pieceApprovalNote') ? 'border-rose-400 ring-2 ring-rose-400/20' : 'border-[#e9e9e7] focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20' }}">
                                <textarea 
                                    wire:model="pieceApprovalNote" 
                                    rows="2"
                                    placeholder="{{ __('Ej: Vía WhatsApp / Correo / Confirmado con Camila...') }}"
                                    class="w-full text-xs text-zinc-800 placeholder-zinc-400 bg-transparent resize-none border-0 p-0 focus:outline-none focus:ring-0 leading-relaxed outline-none"></textarea>
                            </div>
                            @error('pieceApprovalNote')
                                <p class="text-xs text-rose-600 font-medium flex items-center gap-1 mt-1">
                                    <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-stone-100 shrink-0">
                        <button 
                            type="button" 
                            wire:click="closePieceApprovalModal" 
                            class="px-3.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="button" 
                            wire:click="submitPieceApproval" 
                            wire:loading.attr="disabled"
                            class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-loader-2 wire:loading wire:target="submitPieceApproval" class="w-3.5 h-3.5 animate-spin" />
                            @if($pieceApprovalDirectToAlta)
                                <x-lucide-rocket wire:loading.remove wire:target="submitPieceApproval" class="w-3.5 h-3.5" />
                                <span wire:loading.remove wire:target="submitPieceApproval">{{ __('Aprobar y Poner en Alta') }}</span>
                            @else
                                <x-lucide-check wire:loading.remove wire:target="submitPieceApproval" class="w-3.5 h-3.5 stroke-[3]" />
                                <span wire:loading.remove wire:target="submitPieceApproval">{{ __('Confirmar Aprobación') }}</span>
                            @endif
                            <span wire:loading wire:target="submitPieceApproval">{{ __('Procesando...') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
