<div class="h-full w-full max-w-4xl flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10">

    <x-ui.page-header :title="__('Personalización del Color Coding')" :subtitle="$totalItemsCount . ' ' . __('entidades') . ' · ' . __('Los colores se guardan al instante.')">
        <x-slot:actions>
            <flux:button wire:click="confirmResetAll" type="button" variant="outline" size="sm" icon="arrow-uturn-left">{{ __('Restablecer Predeterminados') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status_message'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="px-3.5 py-2.5 bg-ok-soft text-ok border border-line rounded-card text-body flex items-center justify-between gap-2 shrink-0">
            <div class="flex items-center gap-2">
                <x-lucide-check class="w-4 h-4 shrink-0" />
                <span>{{ session('status_message') }}</span>
            </div>
            <button @click="show = false" class="text-ok hover:opacity-70">
                <x-lucide-x class="w-3.5 h-3.5" />
            </button>
        </div>
    @endif

    <div class="flex items-center gap-1 border-b border-line overflow-x-auto pb-px text-body text-ink-3 shrink-0">
        @foreach($categories as $catKey => $cat)
            <button
                wire:click="setCategory('{{ $catKey }}')"
                class="px-3 py-2 transition flex items-center gap-2 border-b-2 cursor-pointer {{ $activeCategory === $catKey ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
                <span>{{ $cat['name'] }}</span>
                <span class="text-meta text-ink-3">{{ $cat['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-3">
        @foreach($items as $key => $item)
            @php
                $p = $item['palette'];
            @endphp
            <div wire:key="color-item-{{ $key }}" class="bg-surface border border-line rounded-card p-4 flex flex-col gap-3">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-row font-semibold text-ink">{{ $item['name'] }}</h2>
                            @if($item['is_custom'])
                                <x-ui.tag tone="neutral">{{ __('Personalizado') }}</x-ui.tag>
                            @endif
                        </div>
                        <span class="text-meta text-ink-3 block">{{ $item['category_name'] }}</span>
                        <p class="text-body text-ink-2 mt-1">{{ $item['description'] }}</p>
                    </div>

                    @if($item['is_custom'])
                        <button
                            wire:click="resetKey('{{ $key }}')"
                            title="{{ __('Restablecer al color original') }}"
                            class="text-meta text-ink-3 hover:text-ink font-medium flex items-center gap-1 cursor-pointer transition py-1 px-2 rounded-control hover:bg-sunken shrink-0">
                            <x-lucide-rotate-ccw class="w-3 h-3" />
                            <span>{{ __('Restablecer') }}</span>
                        </button>
                    @endif
                </div>

                <p class="text-meta text-ink-3">
                    <span class="text-ink-2">{{ __('Elementos Vinculados en el Sistema:') }}</span>
                    {{ implode(' · ', $item['affects']) }}
                </p>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-sunken p-3 rounded-control">
                    <div class="flex items-center gap-3">
                        <label class="relative cursor-pointer shrink-0" title="{{ __('Clic para abrir selector de color') }}">
                            <input type="color" wire:model.live="colors.{{ $key }}" class="sr-only" />
                            <div class="w-9 h-9 rounded-control border border-line-strong" style="background-color: {{ $item['hex'] }};"></div>
                        </label>

                        <div>
                            <span class="text-meta text-ink-3 block">{{ __('Código Hex') }}</span>
                            <input
                                type="text"
                                wire:model.live.debounce.400ms="colors.{{ $key }}"
                                class="font-mono text-body text-ink bg-surface border border-line rounded-control px-2 py-1 w-24 focus:outline-none focus:ring-1 focus:ring-gold"
                                maxlength="7" />
                        </div>
                    </div>

                    <div>
                        <span class="text-meta text-ink-3 block sm:text-right">{{ __('Paletas Recomendadas') }}</span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($item['presets'] as $hexOption)
                                <button
                                    type="button"
                                    wire:click="selectPreset('{{ $key }}', '{{ $hexOption }}')"
                                    title="{{ $hexOption }}"
                                    class="w-5 h-5 rounded-full cursor-pointer border border-black/10 {{ strcasecmp($item['hex'], $hexOption) === 0 ? 'ring-2 ring-ink ring-offset-1' : '' }}"
                                    style="background-color: {{ $hexOption }};">
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="text-meta text-ink-3 block">{{ __('Previsualización en Componentes') }}</span>
                    <div class="border border-line rounded-control p-3 space-y-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-1 rounded-control text-meta font-medium border flex items-center gap-1.5" style="{{ $p['badge_style'] }}">
                                <span class="w-1.5 h-1.5 rounded-full" style="{{ $p['dot_style'] }}"></span>
                                <span>{{ $item['name'] }}</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-control text-meta font-medium border" style="{{ $p['solid_badge_style'] }}">{{ __('Tag Sólido') }}</span>
                            <span class="flex items-center gap-1.5 text-meta text-ink-2">
                                <span class="inline-flex rounded-full h-2 w-2" style="{{ $p['dot_style'] }}"></span>
                                <span>{{ __('Dot Activo') }}</span>
                            </span>
                        </div>

                        <div class="rounded-control p-2.5 space-y-1.5 border" style="{{ $p['card_accent_style'] }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-meta font-semibold" style="{{ $p['header_title_style'] }}">{{ $item['name'] }}</span>
                                <span class="text-meta font-mono text-ink-3">WO-1082</span>
                            </div>
                            <p class="text-body text-ink truncate">Supermercados Talpa — Rediseño de Etiquetas Promocionales</p>
                            <div class="flex items-center justify-between pt-1 border-t border-black/5 text-meta text-ink-3">
                                <span>{{ __('Hoy a las 4:00 PM') }}</span>
                                <button type="button" class="px-2 py-0.5 rounded-control font-medium text-meta" style="{{ $p['button_style'] }}">{{ __('Acción') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($showResetAllModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/40 flex items-center justify-center p-4">
            <div @click.outside="$wire.set('showResetAllModal', false)" class="bg-surface border border-line rounded-panel max-w-md w-full p-5 shadow-panel space-y-4">
                <div class="space-y-1">
                    <h2 class="text-section font-semibold text-ink">{{ __('¿Restablecer todos los colores?') }}</h2>
                    <p class="text-body text-ink-2">
                        {{ __('Esta acción volverá a configurar todos los colores del sistema a la paleta estándar original de Kudos (Camila púrpura, Producción rosa brillante, etc.) y actualizará la base de datos.') }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                    <flux:button wire:click="$set('showResetAllModal', false)" type="button" variant="ghost" size="sm">{{ __('Cancelar') }}</flux:button>
                    <flux:button wire:click="resetAll" type="button" variant="danger" size="sm" icon="arrow-uturn-left">{{ __('Sí, restablecer todo') }}</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
