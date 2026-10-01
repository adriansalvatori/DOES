<div class="flex-1 w-full h-full flex flex-col space-y-6 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-2 max-w-7xl mx-auto pb-28">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-5">
        <div class="flex items-center gap-3 min-w-0 flex-wrap">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Personalización del Color Coding') }}</h1>
            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-stone-100 text-stone-700 border border-stone-200">
                {{ $totalItemsCount }} {{ __('entidades') }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button 
                wire:click="confirmResetAll"
                type="button"
                class="inline-flex items-center gap-2 px-3 py-2 border border-stone-200 hover:border-stone-300 bg-white hover:bg-stone-50 text-zinc-700 text-xs font-semibold rounded-xl shadow-2xs transition cursor-pointer">
                <x-lucide-rotate-ccw class="w-3.5 h-3.5 text-zinc-500" />
                <span>{{ __('Restablecer Predeterminados') }}</span>
            </button>
        </div>
    </div>

    <!-- Alert / Toast Message -->
    @if(session('status_message'))
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center justify-between gap-2 shadow-2xs">
            <div class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ session('status_message') }}</span>
            </div>
            <button @click="show = false" class="text-emerald-700 hover:text-emerald-900 text-xs">
                <x-lucide-x class="w-3.5 h-3.5" />
            </button>
        </div>
    @endif

    <!-- Category Tabs Navigation -->
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] overflow-x-auto pb-px text-xs font-medium text-zinc-500">
        @foreach($categories as $catKey => $cat)
            <button 
                wire:click="setCategory('{{ $catKey }}')" 
                class="px-3.5 py-2.5 rounded-t-xl transition flex items-center gap-2 border-b-2 cursor-pointer {{ $activeCategory === $catKey ? 'border-stone-900 text-stone-900 font-bold bg-white' : 'border-transparent hover:text-zinc-900 hover:bg-stone-50' }}">
                <span>{{ $cat['name'] }}</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeCategory === $catKey ? 'bg-stone-900 text-white' : 'bg-stone-100 text-zinc-500' }}">
                    {{ $cat['count'] }}
                </span>
            </button>
        @endforeach
    </div>

    <!-- Entities Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($items as $key => $item)
            @php
                $p = $item['palette'];
            @endphp
            <div 
                wire:key="color-item-{{ $key }}"
                class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-2xl p-5 shadow-2xs transition space-y-4 relative flex flex-col justify-between">
                
                <!-- Card Header -->
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div 
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs border transition"
                                style="background-color: {{ $p['bg_light'] }}; border-color: {{ $p['border'] }}; color: {{ $p['solid'] }};">
                                @if($item['icon'] === 'user-check')
                                    <x-lucide-user-check class="w-5 h-5" />
                                @elseif($item['icon'] === 'printer')
                                    <x-lucide-printer class="w-5 h-5" />
                                @elseif($item['icon'] === 'crown')
                                    <x-lucide-crown class="w-5 h-5" />
                                @elseif($item['icon'] === 'external-link')
                                    <x-lucide-external-link class="w-5 h-5" />
                                @elseif($item['icon'] === 'calendar-check')
                                    <x-lucide-calendar-check class="w-5 h-5" />
                                @elseif($item['icon'] === 'alert-triangle')
                                    <x-lucide-alert-triangle class="w-5 h-5" />
                                @elseif($item['icon'] === 'message-square')
                                    <x-lucide-message-square class="w-5 h-5" />
                                @elseif($item['icon'] === 'headphones')
                                    <x-lucide-headphones class="w-5 h-5" />
                                @else
                                    <x-lucide-palette class="w-5 h-5" />
                                @endif
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-bold text-sm text-zinc-900">{{ $item['name'] }}</h2>
                                    @if($item['is_custom'])
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            {{ __('Personalizado') }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wider block">
                                    {{ $item['category_name'] }}
                                </span>
                            </div>
                        </div>

                        @if($item['is_custom'])
                            <button 
                                wire:click="resetKey('{{ $key }}')"
                                title="{{ __('Restablecer al color original') }}"
                                class="text-[11px] text-zinc-400 hover:text-zinc-700 font-medium flex items-center gap-1 cursor-pointer transition py-1 px-2 rounded-lg hover:bg-stone-100">
                                <x-lucide-rotate-ccw class="w-3 h-3" />
                                <span>{{ __('Restablecer') }}</span>
                            </button>
                        @endif
                    </div>

                    <p class="text-xs text-zinc-500 leading-relaxed">
                        {{ $item['description'] }}
                    </p>

                    <!-- Scope / Affected Components Pills -->
                    <div class="pt-2 border-t border-stone-100">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1.5">
                            {{ __('Elementos Vinculados en el Sistema:') }}
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($item['affects'] as $aff)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[#f7f7f5] border border-stone-200 text-[10px] font-medium text-zinc-600">
                                    <x-lucide-link class="w-2.5 h-2.5 text-zinc-400 shrink-0" />
                                    <span>{{ $aff }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Color Picker & Controls Section -->
                <div class="space-y-4 pt-3 border-t border-stone-100">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-[#fafaf9] p-3 rounded-xl border border-stone-200/80">
                        <!-- Color Input Trigger & Hex Value -->
                        <div class="flex items-center gap-2.5">
                            <label class="relative cursor-pointer shrink-0" title="{{ __('Clic para abrir selector de color') }}">
                                <input 
                                    type="color" 
                                    wire:model.live="colors.{{ $key }}"
                                    class="sr-only" />
                                <div 
                                    class="w-9 h-9 rounded-xl shadow-xs border-2 border-white ring-1 ring-stone-300 transition hover:scale-105"
                                    style="background-color: {{ $item['hex'] }};">
                                </div>
                            </label>

                            <div class="space-y-0.5">
                                <span class="text-[10px] text-zinc-400 font-semibold block uppercase">{{ __('Código Hex') }}</span>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.400ms="colors.{{ $key }}"
                                    class="font-mono text-xs font-bold text-zinc-800 bg-white border border-stone-200 rounded-lg px-2 py-1 w-24 uppercase focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    maxlength="7" />
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div class="space-y-1">
                            <span class="text-[10px] text-zinc-400 font-semibold block uppercase sm:text-right">{{ __('Paletas Recomendadas') }}</span>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @foreach($item['presets'] as $hexOption)
                                    <button 
                                        type="button"
                                        wire:click="selectPreset('{{ $key }}', '{{ $hexOption }}')"
                                        title="{{ $hexOption }}"
                                        class="w-5 h-5 rounded-full transition cursor-pointer hover:scale-115 border border-black/10 shadow-2xs {{ strcasecmp($item['hex'], $hexOption) === 0 ? 'ring-2 ring-stone-900 ring-offset-1 scale-110' : '' }}"
                                        style="background-color: {{ $hexOption }};">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Live Showcase (Como se ve en la app) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 flex items-center gap-1">
                                <x-lucide-eye class="w-3 h-3 text-zinc-400" />
                                <span>{{ __('Previsualización en Componentes') }}</span>
                            </span>
                            <span class="text-[9px] text-zinc-400 font-mono">{{ __('Renderizado dinámico') }}</span>
                        </div>

                        <div class="bg-white border border-stone-200 rounded-xl p-3 space-y-3 shadow-2xs">
                            <!-- Row 1: Badges & Dots -->
                            <div class="flex items-center gap-2 flex-wrap text-xs">
                                <!-- Soft Badge -->
                                <span 
                                    class="px-2.5 py-1 rounded-md text-[11px] font-semibold border flex items-center gap-1.5 transition shadow-2xs"
                                    style="{{ $p['badge_style'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full" style="{{ $p['dot_style'] }}"></span>
                                    <span>{{ $item['name'] }}</span>
                                </span>

                                <!-- Solid Badge -->
                                <span 
                                    class="px-2 py-0.5 rounded text-[10px] font-bold border transition shadow-2xs uppercase tracking-wider"
                                    style="{{ $p['solid_badge_style'] }}">
                                    <span>{{ __('Tag Sólido') }}</span>
                                </span>

                                <!-- Pulsing Dot Indicator -->
                                <div class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-stone-50 border border-stone-200 text-[10px] font-medium text-zinc-600">
                                    <span class="flex h-2 w-2 relative shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="{{ $p['dot_style'] }}"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2" style="{{ $p['dot_style'] }}"></span>
                                    </span>
                                    <span>{{ __('Dot Activo') }}</span>
                                </div>
                            </div>

                            <!-- Row 2: Mini Kanban Card Simulation -->
                            <div 
                                class="rounded-xl p-2.5 space-y-1.5 text-xs transition border shadow-2xs"
                                style="{{ $p['card_accent_style'] }}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 font-bold" style="{{ $p['header_title_style'] }}">
                                        @if($item['icon'] === 'user-check')
                                            <x-lucide-user-check class="w-3.5 h-3.5" />
                                        @elseif($item['icon'] === 'printer')
                                            <x-lucide-printer class="w-3.5 h-3.5" />
                                        @else
                                            <x-lucide-folder class="w-3.5 h-3.5" />
                                        @endif
                                        <span class="text-[11px] uppercase tracking-wide">{{ $item['name'] }}</span>
                                    </div>
                                    <span class="text-[9px] font-mono text-zinc-400">WO-1082</span>
                                </div>
                                <p class="text-[11px] text-zinc-700 font-medium truncate">
                                    Supermercados Talpa — Rediseño de Etiquetas Promocionales
                                </p>
                                <div class="flex items-center justify-between pt-1 border-t border-black/5 text-[10px] text-zinc-500">
                                    <div class="flex items-center gap-1">
                                        <x-lucide-clock class="w-3 h-3 text-zinc-400" />
                                        <span>{{ __('Hoy a las 4:00 PM') }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        class="px-2 py-0.5 rounded font-semibold text-[10px] shadow-2xs transition"
                                        style="{{ $p['button_style'] }}">
                                        {{ __('Acción') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reset All Confirmation Modal -->
    @if($showResetAllModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div 
                @click.outside="$wire.set('showResetAllModal', false)"
                class="bg-white border border-[#e9e9e7] rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center">
                    <x-lucide-alert-triangle class="w-6 h-6" />
                </div>

                <div class="space-y-1">
                    <h2 class="text-base font-bold text-zinc-900">{{ __('¿Restablecer todos los colores?') }}</h2>
                    <p class="text-xs text-zinc-500 leading-relaxed">
                        {{ __('Esta acción volverá a configurar todos los colores del sistema a la paleta estándar original de Kudos (Camila púrpura, Producción rosa brillante, etc.) y actualizará la base de datos.') }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-stone-100">
                    <button 
                        wire:click="$set('showResetAllModal', false)"
                        type="button" 
                        class="px-3.5 py-2 text-xs font-semibold text-zinc-600 hover:text-zinc-900 bg-stone-100 hover:bg-stone-200 rounded-xl transition cursor-pointer">
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="resetAll"
                        type="button" 
                        class="px-3.5 py-2 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-2xs transition cursor-pointer flex items-center gap-1.5">
                        <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                        <span>{{ __('Sí, restablecer todo') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
