<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-5xl">
    <x-ui.page-header :title="__('Statuses and substatuses')">
        <x-slot:actions>
            <div class="relative w-56">
                <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    wire:model.live.debounce.250ms="search"
                    type="text"
                    placeholder="{{ __('Search substatus...') }}"
                    class="ui-input pl-9 pr-3" />
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">{{ __('New Substatus') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-danger-soft text-danger border border-line px-3.5 py-2.5 rounded-card text-body shrink-0">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Section 1: substatuses by core status -->
    <section class="space-y-3">
        <div class="flex items-end justify-between border-b border-line pb-2">
            <div>
                <h2 class="text-section font-semibold text-ink">{{ __('Substatuses by core status (process)') }}</h2>
                <p class="text-body text-ink-3">{{ __('Substatuses specific to each operational stage.') }}</p>
            </div>
            <span class="text-meta text-ink-3">{{ count($coreStatuses) }} {{ __('Core statuses') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-3">
            @foreach($coreStatuses as $coreCase)
                @php
                    $assignedSubstatuses = $substatusesByCoreStatus[$coreCase->value] ?? collect();
                @endphp
                <div class="bg-surface border border-line rounded-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0 flex-wrap">
                            @if(\App\Enums\CoreStatus::isPendingDesign($coreCase))
                                <span class="text-row font-semibold text-ink">{{ __('Design queues (received orders)') }}</span>
                                <span class="text-meta text-ink-3 hidden sm:inline">({{ __('Applies to all designers automatically') }})</span>
                            @else
                                <span class="text-row font-semibold text-ink">{{ $coreCase->label() }}</span>
                                <span class="text-meta text-ink-3 font-mono hidden sm:inline">({{ $coreCase->value }})</span>
                            @endif
                            <span class="text-meta text-ink-3">{{ $assignedSubstatuses->count() }} {{ __('substatuses') }}</span>
                        </div>

                        <flux:button wire:click="openCreateModalForCoreStatus('{{ $coreCase->value }}')" variant="outline" size="sm" icon="plus">{{ __('Add substatus') }}</flux:button>
                    </div>

                    @if($assignedSubstatuses->isEmpty())
                        <div class="px-4 py-3 text-body text-ink-3">{{ __('No specific substatuses assigned to this core status.') }}</div>
                    @else
                        <div class="divide-y divide-line"
                             data-reorder-group="core-{{ $coreCase->value }}"
                             x-data="{ draggingId: null, dragOverId: null }">
                            @foreach($assignedSubstatuses as $sub)
                                @include('livewire.settings.partials.substatus-row', ['sub' => $sub, 'isGlobalRow' => false])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <!-- Section 2: global substatuses -->
    <section class="space-y-3">
        <div class="flex items-end justify-between border-b border-line pb-2 gap-4">
            <div>
                <h2 class="text-section font-semibold text-ink">{{ __('Cross-stage substatuses and global alerts') }}</h2>
                <p class="text-body text-ink-3">{{ __('Substatuses that apply at any stage (e.g. Ticket, Potential customer, Urgent) and automatic SLA time alerts.') }}</p>
            </div>
            <span class="text-meta text-ink-3 shrink-0">{{ $globalSubstatuses->count() }} {{ __('Global substatuses') }}</span>
        </div>

        <div class="bg-surface border border-line rounded-card overflow-hidden">
            @if($globalSubstatuses->isEmpty())
                <div class="p-6 text-center text-ink-3 text-body">{{ __('No global substatuses yet.') }}</div>
            @else
                <div class="divide-y divide-line"
                     data-reorder-group="global"
                     x-data="{ draggingId: null, dragOverId: null }">
                    @foreach($globalSubstatuses as $sub)
                        @include('livewire.settings.partials.substatus-row', ['sub' => $sub, 'isGlobalRow' => true])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Create / Edit Substatus Modal (saving never asks for confirmation) -->
    @if($showModal)
        <div class="fixed inset-0 z-[100] bg-ink/40 flex items-center justify-center p-4">
            <div
                x-data="{
                    initialName: null,
                    initialColor: null,
                    initialStyleType: null,
                    init() {
                        this.initialName = $wire.name || '';
                        this.initialColor = $wire.main_color || '';
                        this.initialStyleType = $wire.style_type || '';
                    },
                    isDirty() {
                        if (!$wire.showModal) return false;
                        return ($wire.name || '') !== this.initialName ||
                               ($wire.main_color || '') !== this.initialColor ||
                               ($wire.style_type || '') !== this.initialStyleType;
                    }
                }"
                @keydown.window.escape="$wire.closeModal()"
                class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-section font-semibold text-ink">{{ $editingId ? __('Edit Substatus') : __('New Substatus') }}</h3>
                    <button type="button" @click="$wire.closeModal()" class="text-ink-3 hover:text-ink cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Substatus Name') }}</label>
                        <input
                            wire:model="name"
                            type="text"
                            placeholder="{{ __('Ex: IN CLIENT REVIEW') }}"
                            class="ui-input" />
                        @error('name') <span class="text-meta text-danger mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-3 bg-sunken rounded-control">
                        <div>
                            <label class="text-meta text-ink-2 mb-1 flex items-center justify-between">
                                <span>{{ __('Linked core status') }}</span>
                                @if($is_global)
                                    <span class="text-meta text-ink-3">{{ __('Cross-stage mode on') }}</span>
                                @endif
                            </label>
                            <select
                                wire:model.live="core_status"
                                :disabled="$wire.is_global"
                                class="ui-select">
                                <option value="">{{ __('Select a core status') }}</option>
                                @foreach($dropdownStatuses as $statusCase)
                                    <option value="{{ $statusCase->value }}">
                                        @if(\App\Enums\CoreStatus::isPendingDesign($statusCase))
                                            {{ __('Design queues (designers: Euralíz, Adrián, César)') }}
                                        @else
                                            {{ $statusCase->label() }} ({{ $statusCase->value }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-meta text-ink-3 mt-0.5 block">{{ __('Choose the core status this substatus belongs to.') }}</span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-line">
                            <label class="flex items-center gap-2 text-body text-ink-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="is_global" class="rounded border-line-strong text-ink focus:ring-gold">
                                <span>{{ __('Cross-stage / global substatus') }}</span>
                            </label>

                            @if(!$is_global && !empty($core_status))
                                <label class="flex items-center gap-2 text-body text-ink-2 cursor-pointer">
                                    <input type="checkbox" wire:model="is_default" class="rounded border-line-strong text-ink focus:ring-gold">
                                    <span>{{ __('Default') }}</span>
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-meta text-ink-2">{{ __('Background Style') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="setStyleType('light')"
                                class="p-2 rounded-control border text-body font-medium transition cursor-pointer {{ $style_type === 'light' ? 'bg-ink text-white border-ink' : 'bg-surface border-line text-ink-2 hover:bg-sunken' }}">
                                {{ __('Light Background') }}
                            </button>
                            <button type="button" wire:click="setStyleType('solid')"
                                class="p-2 rounded-control border text-body font-medium transition cursor-pointer {{ $style_type === 'solid' ? 'bg-ink text-white border-ink' : 'bg-surface border-line text-ink-2 hover:bg-sunken' }}">
                                {{ __('Solid Color') }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-meta text-ink-2">{{ __('Substatus Main Color') }}</label>

                        <div class="flex items-center gap-3">
                            <input wire:model.live="main_color" type="color" class="w-10 h-10 rounded-control border border-line-strong p-0.5 cursor-pointer shrink-0" />
                            <input wire:model.live="main_color" type="text" class="w-32 text-body font-mono px-3 py-1.5 border border-line-strong rounded-control" />
                        </div>

                        <div class="pt-1">
                            <span class="text-meta text-ink-3 block mb-1.5">{{ __('Project Palette:') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $presets = [
                                        '#EF4444' => __('Red'),
                                        '#F43F5E' => __('Pink'),
                                        '#F97316' => __('Orange'),
                                        '#F59E0B' => __('Amber'),
                                        '#10B981' => __('Green'),
                                        '#14B8A6' => __('Teal'),
                                        '#0EA5E9' => __('Sky'),
                                        '#3B82F6' => __('Blue'),
                                        '#6366F1' => __('Indigo'),
                                        '#A855F7' => __('Purple'),
                                        '#EC4899' => __('Fuchsia'),
                                        '#78716C' => __('Stone'),
                                    ];
                                @endphp
                                @foreach($presets as $hex => $label)
                                    <button
                                        type="button"
                                        wire:click="selectPresetColor('{{ $hex }}')"
                                        class="w-6 h-6 rounded-full border border-black/10 cursor-pointer {{ strtoupper($main_color) === $hex ? 'ring-2 ring-offset-2 ring-ink' : '' }}"
                                        style="background-color: {{ $hex }};"
                                        title="{{ $label }}">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-sunken rounded-control space-y-1.5">
                        <span class="text-meta text-ink-3 block">{{ __('Badge preview') }}</span>
                        <span
                            class="px-3 py-1 rounded-control text-body font-medium border inline-block"
                            style="background-color: {{ $bg_color }}; color: {{ $text_color }}; border-color: {{ $border_color }};">
                            {{ $name ?: __('EXAMPLE SUBSTATUS') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                        <flux:button type="button" @click="$wire.closeModal()" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" size="sm" x-bind:disabled="!isDirty()">{{ __('Save Substatus') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
