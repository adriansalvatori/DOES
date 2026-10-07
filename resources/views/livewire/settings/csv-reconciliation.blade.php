<div class="w-full max-w-7xl mx-auto space-y-6 pb-28">

    <!-- Header Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4 shrink-0">
        <div class="min-w-0">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600">
                    <x-lucide-file-spreadsheet class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Conciliación & Migración CSV') }}</h1>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Compara tu CSV de órdenes con la base de datos actual, revisa coincidencias en 3 niveles y aplica actualizaciones silenciosas.') }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            @if($hasAnalysis)
                <button 
                    wire:click="clearCurrentAnalysis" 
                    class="px-3 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                    <x-lucide-upload-cloud class="w-4 h-4 text-zinc-500" />
                    <span>{{ __('Subir Otro CSV') }}</span>
                </button>
            @endif

            <button 
                wire:click="setTab('history')" 
                class="px-3.5 py-2 {{ $activeTab === 'history' ? 'bg-stone-900 text-white' : 'bg-white border border-stone-200 text-zinc-700 hover:bg-stone-50' }} text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                <x-lucide-history class="w-4 h-4 {{ $activeTab === 'history' ? 'text-amber-400' : 'text-zinc-500' }}" />
                <span>{{ __('Historial & Deshacer') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between shrink-0 shadow-2xs">
            <span class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ $successMessage }}</span>
            </span>
            <button wire:click="$set('successMessage', null)" class="text-emerald-600 hover:text-emerald-800 cursor-pointer">
                <x-lucide-x class="w-4 h-4" />
            </button>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-xs font-medium flex items-center justify-between shrink-0 shadow-2xs">
            <span class="flex items-center gap-2">
                <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" />
                <span>{{ $errorMessage }}</span>
            </span>
            <button wire:click="$set('errorMessage', null)" class="text-red-600 hover:text-red-800 cursor-pointer">
                <x-lucide-x class="w-4 h-4" />
            </button>
        </div>
    @endif

    <!-- Upload Zone (when no active analysis or requested) -->
    @if(!$hasAnalysis && $activeTab !== 'history')
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-8 shadow-2xs flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 mb-4">
                <x-lucide-upload-cloud class="w-8 h-8" />
            </div>

            <h3 class="text-lg font-bold text-zinc-900 mb-1">{{ __('Cargar archivo CSV de órdenes') }}</h3>
            <p class="text-xs text-zinc-500 max-w-md mb-6">
                {{ __('Selecciona o arrastra el archivo CSV con las órdenes a reconciliar. Los encabezados se normalizarán y los nombres en base de datos nunca serán modificados.') }}
            </p>

            <div class="w-full max-w-md">
                <label class="relative flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-stone-300 hover:border-amber-500 rounded-2xl cursor-pointer bg-stone-50/50 hover:bg-stone-50 transition p-4 group">
                    <div class="flex flex-col items-center justify-center pt-2 pb-3">
                        <x-lucide-file-text class="w-7 h-7 text-stone-400 group-hover:text-amber-500 transition mb-2" />
                        <p class="text-xs font-semibold text-zinc-700">
                            {{ __('Haz clic para seleccionar o arrastra tu archivo CSV aquí') }}
                        </p>
                        <p class="text-[11px] text-zinc-400 mt-1">Archivos .csv de hasta 30MB</p>
                    </div>
                    <input type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain" class="hidden" />
                </label>

                <!-- Loading State while file uploads -->
                <div wire:loading wire:target="csvFile" class="mt-4 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-center justify-center gap-2">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin text-amber-600" />
                    <span>{{ __('Subiendo y analizando datos del CSV, por favor espera...') }}</span>
                </div>
            </div>

            <div class="mt-8 border-t border-stone-100 pt-6 max-w-lg text-left w-full space-y-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Garantías del Proceso') }}</span>
                <div class="flex items-start gap-2 text-xs text-zinc-600">
                    <x-lucide-shield-check class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <span><strong>100% Silencioso (Zero-Automation):</strong> Sin alertas por email, sin notificaciones internas y sin disparar llamadas salientes a Trello.</span>
                </div>
                <div class="flex items-start gap-2 text-xs text-zinc-600">
                    <x-lucide-database class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                    <span><strong>Snapshot Automático:</strong> Se genera un respaldo de base de datos antes de cada aprobación masiva con capacidad de restaurar en 1 clic.</span>
                </div>
                <div class="flex items-start gap-2 text-xs text-zinc-600">
                    <x-lucide-lock class="w-4 h-4 text-stone-500 shrink-0 mt-0.5" />
                    <span><strong>Inmutabilidad:</strong> Los nombres de empresa y tarea en la base de datos se conservan íntegros.</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Active Report Dashboard -->
    @if($hasAnalysis && $activeTab !== 'history')
        <!-- Top Metrics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 shrink-0">
            <!-- Total Rows -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500">{{ __('Total en CSV') }}</span>
                    <x-lucide-file-text class="w-4 h-4 text-zinc-400" />
                </div>
                <div class="text-2xl font-extrabold text-zinc-900 mt-1">
                    {{ number_format($meta['total_rows'] ?? 0) }}
                </div>
                <div class="text-[11px] text-zinc-400 truncate mt-0.5">
                    {{ $meta['file_name'] ?? 'orders.csv' }}
                </div>
            </div>

            <!-- Full Match -->
            <div 
                wire:click="setTab('full_match')" 
                class="bg-white border {{ $activeTab === 'full_match' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-[#e9e9e7]' }} rounded-2xl p-4 shadow-2xs cursor-pointer hover:border-emerald-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ __('1. Full Match') }}
                    </span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-md">Batch</span>
                </div>
                <div class="text-2xl font-extrabold text-emerald-700 mt-1">
                    {{ $meta['full_match_count'] ?? 0 }}
                </div>
                <div class="text-[11px] text-emerald-600/80 mt-0.5">
                    {{ __('WO exacto + Similitud ≥ 75%') }}
                </div>
            </div>

            <!-- Partial Match -->
            <div 
                wire:click="setTab('partial_match')" 
                class="bg-white border {{ $activeTab === 'partial_match' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-[#e9e9e7]' }} rounded-2xl p-4 shadow-2xs cursor-pointer hover:border-amber-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        {{ __('2. Partial Match') }}
                    </span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 bg-amber-50 text-amber-700 rounded-md">Revisar</span>
                </div>
                <div class="text-2xl font-extrabold text-amber-700 mt-1">
                    {{ $meta['partial_match_count'] ?? 0 }}
                </div>
                <div class="text-[11px] text-amber-600/80 mt-0.5">
                    {{ __('Divergencia o match difuso') }}
                </div>
            </div>

            <!-- Not Enough Match -->
            <div 
                wire:click="setTab('unmatched')" 
                class="bg-white border {{ $activeTab === 'unmatched' ? 'border-slate-500 ring-2 ring-slate-500/10' : 'border-[#e9e9e7]' }} rounded-2xl p-4 shadow-2xs cursor-pointer hover:border-slate-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        {{ __('3. Sin Coincidencia') }}
                    </span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded-md">Trello Link</span>
                </div>
                <div class="text-2xl font-extrabold text-slate-700 mt-1">
                    {{ $meta['unmatched_count'] ?? 0 }}
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    {{ __('Conectar tarjeta de Trello') }}
                </div>
            </div>
        </div>

        <!-- Section Navigation Tabs & Action Bar -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-3 shadow-2xs flex flex-wrap items-center justify-between gap-3 shrink-0">
            <!-- Tabs -->
            <div class="flex items-center gap-1.5 bg-stone-100/80 p-1 rounded-xl">
                <button 
                    wire:click="setTab('full_match')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'full_match' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600" />
                    <span>{{ __('Full Match') }}</span>
                    <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $activeTab === 'full_match' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-zinc-600' }}">
                        {{ $meta['full_match_count'] ?? 0 }}
                    </span>
                </button>

                <button 
                    wire:click="setTab('partial_match')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'partial_match' ? 'bg-white text-amber-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-alert-triangle class="w-3.5 h-3.5 text-amber-500" />
                    <span>{{ __('Partial Match') }}</span>
                    <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $activeTab === 'partial_match' ? 'bg-amber-100 text-amber-800' : 'bg-stone-200 text-zinc-600' }}">
                        {{ $meta['partial_match_count'] ?? 0 }}
                    </span>
                </button>

                <button 
                    wire:click="setTab('unmatched')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'unmatched' ? 'bg-white text-slate-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-help-circle class="w-3.5 h-3.5 text-slate-500" />
                    <span>{{ __('Sin Coincidencia (Trello)') }}</span>
                    <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $activeTab === 'unmatched' ? 'bg-slate-200 text-slate-800' : 'bg-stone-200 text-zinc-600' }}">
                        {{ $meta['unmatched_count'] ?? 0 }}
                    </span>
                </button>
            </div>

            <!-- Search & Actions -->
            <div class="flex items-center gap-3">
                <div class="relative w-56 sm:w-64">
                    <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input 
                    wire:model.live.debounce.200ms="search" 
                        type="text" 
                        placeholder="{{ __('Buscar por WO, empresa o tarea...') }}" 
                        class="w-full pl-8 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-stone-400 transition" />
                </div>

                <!-- Tab-Specific Main Actions -->
                @if($activeTab === 'full_match' && ($meta['full_match_count'] ?? 0) > 0)
                    <button 
                        wire:click="openBatchConfirm('all_full')" 
                        class="px-4 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-zap class="w-3.5 h-3.5" />
                        <span>{{ __('⚡ Aprobar Todas en Lote') }} ({{ $meta['full_match_count'] ?? 0 }})</span>
                    </button>
                @endif

                @if($activeTab === 'partial_match' && count($selectedPartial) > 0)
                    <button 
                        wire:click="openBatchConfirm('selected_partial')" 
                        class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-check-check class="w-3.5 h-3.5" />
                        <span>{{ __('Aprobar Seleccionadas') }} ({{ count($selectedPartial) }})</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: FULL MATCH                          -->
        <!-- ========================================== -->
        @if($activeTab === 'full_match')
            <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden">
                <!-- Toolbar -->
                <div class="px-5 py-3 bg-stone-50/70 border-b border-[#e9e9e7] flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-zinc-700">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAllFull" 
                                {{ $selectAllFull ? 'checked' : '' }} 
                                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer" />
                            <span>{{ __('Seleccionar todo') }} ({{ $meta['full_match_count'] ?? 0 }})</span>
                        </label>

                        @if(count($selectedFull) > 0 && count($selectedFull) < ($meta['full_match_count'] ?? 0))
                            <button 
                                wire:click="openBatchConfirm('selected_full')" 
                                class="px-3 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-900 font-semibold rounded-lg text-xs transition cursor-pointer">
                                {{ __('Aprobar seleccionadas') }} ({{ count($selectedFull) }})
                            </button>
                        @endif
                    </div>

                    <div class="text-[11px] text-zinc-500 font-medium">
                        {{ __('Mostrando :count de :total en lista', ['count' => count($paginatedRows), 'total' => $totalCount]) }}
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if(($meta['total_rows'] ?? 0) > 0 && ($meta['full_match_count'] ?? 0) === 0)
                            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                <x-lucide-check-check class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-sm">{{ __('¡Todas las órdenes Full Match ya fueron procesadas!') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('Ya no quedan órdenes pendientes con 100% de coincidencia en este lote. Tienes :partial coincidencias parciales y :unmatched órdenes listas para revisar en las otras pestañas.', [
                                        'partial' => $meta['partial_match_count'] ?? 0,
                                        'unmatched' => $meta['unmatched_count'] ?? 0,
                                    ]) }}
                                </p>
                            </div>
                            <div class="flex items-center justify-center gap-2 pt-2">
                                @if(($meta['partial_match_count'] ?? 0) > 0)
                                    <button 
                                        type="button" 
                                        wire:click="setTab('partial_match')" 
                                        class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl text-xs transition cursor-pointer inline-flex items-center gap-1.5 shadow-2xs">
                                        <x-lucide-alert-triangle class="w-3.5 h-3.5" />
                                        <span>{{ __('Ir a Coincidencias Parciales (:count)', ['count' => $meta['partial_match_count']]) }}</span>
                                    </button>
                                @endif
                                @if(($meta['unmatched_count'] ?? 0) > 0)
                                    <button 
                                        type="button" 
                                        wire:click="setTab('unmatched')" 
                                        class="px-3.5 py-2 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition cursor-pointer inline-flex items-center gap-1.5 shadow-2xs">
                                        <x-lucide-help-circle class="w-3.5 h-3.5" />
                                        <span>{{ __('Ir a Sin Coincidencia / Trello (:count)', ['count' => $meta['unmatched_count']]) }}</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            <x-lucide-check-check class="w-8 h-8 mx-auto text-emerald-500 mb-2 opacity-60" />
                            <p>{{ __('No hay órdenes pendientes en este grupo o no coinciden con la búsqueda.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-stone-50/70 border-b border-stone-200">
                                <tr class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider">
                                    <th class="w-10 px-4 py-3 text-center"></th>
                                    <th class="px-4 py-3">{{ __('WO') }}</th>
                                    <th class="px-4 py-3">{{ __('Empresa (BD)') }}</th>
                                    <th class="px-4 py-3">{{ __('Tarea (BD)') }}</th>
                                    <th class="px-4 py-3">{{ __('Cambios Propuestos del CSV') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Similitud') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach($paginatedRows as $row)
                                    <tr class="hover:bg-stone-50/60 transition group">
                                        <td class="px-4 py-3 text-center">
                                            <input 
                                                type="checkbox" 
                                                wire:model.live="selectedFull" 
                                                value="{{ $row['row_id'] }}" 
                                                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer" />
                                        </td>
                                        <td class="px-4 py-3 font-mono font-bold text-zinc-900 whitespace-nowrap">
                                            {{ $row['wo_number'] }}
                                        </td>
                                        <td class="px-4 py-3 text-zinc-800 max-w-[240px]">
                                            <div class="font-semibold truncate" title="{{ $row['db_company'] }}">
                                                {{ $row['db_company'] }}
                                            </div>
                                            <!-- Detected Entities & Client Mapping -->
                                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                                @if(!empty($row['resolved_client_name']))
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Cliente Oficial Mapeado') }}">
                                                        <x-lucide-building-2 class="w-2.5 h-2.5 text-blue-500" />
                                                        <span>{{ $row['resolved_client_name'] }}</span>
                                                        @if(!empty($row['typo_detected']))
                                                            <span class="text-blue-500 font-normal">({{ __('typo') }})</span>
                                                        @endif
                                                    </span>
                                                @elseif(!empty($row['clean_company']))
                                                    <button 
                                                        type="button" 
                                                        wire:click="quickCreateClient('{{ $row['row_id'] }}')" 
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                        title="{{ __('Registrar en Catálogo de Clientes') }}">
                                                        <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                        <span>{{ __('+ Catálogo') }}</span>
                                                    </button>
                                                @endif

                                                @if(!empty($row['extracted_contact']))
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                        <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                        <span>{{ $row['extracted_contact'] }}</span>
                                                    </span>
                                                @endif

                                                @if(!empty($row['extracted_location']))
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Locación / Sucursal') }}">
                                                        <x-lucide-map-pin class="w-2.5 h-2.5 text-zinc-400" />
                                                        <span>{{ $row['extracted_location'] }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-600 max-w-[220px]">
                                            <div class="truncate" title="{{ $row['db_task'] ?? '' }}">
                                                {{ $row['db_task'] ?? '' }}
                                            </div>
                                            @if(!empty($row['clean_task']) && $row['clean_task'] !== ($row['db_task'] ?? ''))
                                                <div class="text-[10px] text-zinc-400 italic truncate mt-0.5" title="Tarea limpia en CSV: {{ $row['clean_task'] }}">
                                                    CSV: {{ $row['clean_task'] }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if(empty($row['diffs']))
                                                <span class="text-zinc-400 text-[11px] italic">{{ __('Sin cambios pendientes (idéntica a BD)') }}</span>
                                            @else
                                                <div class="flex flex-wrap gap-1.5 max-w-lg">
                                                    @foreach($row['diffs'] as $key => $diff)
                                                        @if(!empty($diff['is_smart_merge']))
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-purple-50 text-purple-900 border border-purple-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }} (Fusión Inteligente)">
                                                                <strong class="font-bold text-purple-700">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[150px] font-semibold">{{ $diff['proposed'] }}</span>
                                                                <span class="text-[8.5px] font-extrabold uppercase bg-purple-200/80 text-purple-900 px-1 py-0.2 rounded">Fusión</span>
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }}">
                                                                <strong class="font-bold">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[140px]">{{ $diff['proposed'] }}</span>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">
                                                <x-lucide-check-circle-2 class="w-3 h-3 text-emerald-600" />
                                                <span>{{ __('WO Exacto') }}</span>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 2: PARTIAL MATCH                       -->
        <!-- ========================================== -->
        @if($activeTab === 'partial_match')
            <div class="space-y-4">
                <div class="bg-amber-50/60 border border-amber-200/80 rounded-2xl p-4 text-xs text-amber-900 flex items-start gap-3">
                    <x-lucide-info class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold">{{ __('¿Por qué son coincidencias parciales?') }}</span>
                        <p class="mt-0.5 text-amber-800/90 text-[11px]">
                            {{ __('Estas órdenes coinciden en el número de WO pero tienen diferencias en nombres de empresa o tarea, o bien no traían WO en el CSV pero coinciden cercanamente con órdenes existentes. Revisa lado a lado y aprueba sólo las que confirmes correctas.') }}
                        </p>
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if(($meta['total_rows'] ?? 0) > 0 && ($meta['partial_match_count'] ?? 0) === 0)
                            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                                <x-lucide-check-check class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-sm">{{ __('¡No hay coincidencias parciales pendientes!') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('Todas las coincidencias parciales han sido procesadas o descartadas.') }}
                                </p>
                            </div>
                            @if(($meta['unmatched_count'] ?? 0) > 0)
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="setTab('unmatched')" 
                                        class="px-3.5 py-2 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition cursor-pointer inline-flex items-center gap-1.5 shadow-2xs">
                                        <x-lucide-help-circle class="w-3.5 h-3.5" />
                                        <span>{{ __('Ir a Sin Coincidencia / Trello (:count)', ['count' => $meta['unmatched_count']]) }}</span>
                                    </button>
                                </div>
                            @endif
                        @else
                            <x-lucide-check-circle-2 class="w-8 h-8 mx-auto text-amber-500 mb-2 opacity-60" />
                            <p>{{ __('No hay coincidencias parciales pendientes de revisión.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($paginatedRows as $row)
                            <div 
                                wire:key="partial-card-{{ $row['row_id'] }}"
                                class="bg-white border {{ !empty($activeReassignRows[$row['row_id']]) ? 'border-amber-400 ring-2 ring-amber-400/20' : 'border-[#e9e9e7] hover:border-amber-300' }} rounded-2xl p-4 shadow-2xs transition flex flex-col gap-4">
                                
                                <div class="flex flex-col md:flex-row gap-4 justify-between items-start">
                                    <!-- Checkbox & Info Left -->
                                    <div class="flex items-start gap-3 min-w-0 flex-1">
                                        <input 
                                            type="checkbox" 
                                            wire:model.live="selectedPartial" 
                                            value="{{ $row['row_id'] }}" 
                                            class="rounded border-stone-300 text-amber-600 focus:ring-amber-500 w-4 h-4 mt-1 cursor-pointer shrink-0" />

                                        <div class="space-y-2 min-w-0 flex-1">
                                            <!-- Reason badge & WO -->
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono font-bold text-xs bg-stone-100 text-zinc-900 px-2 py-0.5 rounded-md">
                                                    {{ $row['wo_number'] }}
                                                </span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ ($row['match_type'] ?? '') === 'wo_conflict' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $row['similarity'] }}% {{ __('Similitud') }}
                                                </span>
                                                <span class="text-[11px] text-zinc-500 font-medium">
                                                    {{ $row['reason'] }}
                                                </span>
                                            </div>

                                            <!-- Side-by-side comparison -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-stone-50/70 p-3 rounded-xl border border-stone-200/60">
                                                <!-- DB Current -->
                                                <div class="space-y-1">
                                                    <span class="text-[10px] font-bold uppercase text-zinc-400 block tracking-wider">{{ __('Base de Datos Actual') }}</span>
                                                    <p class="font-bold text-zinc-900 truncate" title="{{ $row['db_company'] ?? '' }}">{{ $row['db_company'] ?? '' }}</p>
                                                    <p class="text-zinc-600 text-[11px] line-clamp-2" title="{{ $row['db_task'] ?? '' }}">{{ $row['db_task'] ?? '' }}</p>
                                                </div>

                                                <!-- CSV Proposal -->
                                                <div class="space-y-1 border-t sm:border-t-0 sm:border-l border-stone-200/80 pt-2 sm:pt-0 sm:pl-3">
                                                    <span class="text-[10px] font-bold uppercase text-amber-600 block tracking-wider">{{ __('Propuesta en CSV') }}</span>
                                                    <p class="font-bold text-amber-950 truncate" title="{{ $row['csv_company'] ?? '' }}">{{ $row['csv_company'] ?? '' }}</p>
                                                    <p class="text-amber-900/80 text-[11px] line-clamp-2" title="{{ $row['csv_task'] ?? '' }}">{{ $row['csv_task'] ?? '' }}</p>
                                                    @if(!empty($row['clean_task']) && $row['clean_task'] !== ($row['csv_task'] ?? ''))
                                                        <p class="text-[10px] text-zinc-400 italic truncate" title="Tarea limpia: {{ $row['clean_task'] }}">
                                                            Limpia: {{ $row['clean_task'] }}
                                                        </p>
                                                    @endif

                                                    <!-- Detected Entities & Client Mapping -->
                                                    <div class="flex flex-wrap items-center gap-1 pt-1">
                                                        @if(!empty($row['resolved_client_name']))
                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Cliente Oficial Mapeado') }}">
                                                                <x-lucide-building-2 class="w-2.5 h-2.5 text-blue-500" />
                                                                <span>{{ $row['resolved_client_name'] }}</span>
                                                                @if(!empty($row['typo_detected']))
                                                                    <span class="text-blue-500 font-normal">({{ __('typo') }})</span>
                                                                @endif
                                                            </span>
                                                        @elseif(!empty($row['clean_company']))
                                                            <button 
                                                                type="button" 
                                                                wire:click="quickCreateClient('{{ $row['row_id'] }}')" 
                                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                                title="{{ __('Registrar en Catálogo de Clientes') }}">
                                                                <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                                <span>{{ __('+ Catálogo') }}</span>
                                                            </button>
                                                        @endif

                                                        @if(!empty($row['extracted_contact']))
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                                <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                                <span>{{ $row['extracted_contact'] }}</span>
                                                            </span>
                                                        @endif

                                                        @if(!empty($row['extracted_location']))
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Locación / Sucursal') }}">
                                                                <x-lucide-map-pin class="w-2.5 h-2.5 text-zinc-400" />
                                                                <span>{{ $row['extracted_location'] }}</span>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Proposed diffs badges -->
                                            @if(!empty($row['diffs']))
                                                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">{{ __('Actualizaciones:') }}</span>
                                                    @foreach($row['diffs'] as $diff)
                                                        @if(!empty($diff['is_smart_merge']))
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-purple-50 text-purple-900 border border-purple-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }} (Fusión Inteligente)">
                                                                <strong class="font-bold text-purple-700">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[140px] font-semibold">{{ $diff['proposed'] }}</span>
                                                                <span class="text-[8.5px] font-extrabold uppercase bg-purple-200/80 text-purple-900 px-1 py-0.2 rounded">Fusión</span>
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-stone-100 text-zinc-800 border border-stone-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }}">
                                                                <strong>{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[120px]">{{ $diff['proposed'] }}</span>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Actions Right -->
                                    <div class="flex sm:flex-col items-stretch sm:items-end gap-2 shrink-0 self-end sm:self-center w-full sm:w-auto">
                                        <!-- Primary Action: Mezclar -->
                                        <button 
                                            wire:click="approveSinglePartial('{{ $row['row_id'] }}')" 
                                            wire:loading.attr="disabled"
                                            wire:target="approveSinglePartial('{{ $row['row_id'] }}')"
                                            title="{{ __('Son la misma orden: combina información del CSV con el registro en BD y mantiene el Trello ID') }}"
                                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                            <x-lucide-git-merge class="w-3.5 h-3.5" wire:loading.remove wire:target="approveSinglePartial('{{ $row['row_id'] }}')" />
                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="approveSinglePartial('{{ $row['row_id'] }}')" />
                                            <span wire:loading.remove wire:target="approveSinglePartial('{{ $row['row_id'] }}')">{{ __('Mezclar (Misma orden)') }}</span>
                                            <span wire:loading wire:target="approveSinglePartial('{{ $row['row_id'] }}')">{{ __('Mezclando...') }}</span>
                                        </button>

                                        @if(($row['match_type'] ?? '') === 'wo_conflict')
                                            <!-- Action for WO conflict: Asignar / Cambiar WO en BD -->
                                            <button 
                                                type="button"
                                                wire:click="toggleReassignWo('{{ $row['row_id'] }}')" 
                                                title="{{ __('No son la misma: el CSV tiene el WO correcto. Asigna el nuevo WO a la orden de BD para crear la del CSV y resolver el conflicto') }}"
                                                class="px-3 py-1.5 {{ !empty($activeReassignRows[$row['row_id']]) ? 'bg-amber-600 text-white font-bold' : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-semibold' }} text-xs rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer">
                                                <x-lucide-hash class="w-3.5 h-3.5 {{ !empty($activeReassignRows[$row['row_id']]) ? 'text-white' : 'text-amber-600' }}" />
                                                <span>{{ !empty($activeReassignRows[$row['row_id']]) ? __('Cerrar Asignación') : __('Asignar WO a BD') }}</span>
                                            </button>
                                        @else
                                            <!-- Action for Secondary match: Descartar -->
                                            <button 
                                                wire:click="discardPartialMatch('{{ $row['row_id'] }}')" 
                                                wire:loading.attr="disabled"
                                                wire:target="discardPartialMatch('{{ $row['row_id'] }}')"
                                                title="{{ __('Descartar coincidencia y mover a Sin Coincidencia para asociar vía Trello') }}"
                                                class="px-3 py-1 bg-stone-100 hover:bg-stone-200 text-zinc-600 text-[11px] font-medium rounded-lg transition flex items-center justify-center gap-1 cursor-pointer">
                                                <x-lucide-x class="w-3 h-3 text-zinc-400" wire:loading.remove wire:target="discardPartialMatch('{{ $row['row_id'] }}')" />
                                                <x-lucide-loader-2 class="w-3 h-3 animate-spin" wire:loading wire:target="discardPartialMatch('{{ $row['row_id'] }}')" />
                                                <span>{{ __('No es la misma') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Inline WO Reassignment Panel for WO Conflicts -->
                                @if(!empty($activeReassignRows[$row['row_id']]))
                                    <div class="pt-3 border-t border-amber-200/80 bg-amber-50/70 -mx-4 -mb-4 p-4 rounded-b-2xl space-y-3">
                                        <div class="flex items-start gap-2 text-xs text-amber-950">
                                            <x-lucide-alert-circle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                            <div>
                                                <span class="font-bold">{{ __('Resolver Conflicto de Número de Orden:') }}</span>
                                                <p class="text-[11px] text-amber-900 mt-0.5">
                                                    {{ __('El número :wo pertenece a :csv_company (según el CSV). Asigna el nuevo número de WO a la orden que está en la base de datos (:db_company), o déjalo vacío para dejarla Sin WO. Al guardar, se creará la orden del CSV con :wo y el conflicto quedará solucionado.', [
                                                        'wo' => $row['wo_number'],
                                                        'csv_company' => $row['csv_company'],
                                                        'db_company' => $row['db_company'],
                                                    ]) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 pt-1">
                                            <div class="relative flex-1 min-w-[200px]">
                                                <input 
                                                    type="text" 
                                                    wire:model="reassignWoInputs.{{ $row['row_id'] }}"
                                                    placeholder="{{ __('Nuevo WO para :company (o vacío para Sin WO)', ['company' => $row['db_company']]) }}"
                                                    class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 font-mono text-zinc-900"
                                                />
                                            </div>
                                            <button 
                                                type="button" 
                                                wire:click="executeReassignWo('{{ $row['row_id'] }}')" 
                                                wire:loading.attr="disabled"
                                                wire:target="executeReassignWo('{{ $row['row_id'] }}')"
                                                class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-bold rounded-lg text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                                                <x-lucide-check-circle-2 class="w-3.5 h-3.5" wire:loading.remove wire:target="executeReassignWo('{{ $row['row_id'] }}')" />
                                                <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="executeReassignWo('{{ $row['row_id'] }}')" />
                                                <span wire:loading.remove wire:target="executeReassignWo('{{ $row['row_id'] }}')">{{ __('Guardar y Solucionar') }}</span>
                                                <span wire:loading wire:target="executeReassignWo('{{ $row['row_id'] }}')">{{ __('Guardando...') }}</span>
                                            </button>
                                            <button 
                                                type="button" 
                                                wire:click="toggleReassignWo('{{ $row['row_id'] }}')" 
                                                class="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-zinc-600 font-medium rounded-lg text-xs transition cursor-pointer">
                                                {{ __('Cancelar') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 3: NOT ENOUGH MATCH (TRELLO LINK)      -->
        <!-- ========================================== -->
        @if($activeTab === 'unmatched')
            <div class="space-y-4">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-2xs">
                    <div class="flex items-start gap-3">
                        <x-lucide-link class="w-4 h-4 text-slate-600 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">{{ __('Vincular órdenes huérfanas con tarjetas de Trello') }}</span>
                            <p class="mt-0.5 text-slate-600 text-[11px]">
                                {{ __('Estas órdenes del CSV no existen en la base de datos local. Puedes auto-buscar sus tarjetas de Trello por número de WO o pegar el enlace manual. El sistema reutilizará órdenes existentes si ya existen para evitar duplicados.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Batch Trello Actions -->
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button 
                            type="button" 
                            wire:click="autoSearchAllTrello" 
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-search class="w-3.5 h-3.5" wire:loading.remove wire:target="autoSearchAllTrello" />
                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="autoSearchAllTrello" />
                            <span>{{ __('Auto-buscar WOs en Trello') }}</span>
                        </button>

                        @if(!empty($trelloCardPreview))
                            <button 
                                type="button" 
                                wire:click="linkAllFoundTrello" 
                                wire:loading.attr="disabled"
                                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                                <x-lucide-check-circle-2 class="w-3.5 h-3.5" wire:loading.remove wire:target="linkAllFoundTrello" />
                                <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="linkAllFoundTrello" />
                                <span>{{ __('Vincular Todas (:count)', ['count' => count($trelloCardPreview)]) }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Filter Pills for Unmatched / Trello Matches -->
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-[#e9e9e7] p-2.5 rounded-2xl shadow-2xs">
                    <div class="flex items-center gap-1.5 bg-stone-100 p-1 rounded-xl text-xs">
                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('all')" 
                            class="px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'all' ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <x-lucide-list class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Todas') }}</span>
                            <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $unmatchedFilter === 'all' ? 'bg-stone-200 text-zinc-800 font-bold' : 'bg-stone-200/60 text-zinc-500' }}">
                                {{ $unmatchedCounts['total'] ?? $meta['unmatched_count'] ?? 0 }}
                            </span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('with_match')" 
                            class="px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'with_match' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ __('Con Tarjeta en Trello') }}</span>
                            <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $unmatchedFilter === 'with_match' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $unmatchedCounts['with_match'] ?? 0 }}
                            </span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('without_match')" 
                            class="px-3 py-1.5 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'without_match' ? 'bg-white text-slate-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                            <span>{{ __('Sin Tarjeta') }}</span>
                            <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $unmatchedFilter === 'without_match' ? 'bg-slate-200 text-slate-800 font-bold' : 'bg-stone-200/60 text-zinc-500' }}">
                                {{ $unmatchedCounts['without_match'] ?? 0 }}
                            </span>
                        </button>
                    </div>

                    <div class="text-[11px] text-zinc-500 font-medium px-2">
                        {{ __('Mostrando :count de :total en esta vista', ['count' => count($paginatedRows), 'total' => $totalCount]) }}
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if($unmatchedFilter === 'with_match')
                            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                                <x-lucide-search class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-sm">{{ __('No hay órdenes con tarjeta de Trello encontrada') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('Ejecuta "Auto-buscar WOs en Trello" arriba para localizar tarjetas automáticamente por número de WO, o pega los enlaces manualmente.') }}
                                </p>
                            </div>
                            <div class="pt-2">
                                <button 
                                    type="button" 
                                    wire:click="setUnmatchedFilter('all')" 
                                    class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 font-semibold rounded-xl text-xs transition cursor-pointer inline-flex items-center gap-1.5">
                                    <x-lucide-list class="w-3.5 h-3.5" />
                                    <span>{{ __('Ver todas las órdenes') }}</span>
                                </button>
                            </div>
                        @else
                            <x-lucide-check-circle-2 class="w-8 h-8 mx-auto text-emerald-500 mb-2 opacity-60" />
                            <p>{{ __('No hay órdenes sin coincidencia pendientes en esta vista.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($paginatedRows as $row)
                            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs space-y-3">
                                <!-- CSV Information -->
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            @if(!empty($row['raw_wo']))
                                                <span class="font-mono font-bold text-xs bg-stone-100 px-2 py-0.5 rounded text-zinc-900">
                                                    {{ $row['raw_wo'] }}
                                                </span>
                                            @endif
                                            <h4 class="font-bold text-sm text-zinc-900">{{ $row['csv_company'] ?: 'Empresa sin nombre' }}</h4>
                                        </div>
                                        <p class="text-xs text-zinc-600">{{ $row['csv_task'] ?: 'Tarea sin especificar' }}</p>
                                        @if(!empty($row['clean_task']) && $row['clean_task'] !== $row['csv_task'])
                                            <p class="text-[10px] text-zinc-400 italic" title="Tarea limpia: {{ $row['clean_task'] }}">
                                                Limpia: {{ $row['clean_task'] }}
                                            </p>
                                        @endif

                                        <!-- Detected Entities & Client Mapping -->
                                        <div class="flex flex-wrap items-center gap-1 pt-1">
                                            @if(!empty($row['resolved_client_name']))
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Cliente Oficial Mapeado') }}">
                                                    <x-lucide-building-2 class="w-2.5 h-2.5 text-blue-500" />
                                                    <span>{{ $row['resolved_client_name'] }}</span>
                                                    @if(!empty($row['typo_detected']))
                                                        <span class="text-blue-500 font-normal">({{ __('typo') }})</span>
                                                    @endif
                                                </span>
                                            @elseif(!empty($row['clean_company']))
                                                <button 
                                                    type="button" 
                                                    wire:click="quickCreateClient('{{ $row['row_id'] }}')" 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                    title="{{ __('Registrar en Catálogo de Clientes') }}">
                                                    <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                    <span>{{ __('+ Registrar Cliente') }}</span>
                                                </button>
                                            @endif

                                            @if(!empty($row['extracted_contact']))
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                    <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                    <span>{{ $row['extracted_contact'] }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($row['extracted_location']))
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Locación / Sucursal') }}">
                                                    <x-lucide-map-pin class="w-2.5 h-2.5 text-zinc-400" />
                                                    <span>{{ $row['extracted_location'] }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Notes & Attributes extracted -->
                                    <div class="flex flex-wrap gap-1.5 text-[11px] text-zinc-500">
                                        @if(!empty($row['parsed_data']['substatus']))
                                            <span class="px-2 py-0.5 bg-stone-100 rounded-md font-semibold text-zinc-700">
                                                {{ $row['parsed_data']['substatus'] }}
                                            </span>
                                        @endif
                                        @if(!empty($row['parsed_data']['delivery_due_date']))
                                            <span class="px-2 py-0.5 bg-stone-100 rounded-md">
                                                Entrega: {{ $row['parsed_data']['delivery_due_date'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if(!empty($row['parsed_data']['production_note']) || !empty($row['parsed_data']['delivery_note']))
                                    <div class="bg-stone-50 p-2.5 rounded-xl text-[11px] text-zinc-600 border border-stone-200/60 space-y-1">
                                        @if(!empty($row['parsed_data']['production_note']))
                                            <p><strong>Nota Producción:</strong> {{ $row['parsed_data']['production_note'] }}</p>
                                        @endif
                                        @if(!empty($row['parsed_data']['delivery_note']))
                                            <p><strong>Nota Entrega:</strong> {{ $row['parsed_data']['delivery_note'] }}</p>
                                        @endif
                                    </div>
                                @endif

                                <!-- Trello Link Box -->
                                <div class="border-t border-stone-100 pt-3 flex flex-col sm:flex-row items-start sm:items-center gap-2.5">
                                    <div class="relative flex-1 w-full">
                                        <x-lucide-trello class="w-4 h-4 text-blue-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                        <input 
                                            type="url" 
                                            wire:model="trelloInput.{{ $row['row_id'] }}" 
                                            placeholder="https://trello.com/c/5x8bZ9q1 o ID de tarjeta..." 
                                            class="w-full pl-9 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
                                    </div>

                                    @if(!empty($row['raw_wo']))
                                        <button 
                                            wire:click="searchSingleTrelloCard('{{ $row['row_id'] }}')" 
                                            wire:loading.attr="disabled"
                                            title="{{ __('Buscar en Trello por el WO :wo', ['wo' => $row['raw_wo']]) }}"
                                            class="px-3.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                            <x-lucide-search class="w-3.5 h-3.5 text-blue-600" />
                                            <span>{{ __('Buscar WO') }}</span>
                                        </button>
                                    @endif

                                    <button 
                                        wire:click="checkTrelloCard('{{ $row['row_id'] }}')" 
                                        wire:loading.attr="disabled"
                                        class="px-3.5 py-1.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                        <x-lucide-check class="w-3.5 h-3.5" />
                                        <span>{{ __('Verificar Enlace') }}</span>
                                    </button>
                                </div>

                                <!-- Verification Error -->
                                @if(!empty($trelloError[$row['row_id']]))
                                    <div class="text-[11px] text-red-600 bg-red-50 p-2 rounded-lg border border-red-200 flex items-center gap-1.5">
                                        <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                        <span>{{ $trelloError[$row['row_id']] }}</span>
                                    </div>
                                @endif

                                <!-- Verification Success Preview & Approval with Deduplication Info -->
                                @if(!empty($trelloCardPreview[$row['row_id']]))
                                    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                                        <div class="space-y-1.5 min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-[10px] font-bold uppercase text-blue-600 tracking-wider flex items-center gap-1">
                                                    <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                                                    {{ __('Tarjeta Encontrada') }}
                                                </span>

                                                @if(!empty($trelloCardPreview[$row['row_id']]['is_closed']))
                                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-extrabold bg-amber-100 text-amber-800 border border-amber-200" title="{{ __('Esta tarjeta está archivada en Trello') }}">
                                                        📦 {{ __('Archivada en Trello') }}
                                                    </span>
                                                @endif

                                                @if(($trelloCardPreview[$row['row_id']]['dedup_action'] ?? '') === 'reuse_existing')
                                                    <span class="px-2 py-0.5 rounded text-[9.5px] font-extrabold bg-blue-100 text-blue-800 border border-blue-300" title="{{ $trelloCardPreview[$row['row_id']]['dedup_reason'] ?? '' }}">
                                                        🔵 {{ __('Actualizará Orden Existente (#:id)', ['id' => $trelloCardPreview[$row['row_id']]['existing_order_id']]) }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[9.5px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300" title="{{ $trelloCardPreview[$row['row_id']]['dedup_reason'] ?? '' }}">
                                                        🟢 {{ __('Creará Orden Nueva en Backlog') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="font-bold text-blue-950 truncate max-w-md">
                                                <a href="{{ $trelloCardPreview[$row['row_id']]['url'] }}" target="_blank" class="hover:underline text-blue-900 inline-flex items-center gap-1">
                                                    <span>{{ $trelloCardPreview[$row['row_id']]['name'] }}</span>
                                                    <x-lucide-external-link class="w-3 h-3 text-blue-500 shrink-0" />
                                                </a>
                                            </p>
                                        </div>

                                        <button 
                                            wire:click="linkAndApproveTrello('{{ $row['row_id'] }}')" 
                                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                            <x-lucide-link class="w-3.5 h-3.5" />
                                            <span>
                                                @if(($trelloCardPreview[$row['row_id']]['dedup_action'] ?? '') === 'reuse_existing')
                                                    {{ __('Actualizar & Fusionar en BD') }}
                                                @else
                                                    {{ __('Crear en Backlog') }}
                                                @endif
                                            </span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Continuous List Footer & Infinite Scroll Controls -->
        @if($activeTab !== 'history' && $totalCount > 0)
            <div class="bg-white border border-[#e9e9e7] rounded-2xl px-5 py-3.5 text-xs text-zinc-600 shadow-2xs flex flex-col md:flex-row items-center justify-between gap-3">
                <!-- Status & Progress -->
                <div class="flex items-center gap-2.5">
                    @if($hasMore)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold text-[11px] bg-stone-100 text-zinc-700 border border-stone-200">
                            <x-lucide-list class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Mostrando :displayed de :total registros en lista', ['displayed' => $displayedCount, 'total' => $totalCount]) }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold text-[11px] bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600" />
                            <span>{{ __('Todos los :total registros visibles en lista', ['total' => $totalCount]) }}</span>
                        </span>
                    @endif
                </div>

                <!-- Load More Button & Scroll Sentinel -->
                @if($hasMore)
                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="loadMore" 
                            wire:loading.attr="disabled"
                            type="button" 
                            class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-arrow-down class="w-3.5 h-3.5" wire:loading.class="hidden" wire:target="loadMore" />
                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="loadMore" />
                            <span>{{ __('Cargar más (+50)') }}</span>
                        </button>

                        <!-- Invisible Sentinel for Smooth Infinite Scroll -->
                        <div x-intersect.threshold.05="$wire.loadMore()" class="w-1 h-1 opacity-0 pointer-events-none"></div>
                    </div>
                @endif

                <!-- Display Size Selector -->
                <div class="flex items-center gap-1.5 bg-stone-100/90 p-1 rounded-xl text-[11px]">
                    <span class="text-zinc-400 px-1 font-medium text-[10px] uppercase tracking-wider">{{ __('Ver:') }}</span>
                    <button 
                        type="button"
                        wire:click="setPerPage(50)" 
                        class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer {{ $perPage == 50 ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                        50
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage(100)" 
                        class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer {{ $perPage == 100 ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                        100
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage(250)" 
                        class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer {{ $perPage == 250 ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                        250
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage('all')" 
                        class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer {{ $perPage === 'all' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-zinc-600 hover:text-zinc-900' }}">
                        {{ __('Todas') }} ({{ $totalCount }})
                    </button>
                </div>
            </div>
        @endif
    @endif

    <!-- ========================================== -->
    <!-- TAB 4: MIGRATION HISTORY & ROLLBACK        -->
    <!-- ========================================== -->
    @if($activeTab === 'history')
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 pb-4">
                <div>
                    <h3 class="text-base font-bold text-zinc-900">{{ __('Historial de Conciliaciones & Migraciones') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Registro de todas las ejecuciones silenciosas. Puedes restaurar el respaldo de la última migración en cualquier momento.') }}</p>
                </div>

                @if(!empty($migrationHistory))
                    <button 
                        wire:click="$set('showRollbackModal', true)" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-undo-2 class="w-4 h-4" />
                        <span>{{ __('↩ Deshacer Última Migración') }}</span>
                    </button>
                @endif
            </div>

            @if(empty($migrationHistory))
                <div class="py-12 text-center text-zinc-400 text-xs">
                    <x-lucide-history class="w-8 h-8 mx-auto text-zinc-300 mb-2" />
                    {{ __('No hay registros históricos de migraciones aún.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-stone-50/70 border-b border-stone-200 text-[11px] font-bold text-zinc-500 uppercase tracking-wider">
                                <th class="px-4 py-3">{{ __('ID / Fecha') }}</th>
                                <th class="px-4 py-3">{{ __('Tipo') }}</th>
                                <th class="px-4 py-3">{{ __('Ejecutado Por') }}</th>
                                <th class="px-4 py-3">{{ __('Órdenes Aprobadas') }}</th>
                                <th class="px-4 py-3">{{ __('Respaldo Snapshot') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Estado') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach($migrationHistory as $item)
                                <tr class="hover:bg-stone-50/50 transition">
                                    <td class="px-4 py-3 font-mono font-medium text-zinc-900">
                                        <div>{{ $item['id'] }}</div>
                                        <div class="text-[11px] text-zinc-400 font-sans">{{ \Carbon\Carbon::parse($item['created_at'])->format('d M Y, h:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-stone-100 text-zinc-700 uppercase">
                                            {{ str_replace('_', ' ', $item['type']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-700 font-medium">
                                        {{ $item['user_name'] ?? 'Admin' }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-zinc-900">
                                        {{ $item['updated_count'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-zinc-500">
                                        {{ $item['backup_file'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if(($item['status'] ?? '') === 'applied')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                <x-lucide-check class="w-3 h-3" />
                                                {{ __('Aplicado') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                                <x-lucide-undo class="w-3 h-3" />
                                                {{ __('Revertido') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- Batch Confirmation Modal -->
    @if($showBatchConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200 space-y-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600">
                    <x-lucide-alert-triangle class="w-5 h-5" />
                </div>

                <div>
                    <h3 class="text-base font-extrabold text-zinc-900">{{ __('Confirmar Aprobación Silenciosa') }}</h3>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('Se actualizarán las órdenes seleccionadas en la base de datos de manera 100% silenciosa. Se creará automáticamente un respaldo snapshot de seguridad.') }}
                    </p>
                </div>

                <div class="bg-stone-50 rounded-xl p-3 text-xs text-zinc-600 border border-stone-200/60 space-y-1">
                    <p>• <strong>Zero-Automation:</strong> Ningún observador ni webhook notificará a los diseñadores.</p>
                    <p>• <strong>Respaldo:</strong> Podrás revertir este lote desde la pestaña de Historial si lo necesitas.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button 
                        wire:click="closeBatchConfirm" 
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="executeBatchApproval" 
                        class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-check class="w-4 h-4" />
                        <span>{{ __('Sí, Aplicar Actualización') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Rollback Confirmation Modal -->
    @if($showRollbackModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200 space-y-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-600">
                    <x-lucide-alert-octagon class="w-5 h-5" />
                </div>

                <div>
                    <h3 class="text-base font-extrabold text-zinc-900">{{ __('¿Deshacer la última migración?') }}</h3>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('Esta acción restaurará la base de datos completa al snapshot creado inmediatamente antes de ejecutar el último lote de migración.') }}
                    </p>
                </div>

                <div class="bg-red-50 text-red-800 rounded-xl p-3 text-xs border border-red-200">
                    {{ __('Cualquier cambio posterior a esa migración también volverá a ese estado. Se creará un respaldo de seguridad del estado actual antes de restaurar.') }}
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button 
                        wire:click="$set('showRollbackModal', false)" 
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        wire:click="triggerRollback" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-undo-2 class="w-4 h-4" />
                        <span>{{ __('Sí, Restaurar Respaldo') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
