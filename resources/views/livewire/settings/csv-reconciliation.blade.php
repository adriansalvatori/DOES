<div class="h-full w-full max-w-5xl flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-28">

    <x-ui.page-header :title="__('Reconciliation & CSV migration')" :subtitle="__('Compare your orders CSV with the current database, review matches across 3 levels and apply silent updates.')">
        <x-slot:actions>
            @if($hasAnalysis)
                <flux:button wire:click="clearCurrentAnalysis" variant="outline" size="sm" icon="arrow-up-tray">{{ __('Subir Otro CSV') }}</flux:button>
            @endif

            <flux:button wire:click="setTab('history')" :variant="$activeTab === 'history' ? 'primary' : 'outline'" size="sm" icon="clock">{{ __('Historial & Deshacer') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <!-- Flash Notifications -->
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-card text-xs font-medium flex items-center justify-between shrink-0">
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
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-card text-xs font-medium flex items-center justify-between shrink-0">
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
        <div class="bg-white border border-line rounded-card p-8 flex flex-col items-center text-center">
            <x-lucide-upload-cloud class="w-6 h-6 text-ink-3 mb-3" />

            <h3 class="text-section font-semibold text-ink mb-1">{{ __('Upload orders CSV file') }}</h3>
            <p class="text-xs text-zinc-500 max-w-md mb-6">
                {{ __('Select or drag the CSV file with the orders to reconcile. Headers will be normalized and names in the database will never be modified.') }}
            </p>

            <div class="w-full max-w-md">
                <label class="relative flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-stone-300 hover:border-amber-500 rounded-card cursor-pointer bg-stone-50/50 hover:bg-stone-50 transition p-4 group">
                    <div class="flex flex-col items-center justify-center pt-2 pb-3">
                        <x-lucide-file-text class="w-7 h-7 text-stone-400 group-hover:text-amber-500 transition mb-2" />
                        <p class="text-xs font-semibold text-zinc-700">
                            {{ __('Click to select or drag your CSV file here') }}
                        </p>
                        <p class="text-meta text-zinc-400 mt-1">Archivos .csv de hasta 30MB</p>
                    </div>
                    <input type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain" class="hidden" />
                </label>

                <!-- Loading State while file uploads -->
                <div wire:loading wire:target="csvFile" class="mt-4 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-card p-3 flex items-center justify-center gap-2">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin text-amber-600" />
                    <span>{{ __('Uploading and analyzing CSV data, please wait...') }}</span>
                </div>
            </div>

            <div class="mt-8 border-t border-stone-100 pt-6 max-w-lg text-left w-full space-y-2">
                <span class="text-meta font-semibold text-zinc-400 block mb-2">{{ __('Process guarantees') }}</span>
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
            <div class="bg-white border border-line rounded-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500">{{ __('Total en CSV') }}</span>
                    <x-lucide-file-text class="w-4 h-4 text-zinc-400" />
                </div>
                <div class="text-title font-semibold text-zinc-900 mt-1">
                    {{ number_format($meta['total_rows'] ?? 0) }}
                </div>
                <div class="text-meta text-zinc-400 truncate mt-0.5">
                    {{ $meta['file_name'] ?? 'orders.csv' }}
                </div>
            </div>

            <!-- Full Match -->
            <div 
                wire:click="setTab('full_match')" 
                class="bg-white border {{ $activeTab === 'full_match' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-line' }} rounded-card p-4 cursor-pointer hover:border-emerald-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ __('1. Full Match') }}
                    </span>
                    <span class="text-meta font-semibold px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-control">Batch</span>
                </div>
                <div class="text-title font-semibold text-emerald-700 mt-1">
                    {{ $meta['full_match_count'] ?? 0 }}
                </div>
                <div class="text-meta text-emerald-600/80 mt-0.5">
                    {{ __('WO exacto + Similitud ≥ 75%') }}
                </div>
            </div>

            <!-- Partial Match -->
            <div 
                wire:click="setTab('partial_match')" 
                class="bg-white border {{ $activeTab === 'partial_match' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-line' }} rounded-card p-4 cursor-pointer hover:border-amber-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        {{ __('2. Partial Match') }}
                    </span>
                    <span class="text-meta font-semibold px-1.5 py-0.5 bg-amber-50 text-amber-700 rounded-control">Revisar</span>
                </div>
                <div class="text-title font-semibold text-amber-700 mt-1">
                    {{ $meta['partial_match_count'] ?? 0 }}
                </div>
                <div class="text-meta text-amber-600/80 mt-0.5">
                    {{ __('Divergencia o match difuso') }}
                </div>
            </div>

            <!-- Not Enough Match -->
            <div 
                wire:click="setTab('unmatched')" 
                class="bg-white border {{ $activeTab === 'unmatched' ? 'border-slate-500 ring-2 ring-slate-500/10' : 'border-line' }} rounded-card p-4 cursor-pointer hover:border-slate-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        {{ __('3. No match') }}
                    </span>
                    <span class="text-meta font-semibold px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded-control">Trello Link</span>
                </div>
                <div class="text-title font-semibold text-slate-700 mt-1">
                    {{ $meta['unmatched_count'] ?? 0 }}
                </div>
                <div class="text-meta text-slate-500 mt-0.5">
                    {{ __('Connect Trello card') }}
                </div>
            </div>
        </div>

        <!-- Section Navigation Tabs & Action Bar -->
        <div class="bg-white border border-line rounded-card p-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <!-- Tabs -->
            <div class="flex items-center gap-1.5 bg-stone-100/80 p-1 rounded-card">
                <button 
                    wire:click="setTab('full_match')" 
                    class="px-3 py-1.5 rounded-control text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'full_match' ? 'bg-white text-emerald-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600" />
                    <span>{{ __('Full Match') }}</span>
                    <span class="px-1.5 py-0.2 text-meta rounded-full {{ $activeTab === 'full_match' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-zinc-600' }}">
                        {{ $meta['full_match_count'] ?? 0 }}
                    </span>
                </button>

                <button 
                    wire:click="setTab('partial_match')" 
                    class="px-3 py-1.5 rounded-control text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'partial_match' ? 'bg-white text-amber-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-alert-triangle class="w-3.5 h-3.5 text-amber-500" />
                    <span>{{ __('Partial Match') }}</span>
                    <span class="px-1.5 py-0.2 text-meta rounded-full {{ $activeTab === 'partial_match' ? 'bg-amber-100 text-amber-800' : 'bg-stone-200 text-zinc-600' }}">
                        {{ $meta['partial_match_count'] ?? 0 }}
                    </span>
                </button>

                <button 
                    wire:click="setTab('unmatched')" 
                    class="px-3 py-1.5 rounded-control text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'unmatched' ? 'bg-white text-slate-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                    <x-lucide-help-circle class="w-3.5 h-3.5 text-slate-500" />
                    <span>{{ __('No match (Trello)') }}</span>
                    <span class="px-1.5 py-0.2 text-meta rounded-full {{ $activeTab === 'unmatched' ? 'bg-slate-200 text-slate-800' : 'bg-stone-200 text-zinc-600' }}">
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
                        placeholder="{{ __('Search by WO, company or task...') }}" 
                        class="ui-input pl-8 pr-3" />
                </div>

                <!-- Tab-Specific Main Actions -->
                @if($activeTab === 'full_match' && ($meta['full_match_count'] ?? 0) > 0)
                    <button 
                        wire:click="openBatchConfirm('all_full')" 
                        class="px-4 py-1.5 bg-ink hover:bg-ink/90 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-zap class="w-3.5 h-3.5" />
                        <span>{{ __('⚡ Approve all in batch') }} ({{ $meta['full_match_count'] ?? 0 }})</span>
                    </button>
                @endif

                @if($activeTab === 'partial_match' && count($selectedPartial) > 0)
                    <button 
                        wire:click="openBatchConfirm('selected_partial')" 
                        class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-check-check class="w-3.5 h-3.5" />
                        <span>{{ __('Approve selected') }} ({{ count($selectedPartial) }})</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: FULL MATCH                          -->
        <!-- ========================================== -->
        @if($activeTab === 'full_match')
            <div class="bg-white border border-line rounded-card overflow-hidden">
                <!-- Toolbar -->
                <div class="px-5 py-3 bg-stone-50/70 border-b border-line flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-zinc-700">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAllFull" 
                                {{ $selectAllFull ? 'checked' : '' }} 
                                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer" />
                            <span>{{ __('Select all') }} ({{ $meta['full_match_count'] ?? 0 }})</span>
                        </label>

                        @if(count($selectedFull) > 0 && count($selectedFull) < ($meta['full_match_count'] ?? 0))
                            <button 
                                wire:click="openBatchConfirm('selected_full')" 
                                class="px-3 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-900 font-semibold rounded-control text-xs transition cursor-pointer">
                                {{ __('Approve selected') }} ({{ count($selectedFull) }})
                            </button>
                        @endif
                    </div>

                    <div class="text-meta text-zinc-500 font-medium">
                        {{ __('Showing :count of :total in list', ['count' => count($paginatedRows), 'total' => $totalCount]) }}
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if(($meta['total_rows'] ?? 0) > 0 && ($meta['full_match_count'] ?? 0) === 0)
                            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                <x-lucide-check-check class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-900 text-sm">{{ __('All Full Match orders have been processed!') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('No pending orders with a 100% match remain in this batch. You have :partial partial matches and :unmatched orders ready to review in the other tabs.', [
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
                                        class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-card text-xs transition cursor-pointer inline-flex items-center gap-1.5">
                                        <x-lucide-alert-triangle class="w-3.5 h-3.5" />
                                        <span>{{ __('Ir a Coincidencias Parciales (:count)', ['count' => $meta['partial_match_count']]) }}</span>
                                    </button>
                                @endif
                                @if(($meta['unmatched_count'] ?? 0) > 0)
                                    <button 
                                        type="button" 
                                        wire:click="setTab('unmatched')" 
                                        class="px-3.5 py-2 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-card text-xs transition cursor-pointer inline-flex items-center gap-1.5">
                                        <x-lucide-help-circle class="w-3.5 h-3.5" />
                                        <span>{{ __('Go to No match / Trello (:count)', ['count' => $meta['unmatched_count']]) }}</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            <x-lucide-check-check class="w-8 h-8 mx-auto text-emerald-500 mb-2 opacity-60" />
                            <p>{{ __('There are no pending orders in this group or none match the search.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-stone-50/70 border-b border-stone-200">
                                <tr class="text-meta font-semibold text-zinc-500  r">
                                    <th class="w-10 px-4 py-3 text-center"></th>
                                    <th class="px-4 py-3">{{ __('WO') }}</th>
                                    <th class="px-4 py-3">{{ __('Company (DB)') }}</th>
                                    <th class="px-4 py-3">{{ __('Task (DB)') }}</th>
                                    <th class="px-4 py-3">{{ __('Proposed changes from CSV') }}</th>
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
                                        <td class="px-4 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                                            {{ $row['wo_number'] }}
                                        </td>
                                        <td class="px-4 py-3 text-zinc-800 max-w-[240px]">
                                            <div class="font-semibold truncate" title="{{ $row['db_company'] }}">
                                                {{ $row['db_company'] }}
                                            </div>
                                            <!-- Detected Entities & Client Mapping -->
                                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                                @if(!empty($row['resolved_client_name']))
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-semibold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Mapped official client') }}">
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
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                        title="{{ __('Register in client catalog') }}">
                                                        <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                        <span>{{ __('+ Catalog') }}</span>
                                                    </button>
                                                @endif

                                                @if(!empty($row['extracted_contact']))
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                        <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                        <span>{{ $row['extracted_contact'] }}</span>
                                                    </span>
                                                @endif

                                                @if(!empty($row['extracted_location']))
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Location / Branch') }}">
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
                                                <div class="text-meta text-zinc-400 italic truncate mt-0.5" title="Tarea limpia en CSV: {{ $row['clean_task'] }}">
                                                    CSV: {{ $row['clean_task'] }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if(empty($row['diffs']))
                                                <span class="text-zinc-400 text-meta italic">{{ __('No pending changes (identical to DB)') }}</span>
                                            @else
                                                <div class="flex flex-wrap gap-1.5 max-w-lg">
                                                    @foreach($row['diffs'] as $key => $diff)
                                                        @if(!empty($diff['is_smart_merge']))
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-control text-meta font-medium bg-purple-50 text-purple-900 border border-purple-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }} (Fusión Inteligente)">
                                                                <strong class="font-semibold text-purple-700">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[150px] font-semibold">{{ $diff['proposed'] }}</span>
                                                                <span class="text-meta font-semibold bg-purple-200/80 text-purple-900 px-1 py-0.2 rounded">Fusión</span>
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-control text-meta font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }}">
                                                                <strong class="font-semibold">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[140px]">{{ $diff['proposed'] }}</span>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-meta font-semibold bg-emerald-100 text-emerald-800">
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
                <div class="bg-amber-50/60 border border-amber-200/80 rounded-card p-4 text-xs text-amber-900 flex items-start gap-3">
                    <x-lucide-info class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-semibold">{{ __('Why are these partial matches?') }}</span>
                        <p class="mt-0.5 text-amber-800/90 text-meta">
                            {{ __('These orders match on WO number but differ in company or task names, or had no WO in the CSV but closely match existing orders. Review them side by side and approve only the ones you confirm are correct.') }}
                        </p>
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="bg-white border border-line rounded-card p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if(($meta['total_rows'] ?? 0) > 0 && ($meta['partial_match_count'] ?? 0) === 0)
                            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                                <x-lucide-check-check class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-900 text-sm">{{ __('No pending partial matches!') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('All partial matches have been processed or dismissed.') }}
                                </p>
                            </div>
                            @if(($meta['unmatched_count'] ?? 0) > 0)
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="setTab('unmatched')" 
                                        class="px-3.5 py-2 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-card text-xs transition cursor-pointer inline-flex items-center gap-1.5">
                                        <x-lucide-help-circle class="w-3.5 h-3.5" />
                                        <span>{{ __('Go to No match / Trello (:count)', ['count' => $meta['unmatched_count']]) }}</span>
                                    </button>
                                </div>
                            @endif
                        @else
                            <x-lucide-check-circle-2 class="w-8 h-8 mx-auto text-amber-500 mb-2 opacity-60" />
                            <p>{{ __('No partial matches pending review.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($paginatedRows as $row)
                            <div 
                                wire:key="partial-card-{{ $row['row_id'] }}"
                                class="bg-white border {{ !empty($activeReassignRows[$row['row_id']]) ? 'border-amber-400 ring-2 ring-amber-400/20' : 'border-line hover:border-amber-300' }} rounded-card p-4 transition flex flex-col gap-4">
                                
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
                                                <span class="font-mono font-semibold text-xs bg-stone-100 text-zinc-900 px-2 py-0.5 rounded-control">
                                                    {{ $row['wo_number'] }}
                                                </span>
                                                <span class="text-meta font-semibold px-2 py-0.5 rounded-full {{ ($row['match_type'] ?? '') === 'wo_conflict' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $row['similarity'] }}% {{ __('Similitud') }}
                                                </span>
                                                <span class="text-meta text-zinc-500 font-medium">
                                                    {{ $row['reason'] }}
                                                </span>
                                            </div>

                                            <!-- Side-by-side comparison -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-stone-50/70 p-3 rounded-card border border-stone-200/60">
                                                <!-- DB Current -->
                                                <div class="space-y-1">
                                                    <span class="text-meta font-semibold text-zinc-400 block">{{ __('Current database') }}</span>
                                                    <p class="font-semibold text-zinc-900 truncate" title="{{ $row['db_company'] ?? '' }}">{{ $row['db_company'] ?? '' }}</p>
                                                    <p class="text-zinc-600 text-meta line-clamp-2" title="{{ $row['db_task'] ?? '' }}">{{ $row['db_task'] ?? '' }}</p>
                                                </div>

                                                <!-- CSV Proposal -->
                                                <div class="space-y-1 border-t sm:border-t-0 sm:border-l border-stone-200/80 pt-2 sm:pt-0 sm:pl-3">
                                                    <span class="text-meta font-semibold text-amber-600 block">{{ __('Propuesta en CSV') }}</span>
                                                    <p class="font-semibold text-amber-950 truncate" title="{{ $row['csv_company'] ?? '' }}">{{ $row['csv_company'] ?? '' }}</p>
                                                    <p class="text-amber-900/80 text-meta line-clamp-2" title="{{ $row['csv_task'] ?? '' }}">{{ $row['csv_task'] ?? '' }}</p>
                                                    @if(!empty($row['clean_task']) && $row['clean_task'] !== ($row['csv_task'] ?? ''))
                                                        <p class="text-meta text-zinc-400 italic truncate" title="Tarea limpia: {{ $row['clean_task'] }}">
                                                            Limpia: {{ $row['clean_task'] }}
                                                        </p>
                                                    @endif

                                                    <!-- Detected Entities & Client Mapping -->
                                                    <div class="flex flex-wrap items-center gap-1 pt-1">
                                                        @if(!empty($row['resolved_client_name']))
                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-semibold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Mapped official client') }}">
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
                                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                                title="{{ __('Register in client catalog') }}">
                                                                <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                                <span>{{ __('+ Catalog') }}</span>
                                                            </button>
                                                        @endif

                                                        @if(!empty($row['extracted_contact']))
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                                <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                                <span>{{ $row['extracted_contact'] }}</span>
                                                            </span>
                                                        @endif

                                                        @if(!empty($row['extracted_location']))
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Location / Branch') }}">
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
                                                    <span class="text-meta font-semibold text-zinc-400">{{ __('Actualizaciones:') }}</span>
                                                    @foreach($row['diffs'] as $diff)
                                                        @if(!empty($diff['is_smart_merge']))
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-control text-meta font-medium bg-purple-50 text-purple-900 border border-purple-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }} (Fusión Inteligente)">
                                                                <strong class="font-semibold text-purple-700">{{ $diff['field'] }}:</strong>
                                                                <span class="truncate max-w-[140px] font-semibold">{{ $diff['proposed'] }}</span>
                                                                <span class="text-meta font-semibold bg-purple-200/80 text-purple-900 px-1 py-0.2 rounded">Fusión</span>
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-control text-meta font-medium bg-stone-100 text-zinc-800 border border-stone-200" title="{{ $diff['field'] }}: {{ $diff['current'] }} ➔ {{ $diff['proposed'] }}">
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
                                            title="{{ __('They are the same order: merges CSV information with the DB record and keeps the Trello ID') }}"
                                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-semibold rounded-card transition flex items-center justify-center gap-1.5 cursor-pointer">
                                            <x-lucide-git-merge class="w-3.5 h-3.5" wire:loading.remove wire:target="approveSinglePartial('{{ $row['row_id'] }}')" />
                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="approveSinglePartial('{{ $row['row_id'] }}')" />
                                            <span wire:loading.remove wire:target="approveSinglePartial('{{ $row['row_id'] }}')">{{ __('Merge (same order)') }}</span>
                                            <span wire:loading wire:target="approveSinglePartial('{{ $row['row_id'] }}')">{{ __('Mezclando...') }}</span>
                                        </button>

                                        @if(($row['match_type'] ?? '') === 'wo_conflict')
                                            <!-- Action for WO conflict: Asignar / Cambiar WO en BD -->
                                            <button 
                                                type="button"
                                                wire:click="toggleReassignWo('{{ $row['row_id'] }}')" 
                                                title="{{ __('They are not the same: the CSV has the correct WO. Assign the new WO to the DB order to create the CSV one and resolve the conflict') }}"
                                                class="px-3 py-1.5 {{ !empty($activeReassignRows[$row['row_id']]) ? 'bg-amber-600 text-white font-semibold' : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-semibold' }} text-xs rounded-card transition flex items-center justify-center gap-1.5 cursor-pointer">
                                                <x-lucide-hash class="w-3.5 h-3.5 {{ !empty($activeReassignRows[$row['row_id']]) ? 'text-white' : 'text-amber-600' }}" />
                                                <span>{{ !empty($activeReassignRows[$row['row_id']]) ? __('Close assignment') : __('Asignar WO a BD') }}</span>
                                            </button>
                                        @else
                                            <!-- Action for Secondary match: Descartar -->
                                            <button 
                                                wire:click="discardPartialMatch('{{ $row['row_id'] }}')" 
                                                wire:loading.attr="disabled"
                                                wire:target="discardPartialMatch('{{ $row['row_id'] }}')"
                                                title="{{ __('Dismiss match and move to No match to link via Trello') }}"
                                                class="px-3 py-1 bg-stone-100 hover:bg-stone-200 text-zinc-600 text-meta font-medium rounded-control transition flex items-center justify-center gap-1 cursor-pointer">
                                                <x-lucide-x class="w-3 h-3 text-zinc-400" wire:loading.remove wire:target="discardPartialMatch('{{ $row['row_id'] }}')" />
                                                <x-lucide-loader-2 class="w-3 h-3 animate-spin" wire:loading wire:target="discardPartialMatch('{{ $row['row_id'] }}')" />
                                                <span>{{ __('Not the same') }}</span>
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
                                                <span class="font-semibold">{{ __('Resolve order number conflict:') }}</span>
                                                <p class="text-meta text-amber-900 mt-0.5">
                                                    {{ __('Number :wo belongs to :csv_company (per the CSV). Assign the new WO number to the order in the database (:db_company), or leave it empty to keep it without a WO. When you save, the CSV order will be created with :wo and the conflict will be resolved.', [
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
                                                    placeholder="{{ __('New WO for :company (or empty for no WO)', ['company' => $row['db_company']]) }}"
                                                    class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-control text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 font-mono text-zinc-900"
                                                />
                                            </div>
                                            <button 
                                                type="button" 
                                                wire:click="executeReassignWo('{{ $row['row_id'] }}')" 
                                                wire:loading.attr="disabled"
                                                wire:target="executeReassignWo('{{ $row['row_id'] }}')"
                                                class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-semibold rounded-control text-xs transition flex items-center gap-1.5 cursor-pointer">
                                                <x-lucide-check-circle-2 class="w-3.5 h-3.5" wire:loading.remove wire:target="executeReassignWo('{{ $row['row_id'] }}')" />
                                                <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="executeReassignWo('{{ $row['row_id'] }}')" />
                                                <span wire:loading.remove wire:target="executeReassignWo('{{ $row['row_id'] }}')">{{ __('Save and resolve') }}</span>
                                                <span wire:loading wire:target="executeReassignWo('{{ $row['row_id'] }}')">{{ __('Guardando...') }}</span>
                                            </button>
                                            <button 
                                                type="button" 
                                                wire:click="toggleReassignWo('{{ $row['row_id'] }}')" 
                                                class="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-zinc-600 font-medium rounded-control text-xs transition cursor-pointer">
                                                {{ __('Cancel') }}
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
                <div class="bg-slate-50 border border-slate-200 rounded-card p-4 text-xs text-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <x-lucide-link class="w-4 h-4 text-slate-600 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold">{{ __('Link orphan orders to Trello cards') }}</span>
                            <p class="mt-0.5 text-slate-600 text-meta">
                                {{ __('These CSV orders don\'t exist in the local database. You can auto-search for their Trello cards by WO number or paste the link manually. The system will reuse existing orders if they already exist to avoid duplicates.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Batch Trello Actions -->
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button 
                            type="button" 
                            wire:click="autoSearchAllTrello" 
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-search class="w-3.5 h-3.5" wire:loading.remove wire:target="autoSearchAllTrello" />
                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="autoSearchAllTrello" />
                            <span>{{ __('Auto-search WOs in Trello') }}</span>
                        </button>

                        @if(!empty($trelloCardPreview))
                            <button 
                                type="button" 
                                wire:click="linkAllFoundTrello" 
                                wire:loading.attr="disabled"
                                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                                <x-lucide-check-circle-2 class="w-3.5 h-3.5" wire:loading.remove wire:target="linkAllFoundTrello" />
                                <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="linkAllFoundTrello" />
                                <span>{{ __('Link all (:count)', ['count' => count($trelloCardPreview)]) }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Filter Pills for Unmatched / Trello Matches -->
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-line p-2.5 rounded-card">
                    <div class="flex items-center gap-1.5 bg-stone-100 p-1 rounded-card text-xs">
                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('all')" 
                            class="px-3 py-1.5 rounded-control font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'all' ? 'bg-white text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <x-lucide-list class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('All') }}</span>
                            <span class="px-1.5 py-0.2 text-meta rounded-full {{ $unmatchedFilter === 'all' ? 'bg-stone-200 text-zinc-800 font-semibold' : 'bg-stone-200/60 text-zinc-500' }}">
                                {{ $unmatchedCounts['total'] ?? $meta['unmatched_count'] ?? 0 }}
                            </span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('with_match')" 
                            class="px-3 py-1.5 rounded-control font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'with_match' ? 'bg-white text-emerald-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ __('With Trello card') }}</span>
                            <span class="px-1.5 py-0.2 text-meta rounded-full {{ $unmatchedFilter === 'with_match' ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $unmatchedCounts['with_match'] ?? 0 }}
                            </span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="setUnmatchedFilter('without_match')" 
                            class="px-3 py-1.5 rounded-control font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $unmatchedFilter === 'without_match' ? 'bg-white text-slate-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                            <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                            <span>{{ __('No card') }}</span>
                            <span class="px-1.5 py-0.2 text-meta rounded-full {{ $unmatchedFilter === 'without_match' ? 'bg-slate-200 text-slate-800 font-semibold' : 'bg-stone-200/60 text-zinc-500' }}">
                                {{ $unmatchedCounts['without_match'] ?? 0 }}
                            </span>
                        </button>
                    </div>

                    <div class="text-meta text-zinc-500 font-medium px-2">
                        {{ __('Showing :count of :total in this view', ['count' => count($paginatedRows), 'total' => $totalCount]) }}
                    </div>
                </div>

                @if(empty($paginatedRows))
                    <div class="bg-white border border-line rounded-card p-12 text-center text-zinc-500 text-xs space-y-3">
                        @if($unmatchedFilter === 'with_match')
                            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                                <x-lucide-search class="w-6 h-6" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-900 text-sm">{{ __('No orders with a Trello card found') }}</h4>
                                <p class="text-zinc-500 mt-1 max-w-md mx-auto text-xs">
                                    {{ __('Run "Auto-search WOs in Trello" above to find cards automatically by WO number, or paste the links manually.') }}
                                </p>
                            </div>
                            <div class="pt-2">
                                <button 
                                    type="button" 
                                    wire:click="setUnmatchedFilter('all')" 
                                    class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 font-semibold rounded-card text-xs transition cursor-pointer inline-flex items-center gap-1.5">
                                    <x-lucide-list class="w-3.5 h-3.5" />
                                    <span>{{ __('View all orders') }}</span>
                                </button>
                            </div>
                        @else
                            <x-lucide-check-circle-2 class="w-8 h-8 mx-auto text-emerald-500 mb-2 opacity-60" />
                            <p>{{ __('No unmatched orders pending in this view.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($paginatedRows as $row)
                            <div class="bg-white border border-line rounded-card p-4 space-y-3">
                                <!-- CSV Information -->
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            @if(!empty($row['raw_wo']))
                                                <span class="font-mono font-semibold text-xs bg-stone-100 px-2 py-0.5 rounded text-zinc-900">
                                                    {{ $row['raw_wo'] }}
                                                </span>
                                            @endif
                                            <h4 class="font-semibold text-sm text-zinc-900">{{ $row['csv_company'] ?: 'Empresa sin nombre' }}</h4>
                                        </div>
                                        <p class="text-xs text-zinc-600">{{ $row['csv_task'] ?: 'Tarea sin especificar' }}</p>
                                        @if(!empty($row['clean_task']) && $row['clean_task'] !== $row['csv_task'])
                                            <p class="text-meta text-zinc-400 italic" title="Tarea limpia: {{ $row['clean_task'] }}">
                                                Limpia: {{ $row['clean_task'] }}
                                            </p>
                                        @endif

                                        <!-- Detected Entities & Client Mapping -->
                                        <div class="flex flex-wrap items-center gap-1 pt-1">
                                            @if(!empty($row['resolved_client_name']))
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-semibold bg-blue-50 text-blue-700 border border-blue-200" title="{{ __('Mapped official client') }}">
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
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-zinc-500 border border-stone-200 transition cursor-pointer" 
                                                    title="{{ __('Register in client catalog') }}">
                                                    <x-lucide-user-plus class="w-2.5 h-2.5" />
                                                    <span>{{ __('+ Register client') }}</span>
                                                </button>
                                            @endif

                                            @if(!empty($row['extracted_contact']))
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Contacto / Responsable') }}">
                                                    <x-lucide-user class="w-2.5 h-2.5 text-zinc-400" />
                                                    <span>{{ $row['extracted_contact'] }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($row['extracted_location']))
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-meta font-medium bg-stone-100 text-zinc-600 border border-stone-200/80" title="{{ __('Location / Branch') }}">
                                                    <x-lucide-map-pin class="w-2.5 h-2.5 text-zinc-400" />
                                                    <span>{{ $row['extracted_location'] }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Notes & Attributes extracted -->
                                    <div class="flex flex-wrap gap-1.5 text-meta text-zinc-500">
                                        @if(!empty($row['parsed_data']['substatus']))
                                            <span class="px-2 py-0.5 bg-stone-100 rounded-control font-semibold text-zinc-700">
                                                {{ $row['parsed_data']['substatus'] }}
                                            </span>
                                        @endif
                                        @if(!empty($row['parsed_data']['delivery_due_date']))
                                            <span class="px-2 py-0.5 bg-stone-100 rounded-control">
                                                Entrega: {{ $row['parsed_data']['delivery_due_date'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if(!empty($row['parsed_data']['production_note']) || !empty($row['parsed_data']['delivery_note']))
                                    <div class="bg-stone-50 p-2.5 rounded-card text-meta text-zinc-600 border border-stone-200/60 space-y-1">
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
                                            class="ui-input pl-9 pr-3" />
                                    </div>

                                    @if(!empty($row['raw_wo']))
                                        <button 
                                            wire:click="searchSingleTrelloCard('{{ $row['row_id'] }}')" 
                                            wire:loading.attr="disabled"
                                            title="{{ __('Search Trello for WO :wo', ['wo' => $row['raw_wo']]) }}"
                                            class="px-3.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                            <x-lucide-search class="w-3.5 h-3.5 text-blue-600" />
                                            <span>{{ __('Search WO') }}</span>
                                        </button>
                                    @endif

                                    <button 
                                        wire:click="checkTrelloCard('{{ $row['row_id'] }}')" 
                                        wire:loading.attr="disabled"
                                        class="px-3.5 py-1.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                        <x-lucide-check class="w-3.5 h-3.5" />
                                        <span>{{ __('Verificar Enlace') }}</span>
                                    </button>
                                </div>

                                <!-- Verification Error -->
                                @if(!empty($trelloError[$row['row_id']]))
                                    <div class="text-meta text-red-600 bg-red-50 p-2 rounded-control border border-red-200 flex items-center gap-1.5">
                                        <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                        <span>{{ $trelloError[$row['row_id']] }}</span>
                                    </div>
                                @endif

                                <!-- Verification Success Preview & Approval with Deduplication Info -->
                                @if(!empty($trelloCardPreview[$row['row_id']]))
                                    <div class="bg-blue-50/70 border border-blue-200 rounded-card p-3.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                                        <div class="space-y-1.5 min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-meta font-semibold text-blue-600 flex items-center gap-1">
                                                    <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                                                    {{ __('Tarjeta Encontrada') }}
                                                </span>

                                                @if(!empty($trelloCardPreview[$row['row_id']]['is_closed']))
                                                    <span class="px-1.5 py-0.5 rounded text-meta font-semibold bg-amber-100 text-amber-800 border border-amber-200" title="{{ __('This card is archived in Trello') }}">
                                                        📦 {{ __('Archivada en Trello') }}
                                                    </span>
                                                @endif

                                                @if(($trelloCardPreview[$row['row_id']]['dedup_action'] ?? '') === 'reuse_existing')
                                                    <span class="px-2 py-0.5 rounded text-meta font-semibold bg-blue-100 text-blue-800 border border-blue-300" title="{{ $trelloCardPreview[$row['row_id']]['dedup_reason'] ?? '' }}">
                                                        🔵 {{ __('Will update existing order (#:id)', ['id' => $trelloCardPreview[$row['row_id']]['existing_order_id']]) }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-meta font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300" title="{{ $trelloCardPreview[$row['row_id']]['dedup_reason'] ?? '' }}">
                                                        🟢 {{ __('Will create new order in Backlog') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="font-semibold text-blue-950 truncate max-w-md">
                                                <a href="{{ $trelloCardPreview[$row['row_id']]['url'] }}" target="_blank" class="hover:underline text-blue-900 inline-flex items-center gap-1">
                                                    <span>{{ $trelloCardPreview[$row['row_id']]['name'] }}</span>
                                                    <x-lucide-external-link class="w-3 h-3 text-blue-500 shrink-0" />
                                                </a>
                                            </p>
                                        </div>

                                        <button 
                                            wire:click="linkAndApproveTrello('{{ $row['row_id'] }}')" 
                                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                            <x-lucide-link class="w-3.5 h-3.5" />
                                            <span>
                                                @if(($trelloCardPreview[$row['row_id']]['dedup_action'] ?? '') === 'reuse_existing')
                                                    {{ __('Actualizar & Fusionar en BD') }}
                                                @else
                                                    {{ __('Create in Backlog') }}
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
            <div class="bg-white border border-line rounded-card px-5 py-3.5 text-xs text-zinc-600 flex flex-col md:flex-row items-center justify-between gap-3">
                <!-- Status & Progress -->
                <div class="flex items-center gap-2.5">
                    @if($hasMore)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold text-meta bg-stone-100 text-zinc-700 border border-stone-200">
                            <x-lucide-list class="w-3.5 h-3.5 text-zinc-500" />
                            <span>{{ __('Showing :displayed of :total records in list', ['displayed' => $displayedCount, 'total' => $totalCount]) }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold text-meta bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600" />
                            <span>{{ __('All :total records visible in list', ['total' => $totalCount]) }}</span>
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
                            class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-arrow-down class="w-3.5 h-3.5" wire:loading.class="hidden" wire:target="loadMore" />
                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="loadMore" />
                            <span>{{ __('Load more (+50)') }}</span>
                        </button>

                        <!-- Invisible Sentinel for Smooth Infinite Scroll -->
                        <div x-intersect.threshold.05="$wire.loadMore()" class="w-1 h-1 opacity-0 pointer-events-none"></div>
                    </div>
                @endif

                <!-- Display Size Selector -->
                <div class="flex items-center gap-1.5 bg-stone-100/90 p-1 rounded-card text-meta">
                    <span class="text-zinc-400 px-1 font-medium text-meta">{{ __('Show:') }}</span>
                    <button 
                        type="button"
                        wire:click="setPerPage(50)" 
                        class="px-2.5 py-1 rounded-control font-semibold transition cursor-pointer {{ $perPage == 50 ? 'bg-white text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                        50
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage(100)" 
                        class="px-2.5 py-1 rounded-control font-semibold transition cursor-pointer {{ $perPage == 100 ? 'bg-white text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                        100
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage(250)" 
                        class="px-2.5 py-1 rounded-control font-semibold transition cursor-pointer {{ $perPage == 250 ? 'bg-white text-zinc-900' : 'text-zinc-600 hover:text-zinc-900' }}">
                        250
                    </button>
                    <button 
                        type="button"
                        wire:click="setPerPage('all')" 
                        class="px-2.5 py-1 rounded-control font-semibold transition cursor-pointer {{ $perPage === 'all' ? 'bg-white text-emerald-800' : 'text-zinc-600 hover:text-zinc-900' }}">
                        {{ __('All') }} ({{ $totalCount }})
                    </button>
                </div>
            </div>
        @endif
    @endif

    <!-- ========================================== -->
    <!-- TAB 4: MIGRATION HISTORY & ROLLBACK        -->
    <!-- ========================================== -->
    @if($activeTab === 'history')
        <div class="bg-white border border-line rounded-card p-6 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 pb-4">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900">{{ __('Reconciliation & migration history') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Log of all silent runs. You can restore the backup of the latest migration at any time.') }}</p>
                </div>

                @if(!empty($migrationHistory))
                    <button 
                        wire:click="$set('showRollbackModal', true)" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-undo-2 class="w-4 h-4" />
                        <span>{{ __('↩ Undo last migration') }}</span>
                    </button>
                @endif
            </div>

            @if(empty($migrationHistory))
                <div class="py-12 text-center text-zinc-400 text-xs">
                    <x-lucide-history class="w-8 h-8 mx-auto text-zinc-300 mb-2" />
                    {{ __('No historical migration records yet.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-stone-50/70 border-b border-stone-200 text-meta font-semibold text-zinc-500  r">
                                <th class="px-4 py-3">{{ __('ID / Date') }}</th>
                                <th class="px-4 py-3">{{ __('Type') }}</th>
                                <th class="px-4 py-3">{{ __('Run by') }}</th>
                                <th class="px-4 py-3">{{ __('Approved orders') }}</th>
                                <th class="px-4 py-3">{{ __('Respaldo Snapshot') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach($migrationHistory as $item)
                                <tr class="hover:bg-stone-50/50 transition">
                                    <td class="px-4 py-3 font-mono font-medium text-zinc-900">
                                        <div>{{ $item['id'] }}</div>
                                        <div class="text-meta text-zinc-400 font-sans">{{ \Carbon\Carbon::parse($item['created_at'])->format('d M Y, h:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-control text-meta font-semibold bg-stone-100 text-zinc-700 ">
                                            {{ str_replace('_', ' ', $item['type']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-700 font-medium">
                                        {{ $item['user_name'] ?? 'Admin' }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-zinc-900">
                                        {{ $item['updated_count'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-meta text-zinc-500">
                                        {{ $item['backup_file'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if(($item['status'] ?? '') === 'applied')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-meta font-semibold bg-emerald-100 text-emerald-800">
                                                <x-lucide-check class="w-3 h-3" />
                                                {{ __('Aplicado') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-meta font-semibold bg-amber-100 text-amber-800">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40">
            <div class="bg-surface rounded-panel max-w-md w-full p-5 shadow-panel border border-line space-y-4">
                <div>
                    <h3 class="text-section font-semibold text-ink">{{ __('Confirm silent approval') }}</h3>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('The selected orders will be updated in the database 100% silently. A safety snapshot backup will be created automatically.') }}
                    </p>
                </div>

                <div class="bg-stone-50 rounded-card p-3 text-xs text-zinc-600 border border-stone-200/60 space-y-1">
                    <p>• <strong>Zero-Automation:</strong> Ningún observador ni webhook notificará a los diseñadores.</p>
                    <p>• <strong>Respaldo:</strong> Podrás revertir este lote desde la pestaña de Historial si lo necesitas.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button 
                        wire:click="closeBatchConfirm" 
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold rounded-card transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="executeBatchApproval" 
                        class="px-4 py-2 bg-ink hover:bg-ink/90 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-check class="w-4 h-4" />
                        <span>{{ __('Yes, apply update') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Rollback Confirmation Modal -->
    @if($showRollbackModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40">
            <div class="bg-surface rounded-panel max-w-md w-full p-5 shadow-panel border border-line space-y-4">
                <div>
                    <h3 class="text-section font-semibold text-ink">{{ __('Undo the last migration?') }}</h3>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('This action will restore the entire database to the snapshot created right before the last migration batch was run.') }}
                    </p>
                </div>

                <div class="bg-red-50 text-red-800 rounded-card p-3 text-xs border border-red-200">
                    {{ __('Any changes made after that migration will also revert to that state. A safety backup of the current state will be created before restoring.') }}
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button 
                        wire:click="$set('showRollbackModal', false)" 
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold rounded-card transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="triggerRollback" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-card transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-undo-2 class="w-4 h-4" />
                        <span>{{ __('Yes, restore backup') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
