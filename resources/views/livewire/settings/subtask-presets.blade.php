<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 max-w-6xl mx-auto">
    <!-- Header Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-stone-900 text-white rounded-xl flex items-center justify-center font-bold shadow-xs">
                <x-lucide-list-checks class="w-5 h-5 text-stone-100" />
            </div>
            <div>
                <h1 class="text-lg font-bold text-zinc-900 tracking-tight">Gestión de Subtareas & Tareas del Sistema</h1>
                <p class="text-xs text-zinc-500 mt-0.5">Configura las plantillas diarias del planificador semanal y activa/desactiva tareas del sistema.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative w-60 sm:w-64">
                <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-2.5" />
                <input 
                    wire:model.live.debounce.250ms="search" 
                    type="text" 
                    placeholder="Buscar tarea o plantilla..." 
                    class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 transition" />
            </div>

            @if($activeTab === 'presets')
                <button 
                    wire:click="openCreateModal" 
                    class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer shrink-0">
                    <x-lucide-plus class="w-4 h-4" />
                    <span>Nueva Subtarea</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Navigation Tabs Bar -->
    <div class="flex items-center gap-2 border-b border-[#e9e9e7] pb-2 shrink-0 text-xs font-medium">
        <button 
            wire:click="setActiveTab('presets')" 
            class="px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-2 {{ $activeTab === 'presets' ? 'bg-stone-900 text-white font-semibold shadow-2xs' : 'bg-white border border-stone-200 text-zinc-600 hover:bg-stone-50' }}">
            <x-lucide-list-checks class="w-4 h-4" />
            <span>Plantillas de Subtareas Diarias ({{ $presets->count() }})</span>
        </button>
        
        <button 
            wire:click="setActiveTab('system')" 
            class="px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-2 {{ $activeTab === 'system' ? 'bg-stone-900 text-white font-semibold shadow-2xs' : 'bg-white border border-stone-200 text-zinc-600 hover:bg-stone-50' }}">
            <x-lucide-cog class="w-4 h-4" />
            <span>Tareas Relacionadas del Sistema ({{ $systemTaskConfigs->count() }})</span>
        </button>
    </div>

    <!-- Flash Notifications -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600" />
                <span>{{ session('message') }}</span>
            </div>
        </div>
    @endif

    <!-- TAB 1: PRESETS (Plantillas de Subtareas) -->
    @if($activeTab === 'presets')
        <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden space-y-0">
            <div class="px-5 py-4 border-b border-[#e9e9e7] flex items-center justify-between bg-stone-50/50">
                <div>
                    <h2 class="font-bold text-xs text-zinc-800 uppercase tracking-wider">Subtareas Predeterminadas (Weekly Planner)</h2>
                    <p class="text-[11px] text-zinc-500 mt-0.5">Opciones rápidas disponibles al hacer clic en "+ Subtarea" en la agenda diaria.</p>
                </div>
            </div>

            @if($presets->isEmpty())
                <div class="p-12 text-center text-zinc-400">
                    <x-lucide-list-checks class="w-10 h-10 mx-auto text-zinc-300 mb-2" />
                    <p class="text-xs">No se encontraron subtareas que coincidan con la búsqueda.</p>
                </div>
            @else
                <div class="divide-y divide-stone-100">
                    @foreach($presets as $index => $preset)
                        <div class="px-5 py-3.5 flex items-center justify-between hover:bg-stone-50/60 transition gap-4">
                            
                            <!-- Order buttons & Chip Preview -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Reorder buttons -->
                                <div class="flex flex-col gap-0.5 shrink-0">
                                    <button 
                                        wire:click="moveUp({{ $preset->id }})" 
                                        @if($loop->first) disabled @endif
                                        class="p-0.5 text-zinc-400 hover:text-zinc-800 disabled:opacity-20 transition"
                                        title="Mover arriba">
                                        <x-lucide-chevron-up class="w-3.5 h-3.5" />
                                    </button>
                                    <button 
                                        wire:click="moveDown({{ $preset->id }})" 
                                        @if($loop->last) disabled @endif
                                        class="p-0.5 text-zinc-400 hover:text-zinc-800 disabled:opacity-20 transition"
                                        title="Mover abajo">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Badge Chip Preview -->
                                @php
                                    $safePresetIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                @endphp
                                <span 
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold border shrink-0 shadow-2xs inline-flex items-center gap-1.5 {{ $preset->badgeStyle() }}">
                                    <x-dynamic-component :component="'lucide-' . $safePresetIcon" class="w-3.5 h-3.5" />
                                    <span>{{ $preset->title }}</span>
                                </span>

                                @if(!$preset->is_active)
                                    <span class="text-[10px] bg-stone-100 text-stone-500 px-2 py-0.5 rounded border border-stone-200 font-medium shrink-0">
                                        Inactiva
                                    </span>
                                @endif
                            </div>

                            <!-- Active Toggle & Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button 
                                    wire:click="toggleActive({{ $preset->id }})" 
                                    class="px-2.5 py-1 rounded-lg border text-xs font-medium transition cursor-pointer flex items-center gap-1.5 {{ $preset->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-stone-50 text-stone-600 border-stone-200' }}">
                                    <span class="w-2 h-2 rounded-full {{ $preset->is_active ? 'bg-emerald-500' : 'bg-stone-400' }}"></span>
                                    <span>{{ $preset->is_active ? 'Activa' : 'Inactiva' }}</span>
                                </button>

                                <button 
                                    wire:click="openEditModal({{ $preset->id }})" 
                                    class="px-2.5 py-1 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition flex items-center gap-1 cursor-pointer">
                                    <x-lucide-pencil class="w-3.5 h-3.5" />
                                    <span>Editar</span>
                                </button>

                                <button 
                                    wire:click="delete({{ $preset->id }})" 
                                    wire:confirm="¿Estás seguro de eliminar la plantilla de subtarea '{{ $preset->title }}'?" 
                                    class="p-1 rounded-lg bg-white hover:bg-red-50 text-red-600 border border-stone-200 hover:border-red-200 transition cursor-pointer" 
                                    title="Eliminar Subtarea">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: SYSTEM RELATED TASKS (Tareas Relacionadas del Sistema con Toggle y Descripción) -->
    @if($activeTab === 'system')
        <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden">
            <div class="px-5 py-4 border-b border-[#e9e9e7] flex items-center justify-between bg-stone-50/50">
                <div>
                    <h2 class="font-bold text-xs text-zinc-800 uppercase tracking-wider">Tareas Relacionadas del Sistema (System Tasks)</h2>
                    <p class="text-[11px] text-zinc-500 mt-0.5">Acciones operativas predefinidas del sistema. Activa o desactiva su uso y revisa su funcionalidad.</p>
                </div>
            </div>

            @if($systemTaskConfigs->isEmpty())
                <div class="p-12 text-center text-zinc-400">
                    <x-lucide-cog class="w-10 h-10 mx-auto text-zinc-300 mb-2" />
                    <p class="text-xs">No se encontraron tareas del sistema que coincidan con el filtro de búsqueda.</p>
                </div>
            @else
                <div class="divide-y divide-stone-100">
                    @foreach($systemTaskConfigs as $sysTask)
                        <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-stone-50/60 transition">
                            
                            <!-- Task Details & Description -->
                            <div class="space-y-1.5 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-sm text-zinc-900">{{ $sysTask->title }}</h3>
                                    
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $sysTask->categoryBadgeStyle() }}">
                                        {{ $sysTask->category }}
                                    </span>

                                    <span class="px-2 py-0.5 rounded bg-stone-100 text-stone-600 font-mono text-[10px] border border-stone-200">
                                        {{ $sysTask->task_type }}
                                    </span>
                                </div>

                                <p class="text-xs text-zinc-600 leading-relaxed font-normal">
                                    {{ $sysTask->description }}
                                </p>
                            </div>

                            <!-- Toggle Button -->
                            <div class="shrink-0 flex items-center gap-3 border-t md:border-t-0 pt-2 md:pt-0 border-stone-100">
                                <span class="text-xs font-semibold {{ $sysTask->is_active ? 'text-emerald-700' : 'text-stone-400' }}">
                                    {{ $sysTask->is_active ? 'Encendida (Habilitada)' : 'Apagada (Deshabilitada)' }}
                                </span>

                                <button 
                                    wire:click="toggleSystemTaskActive({{ $sysTask->id }})" 
                                    type="button"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $sysTask->is_active ? 'bg-emerald-500' : 'bg-stone-300' }}"
                                    role="switch"
                                    aria-checked="{{ $sysTask->is_active ? 'true' : 'false' }}">
                                    <span 
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $sysTask->is_active ? 'translate-x-5' : 'translate-x-0' }}">
                                    </span>
                                </button>
                            </div>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Create / Edit Subtask Preset Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                        <x-lucide-list-checks class="w-4 h-4 text-stone-700" />
                        <span>{{ $editingId ? 'Editar Subtarea' : 'Nueva Subtarea Predeterminada' }}</span>
                    </h3>
                    <button wire:click="closeModal" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields -->
                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 mb-1">Título de la Subtarea</label>
                        <input 
                            wire:model="title" 
                            type="text" 
                            placeholder="Ej: Revisiones cliente, Ajuste Camila..." 
                            class="w-full px-3 py-2 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-400 focus:outline-none font-semibold text-zinc-800" />
                        @error('title') <span class="text-[11px] text-red-600 mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Lucide Icon Library Grid Picker -->
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-zinc-700 text-xs mb-1">Ícono de la Subtarea (Icon Library)</label>
                        @php
                            $iconLibrary = [
                                'sparkles' => 'Propuesta',
                                'user-check' => 'Camila',
                                'message-square' => 'Cliente',
                                'ruler' => 'Medidas',
                                'wrench' => 'Ajustes',
                                'palette' => 'Diseño',
                                'file-text' => 'Documento',
                                'check-circle-2' => 'Check',
                                'tag' => 'Etiqueta',
                                'clock' => 'Tiempo',
                                'send' => 'Envío',
                                'alert-triangle' => 'Alerta',
                                'box' => 'Caja / POP',
                                'zap' => 'Urgente',
                            ];
                        @endphp
                        <div class="grid grid-cols-7 gap-1.5 p-2 bg-[#fbfbfa] border border-[#e9e9e7] rounded-xl">
                            @foreach($iconLibrary as $iconKey => $iconLabel)
                                <button 
                                    type="button" 
                                    wire:click="$set('emoji', '{{ $iconKey }}')"
                                    title="{{ $iconLabel }}"
                                    class="w-8 h-8 rounded-lg border transition flex items-center justify-center cursor-pointer {{ $emoji === $iconKey ? 'bg-stone-900 text-white border-stone-900 shadow-2xs' : 'bg-white text-zinc-700 border-stone-200 hover:bg-stone-100' }}">
                                    <x-dynamic-component :component="'lucide-' . $iconKey" class="w-4 h-4" />
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Color Theme Picker -->
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-zinc-700">Tema de Color (Badge)</label>
                        <div class="grid grid-cols-4 gap-2">
                            @php
                                $themes = [
                                    'sky' => ['label' => 'Sky', 'bg' => 'bg-sky-50 text-sky-800 border-sky-200'],
                                    'purple' => ['label' => 'Púrpura', 'bg' => 'bg-purple-50 text-purple-800 border-purple-200'],
                                    'emerald' => ['label' => 'Verde', 'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                                    'amber' => ['label' => 'Ámbar', 'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
                                    'rose' => ['label' => 'Rosa', 'bg' => 'bg-rose-50 text-rose-800 border-rose-200'],
                                    'violet' => ['label' => 'Violeta', 'bg' => 'bg-violet-50 text-violet-800 border-violet-200'],
                                    'indigo' => ['label' => 'Índigo', 'bg' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
                                    'stone' => ['label' => 'Gris', 'bg' => 'bg-stone-100 text-stone-800 border-stone-200'],
                                ];
                            @endphp
                            @foreach($themes as $key => $info)
                                <button 
                                    type="button" 
                                    wire:click="$set('color_theme', '{{ $key }}')"
                                    class="p-2 rounded-xl border text-center text-xs font-medium transition cursor-pointer flex items-center justify-center gap-1.5 {{ $info['bg'] }} {{ $color_theme === $key ? 'ring-2 ring-stone-900 ring-offset-1 font-bold' : '' }}">
                                    <span>{{ $info['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Active Checkbox -->
                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            wire:model="is_active" 
                            type="checkbox" 
                            id="is_active" 
                            class="w-4 h-4 rounded text-stone-900 focus:ring-stone-400 border-stone-300" />
                        <label for="is_active" class="text-xs font-medium text-zinc-700 cursor-pointer">Activa (disponible en el planificador)</label>
                    </div>

                    <!-- Live Badge Preview -->
                    <div class="p-3 bg-stone-50 border border-stone-200 rounded-xl space-y-1">
                        <span class="text-[10px] text-zinc-400 uppercase font-semibold block">Vista Previa del Botón</span>
                        <div class="pt-0.5">
                            @php
                                $previewPreset = new \App\Models\SubtaskPreset(['color_theme' => $color_theme]);
                                $safePreviewIcon = (preg_match('/^[a-z0-9\-]+$/i', $emoji ?? '')) ? $emoji : 'tag';
                            @endphp
                            <span class="px-2.5 py-1 rounded-md text-xs font-semibold border inline-flex items-center gap-1.5 shadow-2xs {{ $previewPreset->badgeStyle() }}">
                                <x-dynamic-component :component="'lucide-' . $safePreviewIcon" class="w-3.5 h-3.5" />
                                <span>{{ $title ? $title : 'Ejemplo Subtarea' }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                        <button 
                            type="button" 
                            wire:click="closeModal" 
                            class="px-4 py-2 border border-stone-200 text-zinc-700 hover:bg-stone-50 text-xs font-medium rounded-xl transition cursor-pointer">
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition cursor-pointer">
                            Guardar Subtarea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
