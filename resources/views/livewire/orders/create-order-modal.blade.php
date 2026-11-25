<div>
    <div 
        id="create-order-modal-container"
        x-data="{
            isOpen: @js((bool) $showModal),
            initialForm: null,
            init() {
                if (this.isOpen) {
                    this.snapshot();
                    window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                    window.KudosModalStack?.register('create-order-modal', this.$el, () => this.confirmClose(() => $wire.closeModal()));
                }
                this.$watch('$wire.showModal', (show) => {
                    this.isOpen = Boolean(show);
                    if (!show) {
                        window.KudosDirtyGuard.unregister('create-order-modal');
                        window.KudosModalStack?.unregister('create-order-modal');
                    } else {
                        this.snapshot();
                        window.KudosDirtyGuard.register('create-order-modal', () => this.isDirty(), this.$el);
                        window.KudosModalStack?.register('create-order-modal', this.$el, () => this.confirmClose(() => $wire.closeModal()));
                        window.KudosModalStack?.bringToFront('create-order-modal');
                    }
                });
                this.$cleanup(() => {
                    window.KudosDirtyGuard.unregister('create-order-modal');
                    window.KudosModalStack?.unregister('create-order-modal');
                });
            },
            openImmediate() {
                this.isOpen = true;
                window.KudosModalStack?.bringToFront('create-order-modal');
            },
            snapshot() {
                this.initialForm = JSON.stringify({
                    company: ($wire.companyName || '').toString().trim(),
                    task: ($wire.taskName || '').toString().trim(),
                    wo: ($wire.woNumber || '').toString().trim(),
                    trelloId: ($wire.trelloCardId || '').toString().trim(),
                    resp: ($wire.responsiblePerson || '').toString().trim(),
                    location: ($wire.locationName || '').toString().trim(),
                    substatus: ($wire.substatus || '').toString().trim(),
                    flags: Array.from($wire.flags || []).map(String).sort(),
                    due: ($wire.dueDate || '').toString().trim(),
                    createOnTrello: Boolean($wire.createOnTrello),
                    designers: Array.from($wire.designerIds || []).map(String).sort(),
                    pieces: Array.from($wire.pieces || []).map(p => ({
                        name: (p.name || '').trim(),
                        dimensions: (p.dimensions || '').trim(),
                        material: (p.material || '').trim(),
                        quantity: Number(p.quantity) || 1
                    })),
                    newPieceName: ($wire.newPieceName || '').toString().trim()
                });
            },
            isDirty() {
                if (!$wire.showModal || !this.initialForm) return false;
                const current = JSON.stringify({
                    company: ($wire.companyName || '').toString().trim(),
                    task: ($wire.taskName || '').toString().trim(),
                    wo: ($wire.woNumber || '').toString().trim(),
                    trelloId: ($wire.trelloCardId || '').toString().trim(),
                    resp: ($wire.responsiblePerson || '').toString().trim(),
                    location: ($wire.locationName || '').toString().trim(),
                    substatus: ($wire.substatus || '').toString().trim(),
                    flags: Array.from($wire.flags || []).map(String).sort(),
                    due: ($wire.dueDate || '').toString().trim(),
                    createOnTrello: Boolean($wire.createOnTrello),
                    designers: Array.from($wire.designerIds || []).map(String).sort(),
                    pieces: Array.from($wire.pieces || []).map(p => ({
                        name: (p.name || '').trim(),
                        dimensions: (p.dimensions || '').trim(),
                        material: (p.material || '').trim(),
                        quantity: Number(p.quantity) || 1
                    })),
                    newPieceName: ($wire.newPieceName || '').toString().trim()
                });
                return current !== this.initialForm;
            },
            confirmClose(action) {
                if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                    return;
                }
                if (this.isDirty()) {
                    window.KudosDirtyGuard.openConfirmModal({
                        title: @js(__('Save new order?')),
                        description: @js(__('You\'ve entered data for a new order.')),
                        cancelText: @js(__('Cancel')),
                        discardText: @js(__('Don\'t save')),
                        saveText: @js(__('Save')),
                        onCancel: () => {},
                        onDiscard: () => {
                            this.isOpen = false;
                            window.KudosDirtyGuard.unregister('create-order-modal');
                            window.KudosModalStack?.unregister('create-order-modal');
                            action();
                        },
                        onSave: () => {
                            this.isOpen = false;
                            window.KudosDirtyGuard.unregister('create-order-modal');
                            window.KudosModalStack?.unregister('create-order-modal');
                            $wire.save();
                        }
                    });
                } else {
                    this.isOpen = false;
                    window.KudosDirtyGuard.unregister('create-order-modal');
                    window.KudosModalStack?.unregister('create-order-modal');
                    action();
                }
            }
        }"
        x-show="isOpen"
        x-cloak
        @open-create-order.window="openImmediate()"
        @open-duplicate-order.window="openImmediate()"
        @click.self="confirmClose(() => $wire.closeModal())" 
        class="fixed inset-0 overflow-y-auto bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4"
        style="z-index: 300;"
        @pointerdown="window.KudosModalStack?.bringToFront('create-order-modal')"
        @keydown.window.escape="if (window.KudosModalStack ? window.KudosModalStack.isTop('create-order-modal') : true) confirmClose(() => $wire.closeModal())"
    >
        <div class="bg-surface border border-line rounded-panel shadow-panel max-w-2xl w-full flex flex-col transition duration-200 overflow-hidden">
            @if($showModal)

                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-line flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if($isDuplicating)
                            <h3 class="text-section font-semibold text-ink">{{ __('Duplicate Order') }}</h3>
                            <p class="text-meta text-ink-3">{{ __('Edit the details to create the copy of this order.') }}</p>
                        @else
                            <h3 class="text-section font-semibold text-ink">{{ __('Create order') }}</h3>
                            <p class="text-meta text-ink-3">{{ __('Add a new order straight into the active workflow.') }}</p>
                        @endif
                    </div>
                    <button type="button" @click="confirmClose(() => $wire.closeModal())" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" aria-label="{{ __('Close') }}">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form -->
                <form wire:submit.prevent="save" class="flex flex-col">
                    <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                        <!-- Row 1: WO number & Responsible person -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="ui-label" for="co-wo">{{ __('WO number (optional)') }}</label>
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-1 min-w-0">
                                        <span class="inline-flex h-8 items-center px-2.5 rounded-l-control border border-r-0 border-line bg-sunken text-meta font-medium text-ink-3 select-none">WO</span>
                                        <input id="co-wo" type="text" wire:model="woNumber" placeholder="16350" class="ui-input rounded-l-none font-mono min-w-0">
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="generateWoNumber"
                                        wire:loading.attr="disabled"
                                        class="ui-btn ui-btn-secondary ui-btn-sm shrink-0 disabled:opacity-50"
                                        title="{{ __('Create next WO number automatically') }}">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" wire:loading wire:target="generateWoNumber" />
                                        <span>{{ __('Next WO') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Responsible Person (Searchable Dropdown Menu) -->
                            <div class="relative"
                                 x-data="{
                                     open: false,
                                     selectResp(id, name) {
                                         $wire.set('contactId', id);
                                         $wire.set('responsiblePerson', name);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label" for="co-resp">{{ __('Contact / client') }}</label>
                                <div class="relative">
                                    <input
                                        id="co-resp"
                                        type="text"
                                        wire:model.live="responsiblePerson"
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. Marcela, or search clients…') }}"
                                        class="ui-input pr-8 placeholder:normal-case">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer"
                                        title="{{ __('Show client list') }}">
                                        <x-lucide-chevron-down class="w-4 h-4" />
                                    </button>
                                </div>

                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-card shadow-menu max-h-64 overflow-y-auto text-body custom-vertical-scrollbar"
                                    style="display: none;">

                                    {{-- Contactos vinculados a esta marca/compañía --}}
                                    @if(!empty($clientContacts) && $clientContacts->isNotEmpty())
                                        <div class="px-3 py-1.5 bg-canvas border-b border-line text-meta font-medium text-ink-3">
                                            {{ __('Contacts at this company') }}
                                        </div>
                                        @foreach($clientContacts as $c)
                                            <button
                                                type="button"
                                                x-show="!$wire.responsiblePerson || '{{ strtolower(addslashes($c->name)) }}'.includes(($wire.responsiblePerson || '').toLowerCase())"
                                                @click="selectResp({{ $c->id }}, '{{ addslashes($c->name) }}')"
                                                class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-medium text-ink truncate block">
                                                {{ $c->name }}
                                            </button>
                                        @endforeach
                                    @endif

                                    {{-- Otros clientes del directorio global --}}
                                    @php
                                        $linkedIds = !empty($clientContacts) ? $clientContacts->pluck('id')->toArray() : [];
                                        $otherContacts = $directoryContacts->filter(fn($dc) => !in_array($dc->id, $linkedIds, true));
                                    @endphp
                                    @if($otherContacts->isNotEmpty())
                                        <div class="px-3 py-1.5 bg-canvas border-b border-line text-meta font-medium text-ink-3">
                                            {{ __('Client directory') }}
                                        </div>
                                        @foreach($otherContacts as $dc)
                                            <button
                                                type="button"
                                                x-show="!$wire.responsiblePerson || '{{ strtolower(addslashes($dc->name)) }}'.includes(($wire.responsiblePerson || '').toLowerCase())"
                                                @click="selectResp({{ $dc->id }}, '{{ addslashes($dc->name) }}')"
                                                class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink truncate block">
                                                {{ $dc->name }}
                                            </button>
                                        @endforeach
                                    @endif

                                    {{-- Crear contacto sobre la marcha si no existe --}}
                                    <div x-show="Boolean($wire.responsiblePerson && $wire.responsiblePerson.trim().length >= 2)">
                                        <button
                                            type="button"
                                            @click="$wire.createContactForCompany($wire.responsiblePerson); open = false;"
                                            class="w-full text-left px-3 py-2 border-t border-line hover:bg-sunken text-ink font-medium flex items-center gap-2 cursor-pointer"
                                        >
                                            <x-lucide-user-plus class="w-4 h-4 text-ink-3 shrink-0" />
                                            <span class="truncate">{{ __('+ Create contact for this company:') }} <strong class="font-semibold" x-text="$wire.responsiblePerson"></strong></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Company name & Location -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="relative"
                                 x-data="{
                                     open: false,
                                     selectComp(comp) {
                                         $wire.set('companyName', comp);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label" for="co-company">{{ __('Company name') }} <span class="text-danger">*</span></label>
                                <div class="relative">
                                    <input
                                        id="co-company"
                                        type="text"
                                        wire:model.live="companyName"
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. El Taco Loco restaurant…') }}"
                                        class="ui-input pr-8 placeholder:normal-case"
                                        x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer"
                                        aria-label="{{ __('Show companies') }}">
                                        <x-lucide-chevron-down class="w-4 h-4" />
                                    </button>
                                </div>

                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-card shadow-menu max-h-60 overflow-y-auto text-body"
                                    style="display: none;">
                                    @forelse($existingCompanies as $comp)
                                        <button
                                            type="button"
                                            x-show="!$wire.companyName || '{{ strtolower(addslashes($comp)) }}'.includes(($wire.companyName || '').toLowerCase())"
                                            @click="selectComp('{{ addslashes($comp) }}')"
                                            class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink">
                                            {{ $comp }}
                                        </button>
                                    @empty
                                        <div class="px-3 py-2 text-meta text-ink-3">{{ __('Enter a new company name...') }}</div>
                                    @endforelse
                                </div>
                                @error('companyName') <span class="text-meta text-danger mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="relative"
                                 x-data="{
                                     open: false,
                                     selectLoc(loc) {
                                         $wire.set('locationName', loc);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label" for="co-location">{{ __('Location') }} <span class="font-normal text-ink-3">({{ __('Optional') }})</span></label>
                                <div class="relative">
                                    <input
                                        id="co-location"
                                        type="text"
                                        wire:model.live="locationName"
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. Talpa 8, Downtown branch…') }}"
                                        class="ui-input pr-8 placeholder:normal-case">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer"
                                        title="{{ __('View available locations') }}">
                                        <x-lucide-chevron-down class="w-4 h-4" />
                                    </button>
                                </div>

                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-card shadow-menu max-h-48 overflow-y-auto text-body"
                                    style="display: none;">

                                    @if(!empty($clientLocations))
                                        <div class="px-3 py-1.5 bg-canvas border-b border-line text-meta font-medium text-ink-3">
                                            {{ __('Client locations') }}
                                        </div>
                                        @foreach($clientLocations as $cLoc)
                                            <button
                                                type="button"
                                                x-show="!$wire.locationName || '{{ strtolower(addslashes($cLoc)) }}'.includes(($wire.locationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($cLoc) }}')"
                                                class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-medium text-ink flex items-center justify-between">
                                                <span>{{ $cLoc }}</span>
                                                <span class="text-meta text-ink-3 font-normal">{{ __('Registered') }}</span>
                                            </button>
                                        @endforeach
                                    @endif

                                    @php
                                        $otherLocations = array_diff($existingLocations->toArray(), $clientLocations ?? []);
                                    @endphp

                                    @if(!empty($otherLocations))
                                        @if(!empty($clientLocations))
                                            <div class="px-3 py-1.5 bg-canvas border-b border-line text-meta font-medium text-ink-3">
                                                {{ __('Other locations') }}
                                            </div>
                                        @endif
                                        @foreach($otherLocations as $loc)
                                            <button
                                                type="button"
                                                x-show="!$wire.locationName || '{{ strtolower(addslashes($loc)) }}'.includes(($wire.locationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($loc) }}')"
                                                class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink">
                                                {{ $loc }}
                                            </button>
                                        @endforeach
                                    @endif

                                    @if(empty($clientLocations) && empty($otherLocations))
                                        <div class="px-3 py-2 text-meta text-ink-3">{{ __('Enter a new location...') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Task description -->
                        <div>
                            <label class="ui-label" for="co-task">{{ __('Task / job description') }} <span class="text-danger">*</span></label>
                            <input id="co-task" type="text" wire:model="taskName" placeholder="{{ __('e.g. Acrylic outdoor menu & lettering') }}" class="ui-input placeholder:normal-case" x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                            @error('taskName') <span class="text-meta text-danger mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Row 4: Pieces -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-meta font-medium text-ink-2">{{ __('Order pieces') }} <span class="font-normal text-ink-3">({{ __('Optional') }}{{ count($pieces) > 0 ? ': ' . count($pieces) : '' }})</span></span>
                                <button
                                    type="button"
                                    wire:click="$toggle('showPieceSpecs')"
                                    class="text-meta text-ink-3 hover:text-ink cursor-pointer font-medium transition">
                                    {{ $showPieceSpecs ? __('Hide details') : __('Size / material / quantity') }}
                                </button>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        x-ref="pieceInput"
                                        wire:model="newPieceName"
                                        wire:keydown.enter.prevent="$wire.addPiece().then(() => $refs.pieceInput.focus())"
                                        placeholder="{{ __('Piece name (e.g. Front banner 3x2m, 3D sign)…') }}"
                                        class="ui-input flex-1 placeholder:normal-case">
                                    <button type="button" wire:click="addPiece" class="ui-btn ui-btn-secondary shrink-0">
                                        <x-lucide-plus class="w-4 h-4" />
                                        <span>{{ __('Add Item') }}</span>
                                    </button>
                                </div>
                                @error('newPieceName') <span class="text-meta text-danger block">{{ $message }}</span> @enderror

                                @if($showPieceSpecs)
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <div>
                                            <label class="ui-label">{{ __('Measurements') }}</label>
                                            <input type="text" wire:model="newPieceDimensions" wire:keydown.enter.prevent="$wire.addPiece().then(() => $refs.pieceInput.focus())" placeholder="{{ __('e.g. 3.5m x 2.2m') }}" class="ui-input placeholder:normal-case">
                                        </div>
                                        <div>
                                            <label class="ui-label">{{ __('Material / substrate') }}</label>
                                            <input type="text" wire:model="newPieceMaterial" wire:keydown.enter.prevent="$wire.addPiece().then(() => $refs.pieceInput.focus())" placeholder="{{ __('e.g. 3mm acrylic, vinyl') }}" class="ui-input placeholder:normal-case">
                                        </div>
                                        <div>
                                            <label class="ui-label">{{ __('Quantity') }}</label>
                                            <input type="number" min="1" wire:model="newPieceQuantity" wire:keydown.enter.prevent="$wire.addPiece().then(() => $refs.pieceInput.focus())" class="ui-input">
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if(!empty($pieces))
                                <div class="mt-2 max-h-48 overflow-y-auto rounded-card border border-line divide-y divide-line">
                                    @foreach($pieces as $idx => $piece)
                                        <div class="flex items-center justify-between gap-2 px-3 py-2 text-body hover:bg-sunken">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-ink truncate">{{ $piece['name'] }}</span>
                                                    @if(!empty($piece['quantity']) && $piece['quantity'] > 1)
                                                        <span class="text-meta text-ink-3 shrink-0">x{{ $piece['quantity'] }}</span>
                                                    @endif
                                                </div>
                                                @if(!empty($piece['dimensions']) || !empty($piece['material']))
                                                    <div class="text-meta text-ink-3 truncate">
                                                        {{ collect([$piece['dimensions'] ?? null, $piece['material'] ?? null])->filter()->implode(' · ') }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <button type="button" wire:click="editPiece({{ $idx }})" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" title="{{ __('Edit piece') }}" aria-label="{{ __('Edit piece') }}">
                                                    <x-lucide-pencil class="w-4 h-4" />
                                                </button>
                                                <button type="button" wire:click="removePiece({{ $idx }})" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" title="{{ __('Delete item') }}" aria-label="{{ __('Delete item') }}">
                                                    <x-lucide-trash-2 class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Row 5: Substatus & Designers -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="ui-label">{{ __('Condition / substatus') }} <span class="font-normal text-ink-3">({{ __('Optional') }})</span></span>
                                    @if($substatus || !empty($flags))
                                        <button type="button" wire:click="selectSubstatus('')" class="mb-1.5 text-meta text-ink-3 hover:text-ink cursor-pointer font-medium transition" title="{{ __('Clear substatuses and flags') }}">
                                            {{ __('Clear') }}
                                        </button>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @forelse($substatuses as $sub)
                                        @php
                                            $subVal = $sub instanceof \App\Models\Substatus ? $sub->name : ($sub->value ?? (string) $sub);
                                            $isGlobal = $sub instanceof \App\Models\Substatus ? (bool) $sub->is_global : ($sub instanceof \App\Enums\Substatus ? $sub->isGlobal() : false);
                                            $isSelected = $isGlobal ? in_array($subVal, $flags ?? [], true) : ($substatus === $subVal);
                                            $isUrgentSub = strcasecmp($subVal, 'URGENTE') === 0 || strcasecmp($subVal, 'Urgent') === 0;
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="selectSubstatus('{{ addslashes($subVal) }}')"
                                            class="ui-chip"
                                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                            title="{{ $subVal }}"
                                        >
                                            @if($isUrgentSub)
                                                <span class="w-1.5 h-1.5 rounded-full bg-danger shrink-0"></span>
                                            @endif
                                            <span>{{ $subVal }}</span>
                                        </button>
                                    @empty
                                        <span class="text-meta text-ink-3">{{ __('No substatuses available for this stage') }}</span>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <span class="ui-label">{{ __('Assigned designers') }}</span>

                                @if($mostAvailableDesigner)
                                    <p class="mb-1.5 text-meta text-ink-3">
                                        {{ __('Most available this week:') }}
                                        <button
                                            type="button"
                                            wire:click="toggleDesigner({{ $mostAvailableDesigner->id }})"
                                            class="font-medium text-ink-2 hover:text-ink hover:underline cursor-pointer"
                                            title="{{ __('Click to select :name', ['name' => $mostAvailableDesigner->name]) }}"
                                        >{{ $mostAvailableDesigner->name }}</button>
                                    </p>
                                @endif

                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach($designers as $designer)
                                        @php $isAssigned = in_array((int)$designer->id, array_map('intval', $designerIds)); @endphp
                                        <button
                                            type="button"
                                            wire:click="toggleDesigner({{ $designer->id }})"
                                            class="ui-chip"
                                            aria-pressed="{{ $isAssigned ? 'true' : 'false' }}"
                                        >
                                            <span class="w-2 h-2 rounded-full {{ $designer->dot_color_class }}" style="{{ $designer->dot_inline_style }}"></span>
                                            <span>{{ $designer->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                <p class="text-meta text-ink-3 mt-1.5">{{ __('Selecting External designer also adds Euralíz automatically.') }}</p>
                            </div>
                        </div>

                        <!-- Row 6: Due date -->
                        <div>
                            @php
                                $todayDate = now()->toDateString();
                                $tomorrowDate = now()->addDay()->toDateString();
                                $isToday = ($dueDate === $todayDate);
                                $isTomorrow = ($dueDate === $tomorrowDate);
                            @endphp
                            <div class="flex items-center justify-between">
                                <label class="ui-label" for="co-due">{{ __('Due date (SLA)') }}</label>
                                @if($dueDate)
                                    <div class="mb-1.5 flex items-center gap-2 text-meta text-ink-3">
                                        <span>{{ __('Due:') }} <span class="font-medium text-ink-2">{{ \Carbon\Carbon::parse($dueDate)->locale(app()->getLocale())->isoFormat('dddd, D MMMM') }}</span></span>
                                        <button type="button" wire:click="$set('dueDate', '')" class="hover:text-ink cursor-pointer font-medium transition" title="{{ __('Clear date') }}">{{ __('Remove') }}</button>
                                    </div>
                                @else
                                    <span class="mb-1.5 text-meta text-ink-3">{{ __('No date set') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="setDueDatePreset('today')" class="ui-chip" aria-pressed="{{ $isToday ? 'true' : 'false' }}">{{ __('Today') }}</button>
                                <button type="button" wire:click="setDueDatePreset('tomorrow')" class="ui-chip" aria-pressed="{{ $isTomorrow ? 'true' : 'false' }}">{{ __('Tomorrow') }}</button>
                                <input id="co-due" type="date" wire:model.live="dueDate" class="ui-input w-44 cursor-pointer">
                            </div>
                            @error('dueDate') <span class="text-meta text-danger mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Row 7: Trello card (optional) -->
                        <div class="relative"
                             x-data="{
                                 open: false,
                                 selectCard(id) {
                                     $wire.set('trelloCardId', id);
                                     this.open = false;
                                 }
                             }"
                             x-dropdown-nav>
                            <label class="ui-label" for="co-trello">{{ __('Trello card ID') }}</label>
                            <div class="relative">
                                <input
                                    id="co-trello"
                                    type="text"
                                    wire:model.live="trelloCardId"
                                    @focus="open = true"
                                    @click.outside="open = false"
                                    autocomplete="off"
                                    placeholder="{{ __('e.g. AbCdEf12, or search…') }}"
                                    class="ui-input pr-8 font-mono placeholder:font-sans">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer"
                                    title="{{ __('Show Trello cards') }}">
                                    <x-lucide-chevron-down class="w-4 h-4" />
                                </button>
                            </div>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 bottom-full mb-1 z-50 bg-surface border border-line rounded-card shadow-menu max-h-64 overflow-y-auto text-body"
                                style="display: none;">
                                <div class="px-3 py-1.5 bg-canvas border-b border-line text-meta font-medium text-ink-3 sticky top-0 z-10">
                                    {{ __('Available cards') }} ({{ count($availableTrelloCards) }})
                                </div>
                                @forelse($availableTrelloCards as $tc)
                                    <button
                                        type="button"
                                        x-show="!$wire.trelloCardId || '{{ strtolower(addslashes($tc->trello_card_id . ' ' . $tc->wo_number . ' ' . $tc->company_name . ' ' . $tc->task_name . ' ' . $tc->trello_title)) }}'.includes(($wire.trelloCardId || '').toLowerCase())"
                                        @click="selectCard('{{ $tc->trello_card_id }}')"
                                        class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer flex items-center justify-between gap-2 border-b border-line last:border-0">
                                        <div class="min-w-0">
                                            <span class="font-medium text-ink block truncate">
                                                {{ $tc->trello_title ?: ($tc->company_name ?: __('Trello Card')) }}
                                            </span>
                                            @if($tc->task_name && $tc->task_name !== $tc->trello_title)
                                                <span class="text-meta text-ink-3 block truncate">{{ $tc->task_name }}</span>
                                            @endif
                                        </div>
                                        <span class="font-mono text-meta text-ink-3 shrink-0">{{ substr($tc->trello_card_id, 0, 8) }}...</span>
                                    </button>
                                @empty
                                    <div class="px-3 py-3 text-center text-meta text-ink-3">{{ __('No cards available.') }}</div>
                                @endforelse
                            </div>

                            <label for="createOnTrelloCheckbox" class="mt-2 flex items-center gap-2 text-body text-ink-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    id="createOnTrelloCheckbox"
                                    wire:model="createOnTrello"
                                    x-bind:disabled="!!$wire.trelloCardId"
                                    class="w-4 h-4 rounded border-line-strong text-ink focus:ring-ink/20 cursor-pointer disabled:opacity-50">
                                <span>{{ __('Create a new Trello card on save') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3 border-t border-line flex items-center justify-end gap-2">
                        <button type="button" @click="confirmClose(() => $wire.closeModal())" class="ui-btn ui-btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" :disabled="!isDirty()" class="ui-btn ui-btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $isDuplicating ? __('Create copy') : __('Create order') }}
                        </button>
                    </div>
                </form>
            @else
                @include('livewire.orders.partials.create-order-skeleton')
            @endif
        </div>
    </div>

    <!-- ON HOLD REASON MODAL -->
    @if($showOnHoldModal)
        <div
            class="fixed inset-0 z-[310] bg-stone-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.closeOnHoldModal()"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmOnHold()"
            wire:keydown.escape="closeOnHoldModal">
            <div class="bg-surface border border-line rounded-panel shadow-panel max-w-md w-full">
                <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        <h3 class="text-section font-semibold text-ink">{{ __('Reason to Put on On Hold') }}</h3>
                        <p class="text-meta text-ink-3 truncate">{{ $companyName ?: __('New Order') }} &mdash; {{ $taskName ?: '' }}</p>
                    </div>
                    <button wire:click="closeOnHoldModal" type="button" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" aria-label="{{ __('Close') }}">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="px-5 py-4">
                    <label class="ui-label" for="co-onhold">{{ __('Reason / Comment:') }}</label>
                    <textarea id="co-onhold" wire:model="onHoldReason" rows="3" placeholder="{{ __('e.g. Waiting for quote confirmation from client...') }}" class="ui-input"></textarea>
                    @error('onHoldReason')
                        <span class="text-meta text-danger block mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-3">
                    <button wire:click="closeOnHoldModal" type="button" class="ui-btn ui-btn-secondary">{{ __('Cancel') }}</button>
                    <button wire:click="confirmOnHold" wire:loading.attr="disabled" type="button" class="ui-btn ui-btn-primary">{{ __('Put on On Hold') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
