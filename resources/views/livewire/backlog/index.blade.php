<div class="space-y-5 pb-20">
    
    <!-- Top Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-stone-100 border border-stone-200 flex items-center justify-center shrink-0">
                <x-lucide-box class="w-4 h-4 text-zinc-700" />
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight truncate">Backlog de Órdenes ({{ $backlogTotalCount }} Tarjetas)</h2>
                <p class="text-xs text-zinc-500 truncate">
                    Las tarjetas en el Backlog no saturan tus vistas activas. Selecciónalas y añádelas al Workspace según sea necesario.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <div class="px-3 py-1.5 rounded-lg bg-stone-100 border border-stone-200 text-xs text-zinc-700 flex items-center gap-2">
                <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500" />
                <span>Activas en Workspace: <strong>{{ $activeWorkspaceCount }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Search & Batch Action Bar -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative w-full sm:w-64">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar en backlog..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-8 pr-3 py-1 text-xs text-zinc-800 focus:outline-none w-full">
            </div>

            <select wire:model.live="statusFilter" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 focus:outline-none">
                <option value="all">Todas las Listas</option>
                @foreach($coreStatuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="designerFilter" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 focus:outline-none">
                <option value="all">Todos los Diseñadores</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="sortBy" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 font-medium focus:outline-none">
                <option value="trello_created_at_desc">🗓️ Creación Trello (Más Recientes)</option>
                <option value="trello_created_at_asc">🗓️ Creación Trello (Más Antiguas)</option>
                <option value="due_date_asc">⏰ Fecha Límite Próxima</option>
                <option value="company_asc">🔤 Empresa (A-Z)</option>
            </select>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto shrink-0 justify-end">
            @if(count($selectedOrders) > 0)
                <button wire:click="addSelectedToWorkspace" class="px-3.5 py-1.5 rounded-md bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1.5">
                    <x-lucide-plus-circle class="w-3.5 h-3.5 text-emerald-400" />
                    <span>Añadir Selección ({{ count($selectedOrders) }})</span>
                </button>
            @endif

            <button wire:click="addAllFilteredToWorkspace" wire:confirm="¿Añadir todas las órdenes filtradas al Workspace activo?" class="px-3 py-1.5 rounded-md bg-stone-100 hover:bg-stone-200 text-zinc-800 border border-stone-200 font-medium text-xs transition flex items-center gap-1.5">
                <x-lucide-arrow-right-circle class="w-3.5 h-3.5 text-zinc-600" />
                <span>Añadir Todas las Filtradas</span>
            </button>
        </div>
    </div>

    <!-- Flash & Warning Messages -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-alert-triangle class="w-4 h-4 text-amber-600 shrink-0" />
            <span class="truncate">{{ session('warning') }}</span>
        </div>
    @endif

    <!-- Backlog Notion Database Table -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700">
                <thead class="bg-[#f7f7f5] text-zinc-600 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="sticky left-0 z-20 bg-[#f7f7f5] p-3 w-10 text-center border-r border-[#e9e9e7]">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-stone-300 text-stone-800 focus:ring-stone-400">
                        </th>
                        <th class="sticky left-10 z-20 bg-[#f7f7f5] p-3 whitespace-nowrap border-r border-[#e9e9e7] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">WO #</th>
                        <th class="p-3">Empresa</th>
                        <th class="p-3">Responsable</th>
                        <th class="p-3">Tarea / Trabajo</th>
                        <th class="p-3">Creación Trello</th>
                        <th class="p-3">Fecha Límite</th>
                        <th class="p-3">Lista / Estado</th>
                        <th class="p-3">Diseñador</th>
                        <th class="sticky right-0 z-20 bg-[#f7f7f5] p-3 text-right whitespace-nowrap border-l border-[#e9e9e7] shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)]">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($orders as $order)
                        @php $isSelected = in_array((string)$order->id, $selectedOrders); @endphp
                        <tr class="group hover:bg-[#fcfcfb] transition {{ $isSelected ? 'bg-stone-50' : '' }}">
                            <td class="sticky left-0 z-10 p-3 text-center border-r border-[#e9e9e7] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="rounded border-stone-300 text-stone-800 focus:ring-stone-400">
                            </td>
                            <td class="sticky left-10 z-10 p-3 whitespace-nowrap font-mono text-xs font-bold text-zinc-900 border-r border-[#e9e9e7] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <div class="flex items-center gap-1.5">
                                    @if($order->wo_number)
                                        <span class="px-2 py-0.5 rounded bg-stone-900 text-white text-[10px]">{{ $order->wo_number }}</span>
                                    @else
                                        <span class="text-zinc-400 text-[10px]">—</span>
                                    @endif
                                    @if($order->trello_url)
                                        <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="p-0.5 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition" title="Abrir en Trello.com">
                                            <x-lucide-external-link class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 font-semibold text-zinc-900 min-w-0">
                                <span class="truncate block max-w-xs" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                @if($order->responsible_person)
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 border border-indigo-200 text-[10px] font-bold">
                                        {{ $order->responsible_person }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-zinc-600 min-w-0">
                                <span class="truncate block max-w-sm" title="{{ $order->task_name }}">{{ $order->task_name }}</span>
                            </td>
                            <td class="p-3 font-mono text-[11px] text-zinc-600 whitespace-nowrap">
                                {{ $order->trello_created_at ? $order->trello_created_at->format('d M, Y (H:i)') : 'N/A' }}
                            </td>
                            <td class="p-3 font-mono text-zinc-500 whitespace-nowrap">
                                {{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'N/A' }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 border border-stone-200 text-zinc-700">
                                    {{ $order->core_status->label() }}
                                </span>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-stone-100 border border-stone-200 text-[10px] text-zinc-700 font-medium">
                                    {{ $order->designer?->name ?? 'Sin Asignar' }}
                                </span>
                            </td>
                            <td class="sticky right-0 z-10 p-3 text-right whitespace-nowrap border-l border-[#e9e9e7] shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)] transition-colors {{ $isSelected ? 'bg-stone-50' : 'bg-white group-hover:bg-[#fcfcfb]' }}">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }}, startEdit: true })" class="px-2 py-1 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[11px] font-medium text-zinc-800 transition flex items-center gap-1" title="Editar campos y añadir a workspace">
                                        <x-lucide-pencil-line class="w-3 h-3 text-zinc-600" />
                                        <span>Editar & Añadir</span>
                                    </button>
                                    
                                    <button wire:click="addToWorkspace({{ $order->id }})" class="px-2 py-1 rounded bg-stone-900 hover:bg-stone-800 text-white text-[11px] font-medium transition flex items-center gap-1" title="Añadir directamente a workspace activo">
                                        <x-lucide-plus-circle class="w-3 h-3 text-emerald-400" />
                                        <span>Añadir Directo</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-zinc-400 italic">
                                <x-lucide-check-circle-2 class="w-6 h-6 text-emerald-600 mx-auto mb-2" />
                                <span>No hay tarjetas pendientes en el Backlog. ¡Todas están procesadas o en Workspace!</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    <!-- Permanent Fixed Viewport Bottom Pagination Bar -->
    <div 
        :class="sidebarOpen ? 'left-72' : 'left-24'"
        class="fixed bottom-4 right-8 z-40 bg-white/95 backdrop-blur-md border border-[#e9e9e7] rounded-xl p-3 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-[0_4px_24px_rgba(0,0,0,0.12)] transition-all duration-200 ease-in-out">
        <div class="flex items-center gap-3 text-xs text-zinc-600 font-medium">
            <span>
                Mostrando <strong class="text-zinc-900 font-semibold">{{ $orders->firstItem() ?? 0 }}</strong> – <strong class="text-zinc-900 font-semibold">{{ $orders->lastItem() ?? 0 }}</strong> de <strong class="text-zinc-900 font-semibold">{{ $orders->total() }}</strong> tarjetas
            </span>
            <div class="h-3 w-px bg-zinc-300"></div>
            <div class="flex items-center gap-1.5">
                <span class="text-zinc-400 text-[11px]">Ver:</span>
                <select wire:model.live="perPage" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-2 py-0.5 text-xs text-zinc-800 font-medium focus:outline-none">
                    <option value="25">25 / pág</option>
                    <option value="50">50 / pág</option>
                    <option value="100">100 / pág</option>
                </select>
            </div>
        </div>

        @if($orders->hasPages())
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Previous Button -->
                @if($orders->onFirstPage())
                    <span class="px-2.5 py-1 rounded-md bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                        <x-lucide-chevron-left class="w-3.5 h-3.5" />
                        <span>Anterior</span>
                    </span>
                @else
                    <button wire:click="previousPage" class="px-2.5 py-1 rounded-md bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1">
                        <x-lucide-chevron-left class="w-3.5 h-3.5 text-zinc-600" />
                        <span>Anterior</span>
                    </button>
                @endif

                <!-- Page Indicator -->
                <span class="px-3 py-1 rounded-md bg-stone-100 border border-stone-200 text-xs font-semibold font-mono text-zinc-800">
                    {{ $orders->currentPage() }} / {{ $orders->lastPage() }}
                </span>

                <!-- Next Button -->
                @if($orders->hasMorePages())
                    <button wire:click="nextPage" class="px-2.5 py-1 rounded-md bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1">
                        <span>Siguiente</span>
                        <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-600" />
                    </button>
                @else
                    <span class="px-2.5 py-1 rounded-md bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                        <span>Siguiente</span>
                        <x-lucide-chevron-right class="w-3.5 h-3.5" />
                    </span>
                @endif
            </div>
        @endif
    </div>

</div>
