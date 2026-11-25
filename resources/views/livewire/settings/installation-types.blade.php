<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-4xl">
    <x-ui.page-header :title="__('Installation type settings')" :subtitle="__('Manage installation options, customize their colors and keep every order in sync.')">
        <x-slot:actions>
            <div class="relative w-56">
                <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    wire:model.live.debounce.250ms="search"
                    type="text"
                    placeholder="{{ __('Search installation…') }}"
                    class="ui-input pl-9 pr-3" />
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">{{ __('New type') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-danger-soft text-danger border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-circle-alert class="w-4 h-4 shrink-0" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-surface border border-line rounded-card overflow-hidden shrink-0">
        <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-row font-semibold text-ink">{{ __('Available installation options') }}</span>
                <span class="text-meta text-ink-3">({{ $installationTypes->count() }})</span>
            </div>
            <div class="text-meta text-ink-3 hidden sm:block">
                {{ __('Name and color changes show up right away in Overview and on cards.') }}
            </div>
        </div>

        @if($installationTypes->isEmpty())
            <x-ui.empty icon="info" :title="__('No installation options found.')" :description="__('Create a new option with the button above.')" />
        @else
            <div class="divide-y divide-line">
                @foreach($installationTypes as $item)
                    @php
                        $ordersCount = $ordersCountByInstallation[$item->name] ?? 0;
                    @endphp
                    <div class="px-4 py-3 flex items-center justify-between hover:bg-sunken transition-colors gap-4">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <label class="relative cursor-pointer shrink-0 flex items-center" title="{{ __('Click to change the color of :name', ['name' => $item->name]) }}">
                                <input
                                    type="color"
                                    value="{{ $item->color ?? '#0284C7' }}"
                                    wire:change="updateColor({{ $item->id }}, $event.target.value)"
                                    class="sr-only" />
                                <div class="w-6 h-6 rounded-control border border-black/15" style="background-color: {{ $item->color ?? '#0284C7' }};"></div>
                            </label>

                            <span
                                class="px-2.5 py-0.5 rounded-control text-body font-medium border shrink-0"
                                style="background-color: {{ $item->bg_color }}; color: {{ $item->text_color }}; border-color: {{ $item->border_color }};">
                                {{ $item->name }}
                            </span>

                            @if(!$item->is_active)
                                <x-ui.tag tone="neutral">{{ __('Inactive') }}</x-ui.tag>
                            @endif

                            <span class="text-meta text-ink-3 hidden md:inline">
                                {{ $ordersCount }} {{ $ordersCount === 1 ? __('linked order') : __('linked orders') }}
                            </span>
                        </div>

                        <div class="hidden sm:flex items-center shrink-0">
                            <button
                                type="button"
                                wire:click="toggleStyleType({{ $item->id }})"
                                title="{{ __('Toggle style (solid color / light background)') }}"
                                class="text-meta px-2 py-1 rounded-control border border-line text-ink-2 hover:bg-sunken transition cursor-pointer">
                                {{ $item->style_type === 'solid' ? __('Solid Color') : __('Light Background') }}
                            </button>
                        </div>

                        <div class="flex items-center shrink-0">
                            <button
                                wire:click="toggleActive({{ $item->id }})"
                                class="text-meta px-2 py-1 rounded-control border border-line transition cursor-pointer hover:bg-sunken {{ $item->is_active ? 'text-ink' : 'text-ink-3' }}">
                                {{ $item->is_active ? __('Visible') : __('Hidden') }}
                            </button>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <flux:button wire:click="openEditModal({{ $item->id }})" variant="subtle" size="sm" icon="pencil">{{ __('Edit') }}</flux:button>

                            <button
                                wire:click="delete({{ $item->id }})"
                                wire:confirm="{{ __('Delete the installation option ":name"? Orders with this value keep the text, but it will no longer appear in the list.', ['name' => $item->name]) }}"
                                class="p-1.5 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer"
                                title="{{ __('Delete option') }}">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Create / Edit Modal (saving never asks for confirmation) -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40">
            <div
                x-data
                @keydown.window.escape="$wire.closeModal()"
                class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-section font-semibold text-ink">{{ $editingId ? __('Edit installation type') : __('New installation type') }}</h3>
                    <button type="button" @click="$wire.closeModal()" class="text-ink-3 hover:text-ink cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Installation name') }}</label>
                        <input
                            wire:model="name"
                            type="text"
                            placeholder="{{ __('e.g. Send courier') }}"
                            class="ui-input" />
                        @error('name') <span class="text-meta text-danger mt-0.5 block">{{ $message }}</span> @enderror
                        @if($editingId)
                            <span class="text-meta text-ink-3 mt-0.5 block">{{ __('If you rename it, every existing order with this value updates automatically.') }}</span>
                        @endif
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-meta text-ink-2">{{ __('Background Style') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                wire:click="setStyleType('light')"
                                class="p-2 rounded-control border text-body font-medium transition cursor-pointer {{ $style_type === 'light' ? 'bg-ink text-white border-ink' : 'bg-surface border-line text-ink-2 hover:bg-sunken' }}">
                                {{ __('Light Background') }}
                            </button>
                            <button
                                type="button"
                                wire:click="setStyleType('solid')"
                                class="p-2 rounded-control border text-body font-medium transition cursor-pointer {{ $style_type === 'solid' ? 'bg-ink text-white border-ink' : 'bg-surface border-line text-ink-2 hover:bg-sunken' }}">
                                {{ __('Solid Color') }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-meta text-ink-2">{{ __('Main color') }}</label>

                        <div class="flex items-center gap-3">
                            <input
                                wire:model.live="main_color"
                                type="color"
                                class="w-10 h-10 rounded-control border border-line-strong p-0.5 cursor-pointer shrink-0" />
                            <input
                                wire:model.live="main_color"
                                type="text"
                                class="w-32 text-body font-mono px-3 py-1.5 border border-line-strong rounded-control" />
                        </div>

                        <div class="pt-1">
                            <span class="text-meta text-ink-3 block mb-1.5">{{ __('Quick palette:') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $presets = [
                                        '#0284C7' => __('Sky'),
                                        '#2563EB' => __('Blue'),
                                        '#4F46E5' => __('Indigo'),
                                        '#7C3AED' => __('Violet'),
                                        '#9333EA' => __('Purple'),
                                        '#C026D3' => __('Fuchsia'),
                                        '#DB2777' => __('Pink'),
                                        '#DC2626' => __('Red'),
                                        '#EA580C' => __('Orange'),
                                        '#D97706' => __('Amber'),
                                        '#059669' => __('Emerald'),
                                        '#0D9488' => __('Teal'),
                                        '#475569' => __('Slate'),
                                        '#57534E' => __('Stone'),
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

                    <div class="pt-1 border-t border-line">
                        <label class="flex items-center gap-2 text-body text-ink-2 cursor-pointer pt-3">
                            <input type="checkbox" wire:model="is_active" class="rounded border-line-strong text-ink focus:ring-gold">
                            <span>{{ __('Enabled / visible in Overview menus') }}</span>
                        </label>
                    </div>

                    <div class="p-3 bg-sunken rounded-control space-y-1.5">
                        <span class="text-meta text-ink-3 block">{{ __('Badge preview') }}</span>
                        <span
                            class="px-3 py-1 rounded-control text-body font-medium border inline-block"
                            style="background-color: {{ $bg_color }}; color: {{ $text_color }}; border-color: {{ $border_color }};">
                            {{ $name ?: __('Sample installation') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                        <flux:button type="button" @click="$wire.closeModal()" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
