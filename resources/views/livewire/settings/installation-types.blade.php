<div class="flex-1 w-full min-h-0 flex flex-col space-y-6 overflow-y-auto custom-vertical-scrollbar pr-2 max-w-6xl mx-auto pb-28">
    <!-- Header Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4 shrink-0">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Configuración de Tipos de Instalación') }}</h1>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Administra las opciones de instalación, personaliza sus colores y mantén sincronizadas todas las órdenes del sistema.') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative w-64">
                <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input 
                    wire:model.live.debounce.250ms="search" 
                    type="text" 
                    placeholder="{{ __('Buscar instalación...') }}" 
                    class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 transition" />
            </div>

            <button 
                wire:click="openCreateModal" 
                class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                <x-lucide-plus class="w-4 h-4" />
                <span>{{ __('Nuevo Tipo') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between shrink-0">
            <span class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ session('message') }}</span>
            </span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between shrink-0">
            <span class="flex items-center gap-2">
                <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" />
                <span>{{ session('error') }}</span>
            </span>
        </div>
    @endif

    <!-- Installation Types List -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden shrink-0">
        <div class="px-5 py-3.5 bg-stone-50/70 border-b border-[#e9e9e7] flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-lucide-truck class="w-4 h-4 text-zinc-700" />
                <span class="font-bold text-xs text-zinc-900">{{ __('Opciones de Instalación Disponibles') }}</span>
                <span class="text-xs text-zinc-500 font-medium ml-1">({{ $installationTypes->count() }})</span>
            </div>
            <div class="text-[11px] text-zinc-400 hidden sm:block">
                {{ __('Los cambios en nombres y colores se reflejan en tiempo real en Overview y tarjetas.') }}
            </div>
        </div>

        @if($installationTypes->isEmpty())
            <div class="p-8 text-center text-xs text-zinc-500">
                <x-lucide-info class="w-8 h-8 text-zinc-300 mx-auto mb-2" />
                <p class="font-medium">{{ __('No se encontraron opciones de instalación.') }}</p>
                <p class="text-zinc-400 mt-1">{{ __('Crea una nueva opción con el botón superior.') }}</p>
            </div>
        @else
            <div class="divide-y divide-stone-100">
                @foreach($installationTypes as $item)
                    @php
                        $ordersCount = $ordersCountByInstallation[$item->name] ?? 0;
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-stone-50/60 transition gap-4">
                        <!-- Color Picker & Badge Preview -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <label class="relative cursor-pointer shrink-0 group flex items-center" title="{{ __('Clic para cambiar el color de :name', ['name' => $item->name]) }}">
                                <input 
                                    type="color" 
                                    value="{{ $item->color ?? '#0284C7' }}" 
                                    wire:change="updateColor({{ $item->id }}, $event.target.value)" 
                                    class="sr-only" />
                                <div 
                                    class="w-7 h-7 rounded-lg border border-black/15 shadow-2xs flex items-center justify-center transition group-hover:scale-110 group-hover:shadow-md cursor-pointer" 
                                    style="background-color: {{ $item->color ?? '#0284C7' }};">
                                    <x-lucide-palette class="w-3.5 h-3.5 text-white/90 drop-shadow-xs opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </label>

                            <span 
                                class="px-3 py-1 rounded-md text-xs font-bold border shrink-0 shadow-2xs"
                                style="background-color: {{ $item->bg_color }}; color: {{ $item->text_color }}; border-color: {{ $item->border_color }};">
                                {{ $item->name }}
                            </span>

                            @if(!$item->is_active)
                                <span class="text-[10px] bg-stone-100 text-stone-500 border border-stone-200 px-2 py-0.5 rounded font-medium shrink-0">
                                    {{ __('Inactivo') }}
                                </span>
                            @endif

                            <span class="text-[11px] text-zinc-400 font-medium hidden md:inline">
                                {{ $ordersCount }} {{ $ordersCount === 1 ? __('orden asociada') : __('órdenes asociadas') }}
                            </span>
                        </div>

                        <!-- Style Type Indicator / Toggle -->
                        <div class="hidden sm:flex items-center gap-2 shrink-0">
                            <button 
                                type="button"
                                wire:click="toggleStyleType({{ $item->id }})"
                                title="{{ __('Alternar estilo (Color Sólido / Fondo Claro)') }}"
                                class="text-[10px] px-2.5 py-1 rounded-lg border font-medium transition cursor-pointer hover:scale-105 shadow-2xs {{ $item->style_type === 'solid' ? 'bg-stone-800 text-white border-stone-900 hover:bg-stone-900' : 'bg-stone-100 text-zinc-700 border-stone-200 hover:bg-stone-200' }}">
                                {{ $item->style_type === 'solid' ? __('Color Sólido') : __('Fondo Claro') }}
                            </button>
                        </div>

                        <!-- Active Toggle -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                wire:click="toggleActive({{ $item->id }})" 
                                class="text-xs px-2.5 py-1 rounded-lg border transition cursor-pointer {{ $item->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-stone-100 text-stone-500 border-stone-200 hover:bg-stone-200' }}">
                                {{ $item->is_active ? __('Visible') : __('Oculto') }}
                            </button>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                wire:click="openEditModal({{ $item->id }})" 
                                class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition flex items-center gap-1 cursor-pointer">
                                <x-lucide-pencil class="w-3.5 h-3.5" />
                                <span>{{ __('Editar') }}</span>
                            </button>

                            <button 
                                wire:click="delete({{ $item->id }})" 
                                wire:confirm="{{ __('¿Estás seguro de eliminar la opción de instalación ":name"? Si hay órdenes con este valor, mantendrán el texto pero ya no estará en la lista.', ['name' => $item->name]) }}" 
                                class="p-1 rounded-lg bg-white hover:bg-red-50 text-red-600 border border-stone-200 hover:border-red-200 transition cursor-pointer" 
                                title="{{ __('Eliminar Opción') }}">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Create / Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
            <div 
                x-data="{
                    initialName: '',
                    initialColor: '',
                    initialStyleType: '',
                    init() {
                        this.initialName = $wire.name || '';
                        this.initialColor = $wire.main_color || '';
                        this.initialStyleType = $wire.style_type || '';
                        if (window.KudosDirtyGuard) {
                            window.KudosDirtyGuard.register('installation-modal', () => this.isDirty());
                            this.$cleanup(() => window.KudosDirtyGuard.unregister('installation-modal'));
                        }
                    },
                    isDirty() {
                        if (!$wire.showModal) return false;
                        return ($wire.name || '') !== this.initialName || 
                               ($wire.main_color || '') !== this.initialColor ||
                               ($wire.style_type || '') !== this.initialStyleType;
                    },
                    confirmClose(action) {
                        if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                            return;
                        }
                        if (this.isDirty() && window.KudosDirtyGuard) {
                            window.KudosDirtyGuard.openConfirmModal({
                                title: @js(__('¿Guardar cambios?')),
                                description: @js(__('Has modificado la configuración de este tipo de instalación.')),
                                cancelText: @js(__('Cancelar')),
                                discardText: @js(__('No guardar')),
                                saveText: @js(__('Guardar')),
                                onCancel: () => {},
                                onDiscard: () => {
                                    window.KudosDirtyGuard.unregister('installation-modal');
                                    action();
                                },
                                onSave: () => {
                                    window.KudosDirtyGuard.unregister('installation-modal');
                                    $wire.save();
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
                        <x-lucide-truck class="w-4 h-4 text-stone-700" />
                        <span>{{ $editingId ? __('Editar Tipo de Instalación') : __('Nuevo Tipo de Instalación') }}</span>
                    </h3>
                    <button type="button" @click="confirmClose(() => $wire.closeModal())" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields -->
                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">{{ __('Nombre de Instalación') }}</label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            placeholder="{{ __('Ej: ENVIAR CURRIER') }}" 
                            class="w-full px-3 py-2 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-400 focus:outline-none uppercase font-semibold" />
                        @error('name') <span class="text-[11px] text-red-600 mt-0.5 block">{{ $message }}</span> @enderror
                        @if($editingId)
                            <span class="text-[10px] text-zinc-400 mt-0.5 block">{{ __('Si cambias el nombre, todas las órdenes existentes con este valor se actualizarán automáticamente.') }}</span>
                        @endif
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

                    <!-- Main Color Selection & Presets -->
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-zinc-700">{{ __('Color Principal') }}</label>
                        
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
                            <span class="text-[11px] font-medium text-zinc-600 block mb-1.5">{{ __('Paleta Rápida:') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $presets = [
                                        '#0284C7' => __('Sky'),
                                        '#2563EB' => __('Azul'),
                                        '#4F46E5' => __('Índigo'),
                                        '#7C3AED' => __('Violeta'),
                                        '#9333EA' => __('Púrpura'),
                                        '#C026D3' => __('Fucsia'),
                                        '#DB2777' => __('Rosa'),
                                        '#DC2626' => __('Rojo'),
                                        '#EA580C' => __('Naranja'),
                                        '#D97706' => __('Ámbar'),
                                        '#059669' => __('Esmeralda'),
                                        '#0D9488' => __('Teal'),
                                        '#475569' => __('Pizarra'),
                                        '#57534E' => __('Piedra'),
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

                    <!-- Visibility checkbox -->
                    <div class="flex items-center gap-2 pt-1 border-t border-stone-100">
                        <label class="flex items-center gap-2 text-xs font-medium text-zinc-700 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-stone-300 text-stone-900 focus:ring-stone-400">
                            <span>{{ __('Habilitado / Visible en menús de Overview') }}</span>
                        </label>
                    </div>

                    <!-- Live Badge Preview -->
                    <div class="p-4 bg-stone-50 border border-stone-200 rounded-xl space-y-1.5">
                        <span class="text-[10px] text-zinc-400 uppercase font-semibold block">{{ __('Vista Previa del Badge') }}</span>
                        <div class="pt-0.5">
                            <span 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold border shadow-2xs inline-block"
                                style="background-color: {{ $bg_color }}; color: {{ $text_color }}; border-color: {{ $border_color }};">
                                {{ $name ? strtoupper($name) : __('EJEMPLO INSTALACIÓN') }}
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
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-check class="w-3.5 h-3.5" />
                            <span>{{ __('Guardar') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
