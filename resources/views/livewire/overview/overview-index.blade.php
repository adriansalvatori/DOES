<div 
    x-data="{
        colWidths: (() => {
            const defaults = {
                created_at: 6,
                prod_date: 6,
                wo: 6,
                client: 12,
                name: 14,
                designer: 7.5,
                prod_note: 12,
                invoice: 7,
                email_date: 6,
                installation: 6,
                check: 3.5,
                deliv_note: 7,
                substatus: 7
            };
            try {
                localStorage.removeItem('overview_col_widths');
                const saved = JSON.parse(localStorage.getItem('overview_col_widths_pct') || '{}');
                for (let k in saved) {
                    if (saved[k] > 40) return defaults;
                }
                return Object.assign(defaults, saved);
            } catch (err) {
                return defaults;
            }
        })(),
        resizingCol: null,
        startX: 0,
        startWidth: 0,
        initResize(e, colKey) {
            this.resizingCol = colKey;
            this.startX = e.pageX;
            const tableWidth = this.$refs.ordersTable ? this.$refs.ordersTable.getBoundingClientRect().width : (window.innerWidth - 300);
            this.startWidth = this.colWidths[colKey] || 7;
            let onMove = (mv) => {
                if (!this.resizingCol) return;
                let deltaPct = ((mv.pageX - this.startX) / tableWidth) * 100;
                let newPct = Math.max(2.5, this.startWidth + deltaPct);
                this.colWidths[this.resizingCol] = Math.round(newPct * 10) / 10;
                localStorage.setItem('overview_col_widths_pct', JSON.stringify(this.colWidths));
            };
            let onUp = () => {
                setTimeout(() => { this.resizingCol = null; }, 50);
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        }
    }"
    class="h-full w-full max-w-full overflow-y-auto space-y-4 pb-32 px-1">

    <!-- Top Summary Metrics Cards Bar (Full Screen Width) -->
    @island(name: 'overview-metrics')
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 w-full">
        <div class="bg-white p-3 rounded-xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block">{{ __('Workspace Activas') }}</span>
                <span class="text-xl font-extrabold text-stone-900">{{ $this->metrics['totalWorkspaceCount'] }}</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                <x-lucide-activity class="w-5 h-5" />
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block">{{ __('Backlog') }}</span>
                <span class="text-xl font-extrabold text-amber-600">{{ $this->metrics['totalBacklogCount'] }}</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                <x-lucide-box class="w-5 h-5" />
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block">{{ __('Sin WO') }}</span>
                <span class="text-xl font-extrabold {{ $this->metrics['missingWoCount'] > 0 ? 'text-red-600' : 'text-stone-700' }}">{{ $this->metrics['missingWoCount'] }}</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center font-bold">
                <x-lucide-alert-circle class="w-5 h-5" />
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block">{{ __('En Producción') }}</span>
                <span class="text-xl font-extrabold text-pink-600">{{ $this->metrics['inProductionCount'] }}</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-pink-50 text-pink-600 flex items-center justify-center font-bold">
                <x-lucide-layers class="w-5 h-5" />
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl border border-stone-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block">{{ __('Listas Hoy') }}</span>
                <span class="text-xl font-extrabold text-blue-600">{{ $this->metrics['doneTodayCount'] }}</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                <x-lucide-check-circle-2 class="w-5 h-5" />
            </div>
        </div>
    </div>
    @endisland

    <!-- Toolbar Filters Bar -->
    <div class="bg-white rounded-xl border border-stone-200 p-3.5 shadow-2xs space-y-3 w-full">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-stone-100">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-stone-100 text-stone-700 flex items-center justify-center">
                        <x-lucide-table-properties class="w-4 h-4" />
                    </div>
                    <h2 class="font-bold text-sm text-stone-900">{{ __('Overview Operativo') }}</h2>
                </div>

                <!-- View Mode Tabs (TODAS | WORKSPACE | BACKLOG | ARCHIVADAS) -->
                <div class="inline-flex items-center gap-1 bg-stone-100 p-1 rounded-xl border border-stone-200">
                    <button 
                        wire:click="setTab('all')"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $activeTab === 'all' ? 'bg-white text-stone-900 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        {{ __('TODAS') }}
                    </button>
                    <button 
                        wire:click="setTab('workspace')"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'workspace' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <span>⚡ {{ __('WORKSPACE') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-extrabold">{{ $totalWorkspaceCount }}</span>
                    </button>
                    <button 
                        wire:click="setTab('backlog')"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'backlog' ? 'bg-white text-amber-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <span>📦 {{ __('BACKLOG') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-100 text-amber-800 font-extrabold">{{ $totalBacklogCount }}</span>
                    </button>
                    <button 
                        wire:click="setTab('archived')"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'archived' ? 'bg-white text-cyan-800 shadow-2xs' : 'text-stone-500 hover:text-stone-900' }}">
                        <span>🗄️ {{ __('ARCHIVADAS') }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-cyan-100 text-cyan-800 font-extrabold">{{ $totalArchivedCount }}</span>
                    </button>
                </div>
            </div>

            <!-- Global Search, Per Page & Reset Buttons -->
            <div class="flex items-center gap-2">
                <!-- Cache Status & Refresh Button -->
                <button 
                    type="button"
                    wire:click="refreshCache"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer"
                    title="{{ __('Caché en disco activo. Clic para forzar recarga.') }}">
                    <x-lucide-zap class="w-3.5 h-3.5 text-emerald-600" />
                    <span class="hidden md:inline">{{ __('Caché Disco') }}</span>
                    <x-lucide-refresh-cw class="w-3 h-3 text-emerald-500 hover:rotate-180 transition-transform" />
                </button>

                <!-- Per Page Selector -->
                <div class="flex items-center gap-1.5 text-xs text-stone-500 font-semibold bg-stone-50 px-2 py-1.5 rounded-lg border border-stone-200">
                    <span class="hidden sm:inline text-stone-400 font-medium">{{ __('Mostrar:') }}</span>
                    <select 
                        wire:model.live="perPage" 
                        class="bg-transparent text-xs font-bold text-stone-800 focus:outline-none cursor-pointer">
                        <option value="0">⚡ Todas (Sin paginación)</option>
                        <option value="25">25 / pág</option>
                        <option value="50">50 / pág</option>
                        <option value="100">100 / pág</option>
                        <option value="250">250 / pág</option>
                    </select>
                </div>

                <div class="relative w-64">
                    <x-lucide-search class="w-4 h-4 text-stone-400 absolute left-2.5 top-2.5" />
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Buscar WO, cliente, orden...') }}"
                        class="w-full pl-8 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs focus:ring-2 focus:ring-stone-900 focus:bg-white transition"
                    >
                </div>

                @if($search || $filterWo || $filterClient || $filterDesigner || $filterReviewStatus || $filterInstallation || $filterDateRange)
                    <button 
                        wire:click="resetFilters" 
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-rotate-ccw class="w-3.5 h-3.5" />
                        {{ __('Limpiar') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Filters Row -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
            <!-- Filter WO -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Filtro WO') }}</label>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="filterWo"
                    placeholder="ej. WO 1234 o Sin WO"
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white"
                >
            </div>

            <!-- Filter Client -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Cliente') }}</label>
                <select 
                    wire:model.live="filterClient" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todos los clientes') }}</option>
                    @foreach($clients as $c)
                        <option value="{{ is_object($c) ? $c->id : $c }}">{{ is_object($c) ? $c->name : $c }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Designer -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Diseñador') }}</label>
                <select 
                    wire:model.live="filterDesigner" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($designers as $d)
                        <option value="{{ is_object($d) ? $d->id : $d }}">{{ is_object($d) ? $d->name : $d }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Review Status -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Revisión Estimado') }}</label>
                <select 
                    wire:model.live="filterReviewStatus" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todas') }}</option>
                    <option value="CS">{{ __('Revisado por CS (Rosado)') }}</option>
                    <option value="CAMILA">{{ __('Revisado por Camila (Amarillo)') }}</option>
                    <option value="NONE">{{ __('Sin revisión (Blanco)') }}</option>
                </select>
            </div>

            <!-- Filter Installation -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1">{{ __('Instalación') }}</label>
                <select 
                    wire:model.live="filterInstallation" 
                    class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-md text-xs focus:ring-1 focus:ring-stone-900 focus:bg-white">
                    <option value="">{{ __('Todas') }}</option>
                    <option value="Kudos">Kudos (Sin color)</option>
                    <option value="Kudos (Entregado)">Kudos (Verde - Entregado)</option>
                    <option value="Debe">Debe (Rojo)</option>
                    <option value="NONE">Vacío (Sin información)</option>
                </select>
            </div>
        </div>
    </div>

    @php
        $getDesignerBadgeStyle = function($designerName) {
            if (!$designerName) return 'bg-stone-100 text-stone-700 border-stone-200';
            return match($designerName) {
                'Euralíz' => 'bg-fuchsia-500 text-white font-semibold border-fuchsia-600 shadow-2xs',
                'César' => 'bg-cyan-500 text-white font-semibold border-cyan-600 shadow-2xs',
                'Adrián' => 'bg-emerald-500 text-white font-semibold border-emerald-600 shadow-2xs',
                default => 'bg-amber-400 text-amber-950 font-semibold border-amber-500 shadow-2xs',
            };
        };
    @endphp

    <!-- Single Unified Orders Data Grid (11 Columns Layout) -->
    <div class="bg-white rounded-xl border border-stone-200 shadow-2xs w-full pb-12">
        <!-- Table Header Bar -->
        <div class="w-full px-4 py-3 bg-[#f7f7f5] border-b border-stone-200 flex items-center justify-between rounded-t-xl">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-stone-900 text-white flex items-center justify-center font-bold text-xs">
                    @if($activeTab === 'workspace') ⚡
                    @elseif($activeTab === 'backlog') 📦
                    @elseif($activeTab === 'archived') 🗄️
                    @else 📋
                    @endif
                </div>
                <h3 class="font-bold text-sm text-stone-900 tracking-tight">
                    @if($activeTab === 'workspace')
                        {{ __('Órdenes en Workspace') }}
                    @elseif($activeTab === 'backlog')
                        {{ __('Órdenes en Backlog') }}
                    @elseif($activeTab === 'archived')
                        {{ __('Órdenes Archivadas') }}
                    @else
                        {{ __('Todas las Órdenes (Workspace + Backlog + Archivadas)') }}
                    @endif
                </h3>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-stone-200 text-stone-800">
                    {{ method_exists($orders, 'total') ? $orders->total() : count($orders) }}
                </span>
            </div>

            <div class="text-xs text-stone-500 font-medium hidden sm:block">
                @if($activeTab === 'all')
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">⚡ {{ $totalWorkspaceCount }} {{ __('Workspace') }}</span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-1 text-amber-700 font-semibold">📦 {{ $totalBacklogCount }} {{ __('Backlog') }}</span>
                        <span>•</span>
                        <span class="inline-flex items-center gap-1 text-cyan-700 font-semibold">🗄️ {{ $totalArchivedCount }} {{ __('Archivadas') }}</span>
                    </span>
                @endif
            </div>
        </div>

        <div class="w-full overflow-x-hidden pb-16">
            <table x-ref="ordersTable" class="w-full table-fixed text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200 text-[10px] uppercase font-bold text-stone-500 tracking-wider">
                        <!-- 1. Fecha Creación -->
                        <th 
                            :style="'width: ' + (colWidths['created_at'] || 6) + '%;'"
                            class="relative py-2 px-1 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('created_at')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Creación">Creación</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'created_at')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 2. Fecha Enviado a Producción -->
                        <th 
                            :style="'width: ' + (colWidths['prod_date'] || 6) + '%;'"
                            class="relative py-2 px-1 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('production_sent_at')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Enviado a Producción">Env. Prod.</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'prod_date')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 3. WO # -->
                        <th 
                            :style="'width: ' + (colWidths['wo'] || 6) + '%;'"
                            class="relative py-2 px-1 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('wo_number')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="WO #">WO #</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'wo')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 4. Client -->
                        <th 
                            :style="'width: ' + (colWidths['client'] || 12) + '%;'"
                            class="relative py-2 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('company_name')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Cliente">Cliente</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'client')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 5. Order Name -->
                        <th 
                            :style="'width: ' + (colWidths['name'] || 14) + '%;'"
                            class="relative py-2 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('task_name')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nombre de Orden">Nombre de Orden</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'name')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 6. Designer -->
                        <th 
                            :style="'width: ' + (colWidths['designer'] || 7.5) + '%;'"
                            class="relative py-2 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Diseñador">Diseñador</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'designer')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 7. Nota Producción / Instalación -->
                        <th 
                            :style="'width: ' + (colWidths['prod_note'] || 12) + '%;'"
                            class="relative py-2 px-1.5 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nota Producción/Instalación">Nota Prod./Inst.</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'prod_note')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 8. Estimado / Invoice -->
                        <th 
                            :style="'width: ' + (colWidths['invoice'] || 7) + '%;'"
                            class="relative py-2 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Estimado / Invoice">Est. / Inv.</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'invoice')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 8.5. Fecha Email -->
                        <th 
                            :style="'width: ' + (colWidths['email_date'] || 6) + '%;'"
                            class="relative py-2 px-1 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('email_date')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Fecha Email">Email</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'email_date')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 9. Instalación -->
                        <th 
                            :style="'width: ' + (colWidths['installation'] || 6) + '%;'"
                            class="relative py-2 px-1 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Instalación">Instalación</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'installation')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 10. CHECK MARK -->
                        <th 
                            :style="'width: ' + (colWidths['check'] || 3.5) + '%;'"
                            class="relative py-2 px-0.5 text-center select-none group/col">
                            <div class="flex items-center justify-center gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate">✓</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'check')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 11. Nota de Entrega -->
                        <th 
                            :style="'width: ' + (colWidths['deliv_note'] || 7) + '%;'"
                            class="relative py-2 px-1.5 select-none group/col">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Nota de Entrega">Entrega</span>
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'deliv_note')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>

                        <!-- 12. Subestatus -->
                        <th 
                            :style="'width: ' + (colWidths['substatus'] || 7) + '%;'"
                            class="relative py-2 px-1.5 cursor-pointer hover:bg-stone-100 select-none group/col"
                            wire:click="sortByColumn('substatus')">
                            <div class="flex items-center justify-between gap-0.5 w-full pointer-events-none overflow-hidden">
                                <span class="truncate" title="Subestatus">Subestatus</span>
                                <x-lucide-arrow-up-down class="w-3 h-3 text-stone-400 shrink-0" />
                            </div>
                            <div 
                                @mousedown.stop.prevent="initResize($event, 'substatus')"
                                @click.stop.prevent
                                class="absolute right-0 top-0 bottom-0 w-3 cursor-col-resize hover:bg-emerald-500/60 group-hover/col:bg-stone-300 transition z-30"
                                title="Arrastrar para redimensionar">
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 font-medium text-[11px]">
                    @forelse($orders as $order)
                        @php
                            $isProd = ($order->core_status === \App\Enums\CoreStatus::EN_PRODUCCION) 
                                || ($order->core_status?->value === 'EN PRODUCCIÓN') 
                                || ($order->core_status === 'EN PRODUCCIÓN');

                            $isArchived = ($order->core_status === \App\Enums\CoreStatus::ARCHIVED) 
                                || ($order->core_status?->value === 'ARCHIVED') 
                                || ($order->core_status === 'ARCHIVED')
                                || !empty($order->archived_at);

                            $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);

                            $rowStyle = match(true) {
                                $isArchived => 'bg-cyan-100/90 hover:bg-cyan-200/90 text-cyan-950 font-semibold',
                                $isProd => 'bg-orange-200/90 hover:bg-orange-300/90 text-orange-950 font-semibold',
                                default => 'hover:bg-stone-50/80',
                            };
                        @endphp
                        <tr class="transition group relative {{ $rowStyle }}">
                            <!-- 1. Fecha Creación -->
                            <td class="py-1 px-1.5 truncate">
                                @if($editingOrderId === $order->id && $editingField === 'manual_creation_date')
                                    <input 
                                        type="date" 
                                        wire:model="editingValue"
                                        wire:change="saveEdit"
                                        wire:blur="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        autofocus
                                        class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[10px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    >
                                @else
                                    <div 
                                        wire:click="startEdit({{ $order->id }}, 'manual_creation_date')"
                                        class="cursor-pointer hover:underline text-stone-600 truncate text-[10.5px]"
                                        title="{{ $order->manual_creation_date ? $order->manual_creation_date->format('d/m/Y') : ($order->created_at ? $order->created_at->format('d/m/Y') : 'Sin fecha') }} (Clic para editar)">
                                        {{ $order->manual_creation_date ? $order->manual_creation_date->format('d/m/Y') : ($order->created_at ? $order->created_at->format('d/m/Y') : '—') }}
                                    </div>
                                @endif
                            </td>

                            <!-- 2. Fecha Enviado a Producción -->
                            <td class="py-1 px-1.5 truncate">
                                @if($editingOrderId === $order->id && $editingField === 'production_sent_at')
                                    <input 
                                        type="date" 
                                        wire:model="editingValue"
                                        wire:change="saveEdit"
                                        wire:blur="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        autofocus
                                        class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[10px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    >
                                @else
                                    <div 
                                        wire:click="startEdit({{ $order->id }}, 'production_sent_at')"
                                        class="cursor-pointer hover:underline text-stone-600 truncate text-[10.5px]"
                                        title="{{ $order->production_sent_at ? $order->production_sent_at->format('d/m/Y') : 'Sin fecha' }} (Clic para editar)">
                                        {{ $order->production_sent_at ? $order->production_sent_at->format('d/m/Y') : '—' }}
                                    </div>
                                @endif
                            </td>

                            <!-- 3. WO # (Click opens modal if exists + Backlog pill if in_workspace is false) -->
                            <td class="py-1 px-1.5 truncate">
                                <div class="flex items-center gap-1 overflow-hidden truncate">
                                    @if(! $order->in_workspace)
                                        <button 
                                            wire:click="moveToWorkspace({{ $order->id }})"
                                            class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 transition cursor-pointer shrink-0"
                                            title="En Backlog - Clic para mover al Workspace">
                                            📦
                                        </button>
                                    @endif

                                    @if($order->hasNoWo())
                                        @if($editingOrderId === $order->id && $editingField === 'wo_number')
                                            <input 
                                                type="text" 
                                                wire:model="editingValue"
                                                wire:keydown.enter="saveEdit"
                                                wire:blur="saveEdit"
                                                wire:keydown.escape="cancelEdit"
                                                placeholder="WO..."
                                                autofocus
                                                class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[10px] font-mono font-bold w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                            >
                                        @else
                                            <div 
                                                wire:click="startEdit({{ $order->id }}, 'wo_number')"
                                                class="cursor-pointer inline-flex items-center gap-0.5 px-1 py-0.5 rounded-sm text-[9px] font-bold bg-rose-100 text-rose-800 border border-rose-300 animate-pulse truncate"
                                                title="Sin WO - Clic para agregar">
                                                <x-lucide-alert-circle class="w-2.5 h-2.5 shrink-0" />
                                                <span class="truncate">Sin WO</span>
                                            </div>
                                        @endif
                                    @else
                                        <button 
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                            class="font-mono font-bold text-stone-900 hover:text-emerald-700 hover:underline truncate cursor-pointer text-left block"
                                            title="{{ $order->wo_number }} - Ver Detalle">
                                            {{ $order->wo_number }}
                                        </button>
                                    @endif
                                </div>
                            </td>

                            <!-- 4. Cliente (Click opens modal) -->
                            <td class="py-1 px-1.5 truncate">
                                <button 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="font-semibold text-stone-800 hover:text-emerald-700 hover:underline truncate cursor-pointer text-left w-full block uppercase"
                                    title="{{ $order->company_name ?: ($order->client?->name ?? '—') }}">
                                    {{ $order->company_name ?: ($order->client?->name ?? '—') }}
                                </button>
                            </td>

                            <!-- 5. Order Name (Click opens modal) -->
                            <td class="py-1 px-1.5 truncate">
                                <button 
                                    wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
                                    class="text-stone-700 font-medium hover:text-stone-900 hover:underline truncate cursor-pointer text-left w-full block uppercase"
                                    title="{{ $order->clean_task_name }}">
                                    {{ $order->clean_task_name }}
                                </button>
                            </td>

                            <!-- 6. Designer (Full Color Badge, No Dot) -->
                            <td class="py-1 px-1 truncate">
                                <div class="relative w-full" 
                                    x-data="{ 
                                        openDes: false,
                                        dropStyle: '',
                                        toggleDes(el) {
                                            if (this.openDes) {
                                                this.openDes = false;
                                                return;
                                            }
                                            const rect = el.getBoundingClientRect();
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            const menuHeight = 220;
                                            const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
                                            const left = Math.min(Math.max(10, rect.left), window.innerWidth - 170);
                                            if (openUp) {
                                                const bottom = window.innerHeight - rect.top + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; max-height: ${Math.min(260, rect.top - 20)}px; z-index: 99999;`;
                                            } else {
                                                const top = rect.bottom + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; top: ${top}px; max-height: ${Math.min(260, spaceBelow - 20)}px; z-index: 99999;`;
                                            }
                                            this.openDes = true;
                                        }
                                    }"
                                    @scroll.window.passive="openDes = false">
                                    <button 
                                        @click.stop="toggleDes($el)"
                                        class="px-1 py-0.5 rounded-sm border text-[10px] font-semibold cursor-pointer truncate transition w-full text-center block {{ $getDesignerBadgeStyle($order->designer?->name) }}"
                                        title="{{ $order->designer?->name ?? 'Sin Asignar' }}">
                                        <span class="truncate block">{{ $order->designer?->name ?? 'Sin Asignar' }}</span>
                                    </button>

                                    <!-- Designer Selector Popover -->
                                    <template x-teleport="body">
                                        <div 
                                            x-show="openDes" 
                                            x-transition
                                            @click.outside="openDes = false"
                                            :style="dropStyle"
                                            class="bg-white shadow-2xl border border-stone-200 rounded-lg p-1 min-w-[150px] space-y-0.5 text-stone-900 overflow-y-auto">
                                            <div class="px-2 py-1 text-[10px] font-bold text-stone-400 uppercase">Seleccionar Diseñador</div>
                                            <button 
                                                wire:click="updateDesigner({{ $order->id }}, null)"
                                                @click="openDes = false"
                                                class="w-full text-left px-2 py-1 rounded text-xs hover:bg-stone-100 text-stone-500">
                                                -- Sin Asignar --
                                            </button>
                                            @foreach($designers as $d)
                                                <button 
                                                    wire:click="updateDesigner({{ $order->id }}, {{ is_object($d) ? $d->id : $d }})"
                                                    @click="openDes = false"
                                                    class="w-full text-left px-2 py-1 rounded text-xs hover:bg-stone-100 flex items-center justify-between font-semibold text-stone-800">
                                                    <span>{{ is_object($d) ? $d->name : $d }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <!-- 7. Nota Producción / Instalación -->
                            <td class="py-1 px-1.5 truncate">
                                @if($editingOrderId === $order->id && $editingField === 'production_note')
                                    <input 
                                        type="text" 
                                        wire:model="editingValue"
                                        wire:keydown.enter="saveEdit"
                                        wire:blur="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        placeholder="Nota producción..."
                                        autofocus
                                        class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[11px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    >
                                @else
                                    <div 
                                        wire:click="startEdit({{ $order->id }}, 'production_note')"
                                        class="cursor-pointer hover:bg-stone-100 rounded px-1 py-0.5 text-stone-600 italic truncate min-h-[22px] flex items-center"
                                        title="{{ $order->production_note ?: 'Clic para editar nota de producción' }}">
                                        {{ $order->production_note ?: '—' }}
                                    </div>
                                @endif
                            </td>

                            <!-- 8. Estimado / Invoice (Clean grid cell styling, NO inner box borders) -->
                            @php
                                $isRevised = !empty($order->review_status);
                                $prefix = $isRevised ? 'INV' : 'EST';
                                $cleanNum = $order->estimate_invoice_number ? trim(preg_replace('/^(INV|EST)\s*#?\s*/i', '', $order->estimate_invoice_number)) : '';
                                $displayText = $cleanNum ? "{$prefix} {$cleanNum}" : $prefix;
                                
                                $cellBg = match($order->review_status) {
                                    'CS' => 'bg-pink-100 text-pink-900 font-bold',
                                    'CAMILA' => 'bg-yellow-100 text-yellow-950 font-bold',
                                    default => 'bg-transparent text-stone-700',
                                };
                                
                                $checkIconColor = match($order->review_status) {
                                    'CS' => 'text-pink-700 hover:bg-pink-200/80',
                                    'CAMILA' => 'text-yellow-800 hover:bg-yellow-200/80',
                                    default => 'text-stone-400 hover:text-stone-800',
                                };
                            @endphp
                            <td class="py-1 px-1 truncate transition {{ $cellBg }}">
                                <div class="relative flex items-center justify-between gap-0.5 w-full py-0.5 truncate" 
                                    x-data="{ 
                                        openRev: false,
                                        dropStyle: '',
                                        toggleRev(el) {
                                            if (this.openRev) {
                                                this.openRev = false;
                                                return;
                                            }
                                            const rect = el.getBoundingClientRect();
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            const menuHeight = 180;
                                            const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
                                            const left = Math.min(Math.max(10, rect.left), window.innerWidth - 210);
                                            if (openUp) {
                                                const bottom = window.innerHeight - rect.top + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; z-index: 99999;`;
                                            } else {
                                                const top = rect.bottom + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; top: ${top}px; z-index: 99999;`;
                                            }
                                            this.openRev = true;
                                        }
                                    }"
                                    @scroll.window.passive="openRev = false">
                                    @if($editingOrderId === $order->id && $editingField === 'estimate_invoice_number')
                                        <input 
                                            type="text" 
                                            wire:model="editingValue"
                                            wire:keydown.enter="saveEdit"
                                            wire:blur="saveEdit"
                                            wire:keydown.escape="cancelEdit"
                                            placeholder="N°..."
                                            autofocus
                                            class="px-1 py-0.5 bg-white border border-stone-400 text-[10px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900 font-mono font-bold text-stone-900"
                                        >
                                    @else
                                        <span 
                                            wire:click="startEdit({{ $order->id }}, 'estimate_invoice_number')"
                                            class="cursor-pointer font-bold font-mono text-[10px] truncate block"
                                            title="{{ $displayText }} (Clic para editar)">
                                            {{ $displayText }}
                                        </span>
                                    @endif

                                    <button 
                                        @click.stop="toggleRev($el)"
                                        class="p-0.5 rounded cursor-pointer shrink-0 transition flex items-center justify-center border-none {{ $checkIconColor }}"
                                        title="Cambiar estado de revisión">
                                        <x-lucide-check-square class="w-3.5 h-3.5" />
                                    </button>

                                    <!-- Review Status Popover Selector -->
                                    <template x-teleport="body">
                                        <div 
                                            x-show="openRev" 
                                            x-transition
                                            @click.outside="openRev = false"
                                            :style="dropStyle"
                                            class="bg-white shadow-2xl border border-stone-200 rounded-lg p-1.5 min-w-[190px] space-y-1 text-stone-900">
                                            <div class="px-2 py-0.5 text-[10px] font-bold text-stone-400 uppercase">Estado de Revisión</div>
                                            <button 
                                                wire:click="updateReviewStatus({{ $order->id }}, 'CS')"
                                                @click="openRev = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-pink-100 text-pink-900 font-semibold hover:bg-pink-200 transition">
                                                Revisado por CS (Rosado)
                                            </button>
                                            <button 
                                                wire:click="updateReviewStatus({{ $order->id }}, 'CAMILA')"
                                                @click="openRev = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-yellow-100 text-yellow-950 font-semibold hover:bg-yellow-200 transition">
                                                Revisado por Camila (Amarillo)
                                            </button>
                                            <button 
                                                wire:click="updateReviewStatus({{ $order->id }}, null)"
                                                @click="openRev = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-stone-50 text-stone-600 hover:bg-stone-100 border border-stone-200 transition">
                                                Sin revisión (Blanco / EST)
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <!-- 8.5. Fecha Email -->
                            <td class="py-1 px-1.5 truncate">
                                @if($editingOrderId === $order->id && $editingField === 'email_date')
                                    <input 
                                        type="date" 
                                        wire:model="editingValue"
                                        wire:change="saveEdit"
                                        wire:blur="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        autofocus
                                        class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[10px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    >
                                @else
                                    <div 
                                        wire:click="startEdit({{ $order->id }}, 'email_date')"
                                        class="cursor-pointer hover:underline text-stone-600 truncate text-[10.5px]"
                                        title="{{ $order->email_date ? $order->email_date->format('d/m/Y') : 'Sin fecha' }} (Clic para editar)">
                                        {{ $order->email_date ? $order->email_date->format('d/m/Y') : '—' }}
                                    </div>
                                @endif
                            </td>

                            <!-- 9. Instalación -->
                            @php
                                $instCellBg = match($order->installation_type) {
                                    'Kudos (Entregado)', 'Kudos Verde' => 'bg-emerald-100 text-emerald-950 font-bold',
                                    'Debe' => 'bg-red-100 text-red-950 font-bold',
                                    'Kudos' => 'bg-transparent text-stone-900 font-semibold',
                                    'Cliente' => 'bg-transparent text-stone-700 font-medium',
                                    default => 'bg-transparent text-stone-400 font-normal',
                                };
                                $instDisplayText = match($order->installation_type) {
                                    'Kudos (Entregado)', 'Kudos Verde' => 'Kudos',
                                    'Debe' => 'Debe',
                                    'Kudos' => 'Kudos',
                                    'Cliente' => 'Cliente',
                                    default => '—',
                                };
                            @endphp
                            <td class="py-1 px-1 truncate transition {{ $instCellBg }}">
                                <div class="relative flex items-center justify-between gap-0.5 w-full py-0.5 truncate" 
                                    x-data="{ 
                                        openInst: false,
                                        dropStyle: '',
                                        toggleInst(el) {
                                            if (this.openInst) {
                                                this.openInst = false;
                                                return;
                                            }
                                            const rect = el.getBoundingClientRect();
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            const menuHeight = 200;
                                            const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
                                            const left = Math.min(Math.max(10, rect.left), window.innerWidth - 210);
                                            if (openUp) {
                                                const bottom = window.innerHeight - rect.top + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; z-index: 99999;`;
                                            } else {
                                                const top = rect.bottom + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; top: ${top}px; z-index: 99999;`;
                                            }
                                            this.openInst = true;
                                        }
                                    }"
                                    @scroll.window.passive="openInst = false">
                                    <button 
                                        @click.stop="toggleInst($el)"
                                        class="w-full text-left cursor-pointer flex items-center justify-between gap-0.5 border-none bg-transparent py-0.5 truncate"
                                        title="Clic para cambiar instalación: {{ $instDisplayText }}">
                                        <span class="truncate font-bold text-[10px] block">{{ $instDisplayText }}</span>
                                        <x-lucide-chevron-down class="w-2.5 h-2.5 text-stone-500 shrink-0" />
                                    </button>

                                    <!-- Installation Dropdown Popover -->
                                    <template x-teleport="body">
                                        <div 
                                            x-show="openInst" 
                                            x-transition
                                            @click.outside="openInst = false"
                                            :style="dropStyle"
                                            class="bg-white shadow-2xl border border-stone-200 rounded-lg p-1.5 min-w-[190px] space-y-1 text-stone-900">
                                            <div class="px-2 py-0.5 text-[10px] font-bold text-stone-400 uppercase">Estado de Instalación</div>
                                            
                                            <button 
                                                wire:click="updateInstallationType({{ $order->id }}, 'Kudos')"
                                                @click="openInst = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs hover:bg-stone-100 text-stone-800 font-semibold transition flex items-center justify-between">
                                                <span>Kudos</span>
                                                <span class="text-[10px] text-stone-400 font-normal">(Orden Lista)</span>
                                            </button>
                                            
                                            <button 
                                                wire:click="updateInstallationType({{ $order->id }}, 'Kudos (Entregado)')"
                                                @click="openInst = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-emerald-100 text-emerald-950 font-bold hover:bg-emerald-200 transition flex items-center justify-between">
                                                <span>Kudos</span>
                                                <span class="text-[10px] text-emerald-800 font-semibold">(Verde - Entregado)</span>
                                            </button>
                                            
                                            <button 
                                                wire:click="updateInstallationType({{ $order->id }}, 'Debe')"
                                                @click="openInst = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-red-100 text-red-950 font-bold hover:bg-red-200 transition flex items-center justify-between">
                                                <span>Debe</span>
                                                <span class="text-[10px] text-red-800 font-semibold">(Rojo - Atrasado)</span>
                                            </button>
                                            
                                            <button 
                                                wire:click="updateInstallationType({{ $order->id }}, null)"
                                                @click="openInst = false"
                                                class="w-full text-left px-2.5 py-1.5 rounded text-xs bg-stone-50 text-stone-600 hover:bg-stone-100 border border-stone-200 transition">
                                                Vacío (Sin información)
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <!-- 10. CHECK MARK (Standalone Toggle Checkbox) -->
                            <td class="py-1 px-0.5 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:click="toggleOverviewChecked({{ $order->id }})"
                                    {{ $order->overview_checked ? 'checked' : '' }}
                                    class="w-3.5 h-3.5 rounded border-stone-300 text-stone-900 focus:ring-stone-900 cursor-pointer"
                                >
                            </td>

                            <!-- 11. Nota de Entrega -->
                            <td class="py-1 px-1.5 truncate">
                                @if($editingOrderId === $order->id && $editingField === 'delivery_note')
                                    <input 
                                        type="text" 
                                        wire:model="editingValue"
                                        wire:keydown.enter="saveEdit"
                                        wire:blur="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        placeholder="Nota entrega..."
                                        autofocus
                                        class="px-1 py-0.5 bg-white border border-stone-400 rounded-none text-[11px] w-full focus:outline-none focus:ring-1 focus:ring-stone-900"
                                    >
                                @else
                                    <div 
                                        wire:click="startEdit({{ $order->id }}, 'delivery_note')"
                                        class="cursor-pointer hover:bg-stone-100 rounded px-1 py-0.5 text-stone-600 italic truncate min-h-[22px] flex items-center"
                                        title="{{ $order->delivery_note ?: 'Clic para editar nota de entrega' }}">
                                        {{ $order->delivery_note ?: '—' }}
                                    </div>
                                @endif
                            </td>

                            <!-- 12. Subestatus -->
                            @php
                                $subVal = $order->substatus?->value ?? (is_string($order->substatus) ? $order->substatus : null);
                                $subEnum = $subVal ? \App\Enums\Substatus::tryFrom($subVal) : null;
                                $subModel = ($subVal && ($substatuses->first() instanceof \App\Models\Substatus)) ? $substatuses->firstWhere('name', $subVal) : null;
                                $subLabel = $subEnum?->label() ?? ($subVal ?: '—');
                                
                                $subInlineStyle = '';
                                if ($subModel && !empty($subModel->bg_color) && !empty($subModel->text_color)) {
                                    $subInlineStyle = "background-color: {$subModel->bg_color}; color: {$subModel->text_color}; border-color: {$subModel->border_color};";
                                } elseif ($subEnum) {
                                    $subInlineStyle = $subEnum->customBadgeStyle() ?? '';
                                }

                                $subFallbackClass = match($subVal) {
                                    'URGENTE', \App\Enums\Substatus::URGENTE->value => 'bg-red-600 text-white font-extrabold shadow-2xs',
                                    'BLOQUEADA', \App\Enums\Substatus::BLOQUEADA->value => 'bg-amber-500 text-amber-950 font-extrabold',
                                    'CUSTOMER SERVICE REQUIRED', \App\Enums\Substatus::CUSTOMER_SERVICE_REQUIRED->value => 'bg-amber-400 text-amber-950 font-extrabold',
                                    'OVERDUE', \App\Enums\Substatus::OVERDUE->value => 'bg-red-500 text-white font-extrabold',
                                    'ALMOST OVERDUE', \App\Enums\Substatus::ALMOST_OVERDUE->value => 'bg-amber-400 text-amber-950 font-bold',
                                    'CAMBIOS CAMILA', \App\Enums\Substatus::CAMBIOS_CAMILA->value => 'bg-purple-600 text-white font-extrabold',
                                    'CAMBIOS CLIENTE', \App\Enums\Substatus::CAMBIOS_CLIENTE->value => 'bg-sky-500 text-white font-extrabold',
                                    'WAITING FOR CLIENT', \App\Enums\Substatus::WAITING_FOR_CLIENT->value => 'bg-sky-400 text-sky-950 font-extrabold',
                                    'PAUSADO', \App\Enums\Substatus::PAUSADO->value => 'bg-stone-400 text-stone-950 font-bold',
                                    'FALTA APROBACIÓN DE ESTIMADO', \App\Enums\Substatus::FALTA_APROBACION_ESTIMADO->value => 'bg-orange-500 text-white font-extrabold',
                                    'TICKET', \App\Enums\Substatus::TICKET->value => 'bg-rose-500 text-white font-extrabold',
                                    'PONER EN ALTA', \App\Enums\Substatus::PONER_EN_ALTA->value, 'ENVIADO EN ALTA', \App\Enums\Substatus::ENVIADO_EN_ALTA->value => 'bg-pink-500 text-white font-extrabold',
                                    'AJUSTES DE PRODUCCIÓN', \App\Enums\Substatus::AJUSTES_PRODUCCION->value => 'bg-fuchsia-600 text-white font-extrabold',
                                    'ESPERANDO PERMISO', \App\Enums\Substatus::ESPERANDO_PERMISO->value => 'bg-yellow-500 text-yellow-950 font-bold',
                                    'NO RESPUESTA', \App\Enums\Substatus::NO_RESPUESTA->value => 'bg-stone-500 text-white font-semibold',
                                    'POTENTIAL CUSTOMER', \App\Enums\Substatus::POTENTIAL_CUSTOMER->value => 'bg-emerald-600 text-white font-extrabold',
                                    null, '' => 'bg-transparent text-stone-400 font-normal',
                                    default => 'bg-amber-400 text-amber-950 font-bold',
                                };
                            @endphp
                            <td 
                                @if(!empty($subInlineStyle)) style="{{ $subInlineStyle }}" @endif
                                class="py-1 px-1.5 truncate transition {{ empty($subInlineStyle) ? $subFallbackClass : '' }}">
                                <div class="relative flex items-center justify-between gap-0.5 w-full py-0.5 truncate" 
                                    x-data="{ 
                                        openSub: false,
                                        dropStyle: '',
                                        toggleSub(el) {
                                            if (this.openSub) {
                                                this.openSub = false;
                                                return;
                                            }
                                            const rect = el.getBoundingClientRect();
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            const menuHeight = 300;
                                            const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
                                            const left = Math.min(Math.max(10, rect.right - 220), window.innerWidth - 235);
                                            if (openUp) {
                                                const bottom = window.innerHeight - rect.top + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; max-height: ${Math.min(320, rect.top - 20)}px; z-index: 99999;`;
                                            } else {
                                                const top = rect.bottom + 4;
                                                this.dropStyle = `position: fixed; left: ${left}px; top: ${top}px; max-height: ${Math.min(320, spaceBelow - 20)}px; z-index: 99999;`;
                                            }
                                            this.openSub = true;
                                        }
                                    }"
                                    @scroll.window.passive="openSub = false">
                                    <button 
                                        @click.stop="toggleSub($el)"
                                        class="w-full text-left cursor-pointer flex items-center justify-between gap-0.5 border-none bg-transparent py-0.5 truncate"
                                        title="Clic para cambiar subestatus / banderas: {{ $subLabel }}">
                                        <div class="flex items-center gap-0.5 overflow-hidden truncate">
                                            <span class="truncate font-bold text-[10px] block">{{ $subLabel }}</span>
                                            @if($order->isOverdue())
                                                <span class="px-0.5 py-0.2 rounded text-[8px] font-extrabold bg-red-600 text-white uppercase shrink-0">!</span>
                                            @elseif($order->isDueToday())
                                                <span class="px-0.5 py-0.2 rounded text-[8px] font-bold bg-amber-500 text-amber-950 uppercase shrink-0">PV</span>
                                            @endif
                                            @if(!empty($order->flags))
                                                @foreach($order->flags as $fName)
                                                    @php
                                                        $fEnum = \App\Enums\Substatus::tryFrom($fName);
                                                        $fLabel = $fEnum?->label() ?? $fName;
                                                    @endphp
                                                    @if($fName !== 'OVERDUE' && $fName !== 'ALMOST OVERDUE')
                                                        <span class="px-0.5 py-0.2 rounded text-[8px] font-extrabold bg-purple-600 text-white uppercase shrink-0" title="Flag {{ $fLabel }}">{{ Str::limit($fLabel, 3, '') }}</span>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                        <x-lucide-chevron-down class="w-2.5 h-2.5 opacity-60 shrink-0" />
                                    </button>

                                    <!-- Substatus & Flags Dynamic Dropdown Popover -->
                                    <template x-teleport="body">
                                        <div 
                                            x-show="openSub" 
                                            x-transition
                                            @click.outside="openSub = false"
                                            :style="dropStyle"
                                            class="bg-white shadow-2xl border border-stone-200 rounded-xl p-1.5 min-w-[240px] overflow-y-auto space-y-1.5 text-stone-900 scrollbar-thin">
                                            
                                            <!-- Section 1: Classification Substatus -->
                                            <div class="px-2 py-0.5 text-[10px] font-extrabold text-stone-400 uppercase tracking-wider border-b border-stone-100 pb-1">
                                                {{ __('Clasificación de Proceso (1 Selección)') }}
                                            </div>
                                            
                                            <button 
                                                wire:click="updateSubstatus({{ $order->id }}, null)"
                                                @click="openSub = false"
                                                class="w-full text-left px-2.5 py-1 rounded-md text-xs bg-stone-50 text-stone-500 hover:bg-stone-100 border border-stone-200 transition font-medium flex items-center justify-between cursor-pointer">
                                                <span>-- {{ __('Sin Subestatus') }} --</span>
                                                @if(empty($subVal))
                                                    <x-lucide-check class="w-3.5 h-3.5 text-stone-600 stroke-[3]" />
                                                @endif
                                            </button>

                                            @foreach($substatuses as $subItem)
                                                @php
                                                    $itemValue = $subItem instanceof \App\Models\Substatus ? $subItem->name : $subItem->value;
                                                    $itemEnum = \App\Enums\Substatus::tryFrom($itemValue);
                                                    if ($itemEnum && $itemEnum->isGlobal()) {
                                                        continue;
                                                    }
                                                    if ($subItem instanceof \App\Models\Substatus && $subItem->is_global) {
                                                        continue;
                                                    }
                                                    $itemLabel = $itemEnum?->label() ?? $itemValue;
                                                    $isSelected = ($subVal === $itemValue);
                                                    
                                                    if ($subItem instanceof \App\Models\Substatus && $subItem->bg_color && $subItem->text_color) {
                                                        $itemStyle = "background-color: {$subItem->bg_color}; color: {$subItem->text_color}; border-color: {$subItem->border_color};";
                                                    } else {
                                                        $itemStyle = $itemEnum?->customBadgeStyle() ?? '';
                                                    }

                                                    $itemFallbackClass = match($itemValue) {
                                                        'BLOQUEADA' => 'bg-amber-500 text-amber-950 font-extrabold',
                                                        'CUSTOMER SERVICE REQUIRED' => 'bg-amber-400 text-amber-950 font-extrabold',
                                                        'CAMBIOS CAMILA' => 'bg-purple-600 text-white font-extrabold',
                                                        'CAMBIOS CLIENTE' => 'bg-sky-500 text-white font-extrabold',
                                                        'WAITING FOR CLIENT' => 'bg-sky-400 text-sky-950 font-extrabold',
                                                        'PAUSADO' => 'bg-stone-400 text-stone-950 font-bold',
                                                        'FALTA APROBACIÓN DE ESTIMADO' => 'bg-orange-500 text-white font-extrabold',
                                                        'PONER EN ALTA', 'ENVIADO EN ALTA' => 'bg-pink-500 text-white font-extrabold',
                                                        'AJUSTES DE PRODUCCIÓN' => 'bg-fuchsia-600 text-white font-extrabold',
                                                        default => 'bg-stone-100 text-stone-800 border-stone-200 font-semibold',
                                                    };
                                                @endphp
                                                
                                                <button 
                                                    wire:click="updateSubstatus({{ $order->id }}, '{{ addslashes($itemValue) }}')"
                                                    @click="openSub = false"
                                                    @if(!empty($itemStyle)) style="{{ $itemStyle }}" @endif
                                                    class="w-full text-left px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center justify-between border cursor-pointer {{ empty($itemStyle) ? $itemFallbackClass : '' }} hover:opacity-90">
                                                    <span class="truncate">{{ $itemLabel }}</span>
                                                    @if($isSelected)
                                                        <x-lucide-check class="w-3.5 h-3.5 shrink-0 ml-1 stroke-[3]" />
                                                    @endif
                                                </button>
                                            @endforeach

                                            <!-- Section 2: Global Flags -->
                                            <div class="px-2 pt-2 py-0.5 text-[10px] font-extrabold text-stone-400 uppercase tracking-wider border-t border-stone-100 mt-1">
                                                {{ __('Banderas / Flags Globales (Coexistentes)') }}
                                            </div>

                                            @php
                                                $globalFlags = [
                                                    \App\Enums\Substatus::URGENTE,
                                                    \App\Enums\Substatus::TICKET,
                                                    \App\Enums\Substatus::POTENTIAL_CUSTOMER,
                                                ];
                                            @endphp
                                            @foreach($globalFlags as $flagEnum)
                                                @php
                                                    $hasF = $order->hasFlag($flagEnum);
                                                @endphp
                                                <button 
                                                    wire:click="toggleFlag({{ $order->id }}, '{{ $flagEnum->value }}')"
                                                    class="w-full text-left px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center justify-between border cursor-pointer {{ $hasF ? 'bg-indigo-600 text-white border-indigo-700' : 'bg-stone-50 text-stone-700 border-stone-200 hover:bg-stone-100' }}">
                                                    <span>🚩 {{ $flagEnum->label() }}</span>
                                                    @if($hasF)
                                                        <x-lucide-check class="w-3.5 h-3.5 text-white shrink-0 ml-1 stroke-[3]" />
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-8 text-center text-stone-400 font-medium">
                                {{ __('No se encontraron órdenes para la vista seleccionada.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Links Bar or All Loaded Status -->
        @if(method_exists($orders, 'hasPages') && $orders->hasPages())
            <div class="px-4 py-3 border-t border-stone-200 bg-[#f7f7f5] flex flex-col sm:flex-row items-center justify-between gap-3 rounded-b-xl">
                <div class="text-xs text-stone-500 font-medium">
                    {{ __('Mostrando') }} <span class="font-bold text-stone-900">{{ $orders->firstItem() }}</span> {{ __('a') }} <span class="font-bold text-stone-900">{{ $orders->lastItem() }}</span> {{ __('de') }} <span class="font-bold text-stone-900">{{ $orders->total() }}</span> {{ __('órdenes') }}
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        @else
            <div class="px-4 py-2.5 border-t border-stone-200 bg-[#f7f7f5] flex flex-col sm:flex-row items-center justify-between gap-3 rounded-b-xl">
                <div class="text-xs text-stone-600 font-medium flex items-center gap-2">
                    <span>{{ __('Mostrando todas las') }} <strong class="text-stone-900 font-bold">{{ count($orders) }}</strong> {{ __('órdenes en vista completa') }}</span>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full border border-emerald-300">
                        <x-lucide-zap class="w-3 h-3 text-emerald-600" /> {{ __('Sin paginación • File Cached') }}
                    </span>
                </div>
                <div class="text-[11px] text-stone-400">
                    {{ __('Los cambios se sincronizan y actualizan el caché en tiempo real') }}
                </div>
            </div>
        @endif
    </div>
</div>
