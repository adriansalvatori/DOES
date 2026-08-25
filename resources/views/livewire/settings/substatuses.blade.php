<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 max-w-6xl mx-auto">
    <!-- Header Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-stone-900 text-white rounded-xl flex items-center justify-center font-bold shadow-xs">
                <x-lucide-settings-2 class="w-5 h-5" />
            </div>
            <div>
                <h1 class="text-lg font-bold text-zinc-900 tracking-tight">{{ __('Configuración de Subestatus') }}</h1>
                <p class="text-xs text-zinc-500 mt-0.5">{{ __('Crea, personaliza y asigna códigos de color para los subestatus de las órdenes.') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative w-64">
                <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-2.5" />
                <input 
                    wire:model.live.debounce.250ms="search" 
                    type="text" 
                    placeholder="{{ __('Buscar subestatus...') }}" 
                    class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 transition" />
            </div>

            <button 
                wire:click="openCreateModal" 
                class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                <x-lucide-plus class="w-4 h-4" />
                <span>{{ __('Nuevo Subestatus') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Substatuses Table List -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e9e9e7] flex items-center justify-between bg-stone-50/50">
            <h2 class="font-bold text-xs text-zinc-800 uppercase tracking-wider">{{ __('Lista de Subestatus') }} ({{ $substatuses->count() }})</h2>
            <span class="text-[11px] text-zinc-400">{{ __('Los cambios se aplican automáticamente en todas las vistas') }}</span>
        </div>

        @if($substatuses->isEmpty())
            <div class="p-12 text-center text-zinc-400">
                <x-lucide-tag class="w-10 h-10 mx-auto text-zinc-300 mb-2" />
                <p class="text-xs">{{ __('No se encontraron subestatus que coincidan con la búsqueda.') }}</p>
            </div>
        @else
            <div class="divide-y divide-stone-100">
                @foreach($substatuses as $sub)
                    <div class="px-5 py-3.5 flex items-center justify-between hover:bg-stone-50/60 transition gap-4">
                        <!-- Substatus Badge Preview -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span 
                                class="px-3 py-1 rounded-md text-xs font-bold border shrink-0 shadow-2xs"
                                style="background-color: {{ $sub->bg_color }}; color: {{ $sub->text_color }}; border-color: {{ $sub->border_color }};">
                                {{ __($sub->name) }}
                            </span>
                            @if($sub->is_system)
                                <span class="text-[10px] bg-stone-100 text-stone-600 px-2 py-0.5 rounded border border-stone-200 font-medium shrink-0">
                                    {{ __('Sistema') }}
                                </span>
                            @endif
                        </div>

                        <!-- Style Type Indicator -->
                        <div class="hidden sm:flex items-center gap-2 shrink-0">
                            <span class="text-[10px] px-2 py-0.5 rounded border font-medium {{ $sub->style_type === 'solid' ? 'bg-stone-800 text-white border-stone-900' : 'bg-stone-100 text-zinc-600 border-stone-200' }}">
                                {{ $sub->style_type === 'solid' ? __('Color Sólido') : __('Fondo Claro') }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                wire:click="openEditModal({{ $sub->id }})" 
                                class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition flex items-center gap-1 cursor-pointer">
                                <x-lucide-pencil class="w-3.5 h-3.5" />
                                <span>{{ __('Editar') }}</span>
                            </button>

                            @if(!$sub->is_system)
                                <button 
                                    wire:click="delete({{ $sub->id }})" 
                                    wire:confirm="{{ __('¿Estás seguro de eliminar el subestatus ":name"?', ['name' => $sub->name]) }}" 
                                    class="p-1 rounded-lg bg-white hover:bg-red-50 text-red-600 border border-stone-200 hover:border-red-200 transition cursor-pointer" 
                                    title="{{ __('Eliminar Subestatus') }}">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Create / Edit Substatus Modal -->
    @if($showModal)
        <div @click.self="confirmClose(() => $wire.closeModal())" class="fixed inset-0 z-[100] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div 
                x-data="{
                    initialName: null,
                    initialColor: null,
                    init() {
                        this.initialName = $wire.name || '';
                        this.initialColor = $wire.main_color || '';
                        window.KudosDirtyGuard.register('substatus-modal', () => this.isDirty());
                    },
                    isDirty() {
                        return ($wire.name || '') !== this.initialName || ($wire.main_color || '') !== this.initialColor;
                    },
                    confirmClose(action) {
                        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                            return;
                        }
                        if (this.isDirty()) {
                            window.KudosDirtyGuard.openConfirmModal({
                                title: @js(__('¿Descartar cambios en subestatus?')),
                                description: @js(__('Has modificado la configuración de este subestatus. Si sales sin guardar, tus ajustes se descartarán.')),
                                confirmText: @js(__('Sí, descartar cambios')),
                                cancelText: @js(__('Continuar editando')),
                                onConfirm: () => {
                                    window.KudosDirtyGuard.unregister('substatus-modal');
                                    action();
                                }
                            });
                        } else {
                            action();
                        }
                    }
                }"
                @keydown.window.escape="confirmClose(() => $wire.closeModal())"
                class="bg-white rounded-2xl border border-stone-200 shadow-xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                        <x-lucide-tag class="w-4 h-4 text-stone-700" />
                        <span>{{ $editingId ? __('Editar Subestatus') : __('Nuevo Subestatus') }}</span>
                    </h3>
                    <button type="button" @click="confirmClose(() => $wire.closeModal())" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields -->
                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">{{ __('Nombre del Subestatus') }}</label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            placeholder="{{ __('Ej: EN REVISIÓN CLIENTE') }}" 
                            class="w-full px-3 py-2 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-400 focus:outline-none uppercase font-semibold" />
                        @error('name') <span class="text-[11px] text-red-600 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Style Type Selector: Light vs Solid -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-zinc-700">{{ __('Estilo de Fondo') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                type="button" 
                                wire:click="setStyleType('light')" 
                                class="p-2.5 rounded-xl border text-center text-xs font-semibold transition cursor-pointer flex items-center justify-center gap-2 {{ $style_type === 'light' ? 'bg-stone-900 text-white border-stone-900 shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                <x-lucide-sun class="w-3.5 h-3.5" />
                                <span>{{ __('Fondo Claro') }}</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="setStyleType('solid')" 
                                class="p-2.5 rounded-xl border text-center text-xs font-semibold transition cursor-pointer flex items-center justify-center gap-2 {{ $style_type === 'solid' ? 'bg-stone-900 text-white border-stone-900 shadow-2xs' : 'bg-stone-50 border-stone-200 text-zinc-700 hover:bg-stone-100' }}">
                                <x-lucide-paint-bucket class="w-3.5 h-3.5" />
                                <span>{{ __('Color Sólido') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Single Main Color Selection & Presets -->
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-zinc-700">{{ __('Color Principal del Subestatus') }}</label>
                        
                        <div class="flex items-center gap-3">
                            <input 
                                wire:model.live="main_color" 
                                type="color" 
                                class="w-10 h-10 rounded-xl border border-stone-300 p-0.5 cursor-pointer shadow-2xs shrink-0" />
                            <input 
                                wire:model.live="main_color" 
                                type="text" 
                                class="w-32 text-xs font-mono px-3 py-2 border border-stone-200 rounded-lg uppercase" />
                        </div>

                        <!-- Curated Palette Swatches -->
                        <div class="pt-2">
                            <span class="text-[11px] font-medium text-zinc-600 block mb-1.5">{{ __('Paleta del Proyecto:') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $presets = [
                                        '#EF4444' => __('Rojo'),
                                        '#F43F5E' => __('Rosa'),
                                        '#F97316' => __('Naranja'),
                                        '#F59E0B' => __('Ámbar'),
                                        '#10B981' => __('Verde'),
                                        '#14B8A6' => __('Teal'),
                                        '#0EA5E9' => __('Sky'),
                                        '#3B82F6' => __('Azul'),
                                        '#6366F1' => __('Índigo'),
                                        '#A855F7' => __('Púrpura'),
                                        '#EC4899' => __('Fucsia'),
                                        '#78716C' => __('Piedra'),
                                    ];
                                @endphp
                                @foreach($presets as $hex => $label)
                                    <button 
                                        type="button"
                                        wire:click="selectPresetColor('{{ $hex }}')" 
                                        class="w-6 h-6 rounded-full border border-black/10 transition shadow-2xs hover:scale-110 cursor-pointer {{ strtoupper($main_color) === $hex ? 'ring-2 ring-offset-2 ring-stone-900 scale-110' : '' }}" 
                                        style="background-color: {{ $hex }};" 
                                        title="{{ $label }}">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Live Badge Preview -->
                    <div class="p-4 bg-stone-50 border border-stone-200 rounded-xl space-y-1.5">
                        <span class="text-[10px] text-zinc-400 uppercase font-semibold block">{{ __('Vista Previa') }}</span>
                        <div class="pt-0.5">
                            <span 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold border shadow-2xs inline-block"
                                style="background-color: {{ $bg_color }}; color: {{ $text_color }}; border-color: {{ $border_color }};">
                                {{ $name ? strtoupper($name) : __('EJEMPLO SUBESTATUS') }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                        <button 
                            type="button" 
                            @click="confirmClose(() => $wire.closeModal())" 
                            class="px-4 py-2 border border-stone-200 text-zinc-700 hover:bg-stone-50 text-xs font-medium rounded-xl transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition cursor-pointer">
                            {{ __('Guardar Subestatus') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
