<div class="space-y-5">
    
    <!-- Top Notion-Style Header Controls -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <x-lucide-kanban class="w-5 h-5 text-zinc-700 shrink-0" />
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight truncate">Kanban Board (9 Listas)</h2>
                <p class="text-xs text-zinc-500 truncate">Arrastra y suelta tarjetas entre listas para actualizar su estado en tiempo real.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto shrink-0">
            <!-- Search Input with Live Occurrences Dropdown -->
            <div class="relative w-full sm:w-64" x-data="{ open: true }" @click.outside="open = false">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5 shrink-0 z-10" />
                <input type="text" 
                       wire:model.live.debounce.200ms="search" 
                       @focus="open = true" 
                       @input="open = true"
                       placeholder="Buscar empresa o tarea..." 
                       class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-8 pr-3 py-1 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">

                @if(strlen(trim($search)) >= 2)
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-h-72 overflow-y-auto p-1.5 text-xs">
                        <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 border-b border-[#f0f0ee] mb-1 flex items-center justify-between">
                            <span>Coincidencias ({{ $this->searchResults->count() }})</span>
                            <span class="text-[9px] font-mono text-zinc-400">Clic para abrir</span>
                        </div>

                        @forelse($this->searchResults as $result)
                            <button wire:click="selectSearchResult({{ $result->id }})" 
                                    @click="open = false"
                                    class="w-full text-left p-2 rounded-lg hover:bg-stone-100 transition flex items-center justify-between gap-2 group">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        @if($result->wo_number)
                                            <span class="font-mono text-[10px] font-bold px-1.5 py-0.2 rounded bg-stone-200 text-zinc-700 shrink-0">
                                                {{ $result->wo_number }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-zinc-900 truncate group-hover:text-stone-900 text-xs">
                                            {{ $result->company_name }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 truncate mt-0.5" title="{{ $result->task_name }}">{{ $result->task_name }}</p>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border bg-stone-50 text-zinc-600 border-stone-200 shrink-0">
                                        {{ $result->core_status->label() }}
                                    </span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 transition shrink-0" />
                                </div>
                            </button>
                        @empty
                            <div class="px-3 py-4 text-center text-zinc-400 text-xs">
                                No se encontraron coincidencias para "{{ $search }}"
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- Designer Filter -->
            <select wire:model.live="designerFilter" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 focus:border-stone-400 focus:outline-none">
                <option value="all">Todos los Diseñadores</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                @endforeach
            </select>

            <!-- Substatus Filter -->
            <select wire:model.live="substatusFilter" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 focus:border-stone-400 focus:outline-none">
                <option value="all">Todas las Condiciones</option>
                @foreach(\App\Enums\Substatus::cases() as $sub)
                    <option value="{{ $sub->value }}">{{ $sub->value }}</option>
                @endforeach
            </select>

            <!-- Create New Order Button -->
            <button 
                @click="$dispatch('open-create-order')" 
                class="px-3.5 py-1 rounded-md bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 shrink-0">
                <x-lucide-plus class="w-3.5 h-3.5 text-white" />
                <span>Nueva Orden</span>
            </button>
        </div>
    </div>

    <!-- Notion Column Group Filter Tabs Bar -->
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] pb-2 overflow-x-auto scrollbar-none text-xs">
        <button wire:click="$set('columnGroup', 'all')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'all' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-layers class="w-3.5 h-3.5 text-zinc-500" />
            <span>Todas las Listas (9)</span>
        </button>

        <button wire:click="$set('columnGroup', 'incoming')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'incoming' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-inbox class="w-3.5 h-3.5 text-zinc-500" />
            <span>Bloqueadas & Pendientes (4)</span>
        </button>

        <button wire:click="$set('columnGroup', 'in_progress')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'in_progress' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-play-circle class="w-3.5 h-3.5 text-zinc-500" />
            <span>En Proceso (3)</span>
        </button>

        <button wire:click="$set('columnGroup', 'final')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $columnGroup === 'final' ? 'bg-white text-zinc-900 border border-stone-300 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-zinc-500" />
            <span>Producción & Hold (2)</span>
        </button>
    </div>

    <!-- Alert Flash Message -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Notion Light Kanban Columns Container (Full Width Drag & Drop Grid) -->
    <div class="flex gap-3 overflow-x-auto pb-6 scrollbar-thin scrollbar-thumb-stone-300 min-h-[78vh] w-full">
        @foreach($columns as $column)
            @php
                $columnOrders = $orders->filter(fn($o) => $o->core_status === $column);
            @endphp
            <div 
                x-data="{ isTarget: false }"
                @dragover.prevent="isTarget = true"
                @dragleave.prevent="isTarget = false"
                @drop.prevent="
                    isTarget = false;
                    const orderId = event.dataTransfer.getData('text/plain');
                    if (orderId) {
                        $wire.moveOrder(orderId, '{{ $column->value }}');
                    }
                "
                :class="{ 'border-stone-400 ring-2 ring-stone-300/60 bg-stone-100': isTarget }"
                class="shrink-0 w-80 bg-[#f7f7f5] border border-[#e9e9e7] rounded-xl flex flex-col max-h-[80vh] transition duration-150 shadow-2xs">
                
                <!-- Column Header -->
                <div class="p-3 border-b border-[#e9e9e7] bg-[#efefed] rounded-t-xl flex items-center justify-between sticky top-0 z-10">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full shrink-0 bg-stone-600"></span>
                        <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider truncate">{{ $column->label() }}</h3>
                    </div>
                    @php
                        $columnTasks = ($column === \App\Enums\CoreStatus::TO_DO_TODAY)
                            ? $relatedTasks->filter(fn($t) => $t->order !== null && !$t->isDone())
                            : collect();
                        $totalItemCount = $columnOrders->count() + $columnTasks->count();
                    @endphp
                    <div class="flex items-center gap-1 shrink-0 ml-1">
                        <button 
                            @click="$dispatch('open-create-order', { initialStatus: '{{ $column->value }}' })" 
                            class="p-1 rounded text-zinc-500 hover:text-zinc-900 hover:bg-white transition" 
                            title="Añadir nueva orden a {{ $column->label() }}">
                            <x-lucide-plus class="w-3.5 h-3.5" />
                        </button>
                        <span class="px-2 py-0.5 rounded bg-white text-[11px] font-mono text-zinc-600 border border-stone-200 font-semibold shrink-0">
                            {{ $totalItemCount }}
                        </span>
                    </div>
                </div>

                <!-- Column Cards Container -->
                <div class="p-2.5 overflow-y-auto flex-1 space-y-2.5 scrollbar-thin">
                    @if($columnOrders->isEmpty() && $columnTasks->isEmpty())
                        <div class="py-12 text-center border border-dashed border-stone-300 rounded-lg">
                            <x-lucide-move class="w-4 h-4 text-zinc-400 mx-auto mb-1 shrink-0" />
                            <span class="text-[11px] text-zinc-500 font-normal block">Arrastra una tarjeta aquí</span>
                        </div>
                    @else
                        <!-- 1. RELATED TASK CARDS IN KANBAN (ANIMATES OUT ON COMPLETION) -->
                        @foreach($columnTasks as $task)
                            <div 
                                wire:key="task-card-{{ $task->id }}"
                                x-data="{ isCompleting: false }"
                                :class="{ 'opacity-0 scale-90 -translate-y-3 pointer-events-none transition-all duration-300 ease-out': isCompleting }"
                                class="bg-violet-50/60 border border-violet-200 hover:border-violet-300 rounded-lg p-3 space-y-2 shadow-2xs transition duration-200 group relative select-none">
                                <!-- Task Header: Badge & Assignee -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-700 text-white shrink-0 flex items-center gap-1">
                                            <x-lucide-check-square class="w-3 h-3 text-white" />
                                            <span>TAREA VINCULADA</span>
                                        </span>
                                        @if($task->type)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-800 border border-violet-200 shrink-0">
                                                {{ $task->type->label() }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-[10px] font-medium text-zinc-700 bg-white px-1.5 py-0.5 rounded border border-stone-200 shrink-0">
                                            {{ $task->assignee?->name ?? 'Sin Asignar' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Task Title & Parent Order Link -->
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-xs text-zinc-900 leading-snug break-words">
                                        {{ $task->title }}
                                    </h4>
                                    @if($task->order)
                                        <p class="text-[10px] text-violet-700 font-medium truncate mt-0.5 flex items-center gap-1">
                                            <x-lucide-link class="w-3 h-3 text-violet-500 shrink-0" />
                                            <span>{{ $task->order->wo_number ?? 'Orden' }} • {{ $task->order->company_name }}</span>
                                        </p>
                                    @endif
                                </div>

                                <!-- Task Controls: Quick Complete & View Order -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t border-violet-100 min-w-0">
                                    <button 
                                        @click="isCompleting = true; setTimeout(() => $wire.toggleTaskComplete({{ $task->id }}), 300)"
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-200 transition flex items-center gap-1 shadow-2xs">
                                        <x-lucide-check-circle-2 class="w-3 h-3 text-emerald-600" />
                                        <span>Completar</span>
                                    </button>

                                    @if($task->order)
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-stone-100 border border-stone-200 text-[10px] font-medium text-zinc-700 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>Ver Orden</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <!-- 2. REGULAR ORDER CARDS IN KANBAN -->
                        @foreach($columnOrders as $order)
                            <div 
                                wire:key="order-card-{{ $order->id }}"
                                draggable="true"
                                @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
                                class="bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg p-3 space-y-2 shadow-2xs transition cursor-grab active:cursor-grabbing group relative select-none">
                                
                                <!-- Card Header: Badges & Designer -->
                                <div class="flex items-start justify-between gap-1.5 min-w-0">
                                    <div class="flex flex-wrap gap-1 min-w-0">
                                        @if($order->wo_number)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-stone-900 text-white shrink-0 whitespace-nowrap">
                                                {{ $order->wo_number }}
                                            </span>
                                        @endif
                                        @if($order->responsible_person)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 shrink-0 whitespace-nowrap flex items-center gap-1">
                                                <x-lucide-user class="w-2.5 h-2.5 text-indigo-600 shrink-0" />
                                                <span>{{ $order->responsible_person }}</span>
                                            </span>
                                        @endif
                                        @if($order->substatus)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                {{ $order->substatus->value }}
                                            </span>
                                        @endif
                                        @if($order->customer_service_required)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-pink-50 text-pink-700 border border-pink-200 shrink-0 whitespace-nowrap">
                                                ATENCIÓN CLIENTE
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0 ml-1">
                                        @if($order->trello_url)
                                            <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="p-1 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition shrink-0" title="Abrir en Trello.com">
                                                <x-lucide-external-link class="w-3.5 h-3.5" />
                                            </a>
                                        @endif
                                        <span class="text-[10px] font-medium text-zinc-600 bg-stone-100 px-1.5 py-0.5 rounded border border-stone-200 shrink-0 whitespace-nowrap">
                                            {{ $order->designer?->name ?? 'Sin Asignar' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Title & Company -->
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-xs text-zinc-900 group-hover:text-stone-800 transition truncate leading-snug" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                    <p class="text-[11px] text-zinc-500 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                </div>

                                <!-- Metadata & Due Date -->
                                <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-1.5 border-t border-[#f0f0ee] gap-1">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <x-lucide-calendar class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span class="font-mono font-medium truncate {{ $order->isOverdue() ? 'text-red-600 font-bold' : 'text-zinc-700' }}">
                                            {{ $order->current_due_date ? $order->current_due_date->format('d M') : 'N/A' }}
                                        </span>
                                    </div>

                                    @if($order->relatedTasks->where('status', 'todo')->count() > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-violet-100 text-violet-800 font-semibold border border-violet-200 flex items-center gap-1 shrink-0 whitespace-nowrap" title="Tareas vinculadas activas">
                                            <x-lucide-check-square class="w-3 h-3 text-violet-600 shrink-0" />
                                            <span>{{ $order->relatedTasks->where('status', 'todo')->count() }} Tareas</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Quick Move Select & Modal Trigger -->
                                <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t border-[#f0f0ee] min-w-0">
                                    <select wire:change="moveOrder({{ $order->id }}, $event.target.value)" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded px-1.5 py-0.5 text-[10px] text-zinc-700 focus:outline-none w-full min-w-0 truncate">
                                        <option value="">Mover a...</option>
                                        @foreach($allColumns as $colOption)
                                            @if($colOption !== $order->core_status)
                                                <option value="{{ $colOption->value }}">{{ $colOption->label() }}</option>
                                            @endif
                                        @endforeach
                                    </select>

                                    <div class="shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>Detalle</span>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    @endif
                </div>

            </div>
        @endforeach
    </div>

    <!-- Create Order Modal -->
    <livewire:orders.create-order-modal />

    <!-- On Hold Reason Modal -->
    @if($showOnHoldModal)
        <div class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white border border-[#e9e9e7] rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 flex items-center gap-1.5">
                            <x-lucide-pause-circle class="w-5 h-5 text-amber-600 shrink-0" />
                            <span>Motivo para Poner en On Hold</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Ingresa la razón por la que esta orden entra en pausa.</p>
                    </div>
                    <button wire:click="cancelOnHold" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-1.5 text-xs">
                    <label class="font-medium text-zinc-700 block">Motivo / Comentario:</label>
                    <textarea wire:model="onHoldReason" rows="3" placeholder="Ej: Esperando confirmación de presupuesto por parte del cliente..." class="w-full bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg p-2.5 text-xs text-zinc-900 focus:outline-none focus:border-stone-400"></textarea>
                    @error('onHoldReason')
                        <span class="text-red-600 text-[11px] block mt-0.5">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="cancelOnHold" class="px-3 py-1.5 rounded-md bg-stone-100 text-zinc-700 text-xs font-medium hover:bg-stone-200 transition">
                        Cancelar
                    </button>
                    <button wire:click="confirmOnHold" class="px-3.5 py-1.5 rounded-md bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>Poner en On Hold</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
