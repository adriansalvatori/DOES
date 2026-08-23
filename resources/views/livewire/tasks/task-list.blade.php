<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Notion Header & Controls -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs shrink-0">
        <div class="flex items-center gap-3">
            <x-lucide-check-square class="w-5 h-5 text-zinc-700" />
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight">Tareas Vinculadas (Related Tasks)</h2>
                <p class="text-xs text-zinc-500">Gestión de acciones independientes asociadas a las órdenes de diseño.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative w-full sm:w-60">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar tarea o empresa..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-8 pr-3 py-1 text-xs text-zinc-800 focus:outline-none w-full">
            </div>

            <!-- Status Filter (Searchable) -->
            <div class="relative min-w-[150px]" 
                 x-data="{ 
                     open: false,
                     search: '',
                     selectStatus(val) {
                         $wire.set('statusFilter', val);
                         this.open = false;
                     }
                 }">
                <button 
                    type="button" 
                    @click="open = !open" 
                    @click.outside="open = false"
                    class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-md px-2.5 py-1 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                    <span class="truncate">
                        @if($statusFilter === 'todo')
                            Pendientes (To Do)
                        @elseif($statusFilter === 'done')
                            Completadas (Done)
                        @else
                            Todas las Tareas
                        @endif
                    </span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 overflow-y-auto divide-y divide-stone-100 text-xs">
                    <div 
                        @click="selectStatus('todo')" 
                        class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>Pendientes (To Do)</span>
                        @if($statusFilter === 'todo')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </div>
                    <div 
                        @click="selectStatus('done')" 
                        class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>Completadas (Done)</span>
                        @if($statusFilter === 'done')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </div>
                    <div 
                        @click="selectStatus('all')" 
                        class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>Todas las Tareas</span>
                        @if($statusFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </div>
                </div>
            </div>

            <!-- Type Filter (Searchable) -->
            <div class="relative min-w-[150px]" 
                 x-data="{ 
                     open: false,
                     search: '',
                     selectType(val) {
                         $wire.set('typeFilter', val);
                         this.open = false;
                     }
                 }">
                <button 
                    type="button" 
                    @click="open = !open" 
                    @click.outside="open = false"
                    class="w-full bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-md px-2.5 py-1 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                    <span class="truncate">{{ $typeFilter === 'all' ? 'Todos los Tipos' : $typeFilter }}</span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute left-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="Buscar tipo..." 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <div 
                        @click="selectType('all')" 
                        class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>Todos los Tipos</span>
                        @if($typeFilter === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </div>
                    @foreach($taskTypes as $type)
                        <div 
                            x-show="!search || '{{ strtolower(addslashes($type->value)) }}'.includes(search.toLowerCase())"
                            @click="selectType('{{ $type->value }}')" 
                            class="p-2 hover:bg-stone-100 cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span class="truncate">{{ $type->value }}</span>
                            @if($typeFilter === $type->value)
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- Notion Database Table View (Light Mode) -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700">
                <thead class="bg-[#f7f7f5] text-zinc-600 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="p-3">Estado</th>
                        <th class="p-3">Título de la Tarea</th>
                        <th class="p-3">Orden / Empresa</th>
                        <th class="p-3">Asignado</th>
                        <th class="p-3">Fecha Límite</th>
                        <th class="p-3 text-right">Detalles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($tasks as $task)
                        <tr class="hover:bg-[#fcfcfb] transition">
                            <td class="p-3">
                                <button wire:click="toggleTaskStatus({{ $task->id }})" class="px-2.5 py-1 rounded text-[10px] font-medium transition flex items-center gap-1.5 {{ $task->isDone() ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    <x-lucide-check-circle-2 class="w-3 h-3" />
                                    <span>{{ $task->isDone() ? 'Done ✓' : 'To Do' }}</span>
                                </button>
                            </td>
                            <td class="p-3 font-medium text-zinc-900">
                                {{ $task->title }}
                            </td>
                            <td class="p-3 font-normal text-zinc-600">
                                {{ $task->order?->company_name }}
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded bg-stone-100 border border-stone-200 text-[10px] text-zinc-700 font-medium">
                                    {{ $task->assignee?->name ?? 'Sin Asignar' }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-zinc-500">
                                {{ $task->due_date ? $task->due_date->format('d M, Y') : 'N/A' }}
                            </td>
                            <td class="p-3 text-right">
                                <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order_id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 inline-flex">
                                    <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                    <span>Detalle</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-zinc-400 italic">
                                No se encontraron tareas vinculadas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
