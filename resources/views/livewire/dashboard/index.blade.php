<div class="space-y-5">
    
    <!-- Top Header Bar (Sober Light Style) -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-stone-100 border border-stone-200 flex items-center justify-center shrink-0">
                <x-lucide-layout-dashboard class="w-4 h-4 text-zinc-700" />
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight truncate">Centro de Control Operativo</h2>
                <p class="text-xs text-zinc-500 truncate">
                    Respondiendo la pregunta clave: <span class="text-zinc-700 font-medium italic">¿Qué necesita atención hoy, por qué y quién es responsable?</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto shrink-0">
            <!-- Search -->
            <div class="relative w-full sm:w-60">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5 shrink-0" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por empresa o tarea..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-8 pr-3 py-1 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">
            </div>

            <!-- Designer Filter Dropdown -->
            <select wire:model.live="selectedDesigner" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-2.5 py-1 text-xs text-zinc-700 focus:border-stone-400 focus:outline-none">
                <option value="all">Todos los Diseñadores</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Flash Message -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Notion Light Summary Stat Cards Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
        <button wire:click="setActiveTab('today')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'today' ? 'bg-white border-stone-400 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px]">Para Hoy</span>
                <x-lucide-pin class="w-3.5 h-3.5 text-zinc-500" />
            </div>
            <span class="text-base font-mono font-bold text-zinc-900">{{ $toDoTodayOrders->count() }}</span>
        </button>

        <button wire:click="setActiveTab('overdue')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'overdue' ? 'bg-white border-red-300 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px] text-red-700">Atrasadas</span>
                <x-lucide-alert-octagon class="w-3.5 h-3.5 text-red-600" />
            </div>
            <span class="text-base font-mono font-bold text-red-700">{{ $overdueOrders->count() }}</span>
        </button>

        <button wire:click="setActiveTab('camila')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'camila' ? 'bg-white border-purple-300 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px]">Camila</span>
                <x-lucide-user-check class="w-3.5 h-3.5 text-purple-600" />
            </div>
            <span class="text-base font-mono font-bold text-purple-800">{{ $camilaFollowUpTasks->count() }}</span>
        </button>

        <button wire:click="setActiveTab('client')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'client' ? 'bg-white border-sky-300 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px]">Cliente</span>
                <x-lucide-mail class="w-3.5 h-3.5 text-sky-600" />
            </div>
            <span class="text-base font-mono font-bold text-sky-800">{{ $clientFollowUpTasks->count() }}</span>
        </button>

        <button wire:click="setActiveTab('alta')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'alta' ? 'bg-white border-emerald-300 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px]">Listo ALTA</span>
                <x-lucide-rocket class="w-3.5 h-3.5 text-emerald-600" />
            </div>
            <span class="text-base font-mono font-bold text-emerald-800">{{ $readyForAltaOrders->count() }}</span>
        </button>

        <button wire:click="setActiveTab('resolver')" class="p-3 rounded-xl border text-left transition flex flex-col justify-between space-y-1 {{ $activeTab === 'resolver' ? 'bg-white border-rose-300 shadow-2xs font-semibold' : 'bg-[#f7f7f5] border-[#e9e9e7] hover:bg-[#efefed]' }}">
            <div class="flex items-center justify-between text-zinc-500 text-xs">
                <span class="font-medium text-[11px] text-rose-700">Resolver</span>
                <x-lucide-shield-alert class="w-3.5 h-3.5 text-rose-600" />
            </div>
            <span class="text-base font-mono font-bold text-rose-700">{{ $resolverOrders->count() }}</span>
        </button>
    </div>

    <!-- Notion Light Database Tabs Bar -->
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] pb-2 overflow-x-auto scrollbar-none text-xs">
        <button wire:click="setActiveTab('today')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'today' ? 'bg-white text-zinc-900 border border-[#d0d0ce] shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-pin class="w-3.5 h-3.5 text-zinc-500" />
            <span>Trabajo Para Hoy ({{ $toDoTodayOrders->count() }})</span>
        </button>

        <button wire:click="setActiveTab('overdue')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'overdue' ? 'bg-white text-red-800 border border-red-200 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-alert-octagon class="w-3.5 h-3.5 text-red-600" />
            <span>Atrasadas / Overdue ({{ $overdueOrders->count() }})</span>
        </button>

        <button wire:click="setActiveTab('camila')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'camila' ? 'bg-white text-purple-800 border border-purple-200 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-user-check class="w-3.5 h-3.5 text-purple-600" />
            <span>Revisiones Camila ({{ $camilaFollowUpTasks->count() }})</span>
        </button>

        <button wire:click="setActiveTab('client')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'client' ? 'bg-white text-sky-800 border border-sky-200 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-mail class="w-3.5 h-3.5 text-sky-600" />
            <span>Follow-ups Cliente ({{ $clientFollowUpTasks->count() }})</span>
        </button>

        <button wire:click="setActiveTab('alta')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'alta' ? 'bg-white text-emerald-800 border border-emerald-200 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-rocket class="w-3.5 h-3.5 text-emerald-600" />
            <span>Listas para ALTA ({{ $readyForAltaOrders->count() }})</span>
        </button>

        <button wire:click="setActiveTab('resolver')" class="px-3 py-1 rounded-md font-medium transition flex items-center gap-1.5 shrink-0 {{ $activeTab === 'resolver' ? 'bg-white text-rose-800 border border-rose-200 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800 hover:bg-[#f2f2f0]' }}">
            <x-lucide-shield-alert class="w-3.5 h-3.5 text-rose-600" />
            <span>Vista Resolver ({{ $resolverOrders->count() }})</span>
        </button>
    </div>

    <!-- Active Tab Panel Content -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 min-h-[400px] shadow-2xs">
        
        <!-- TAB 1: TO DO TODAY -->
        @if($activeTab === 'today')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-pin class="w-4 h-4 text-zinc-500" /> Trabajo Programado Para Hoy ({{ $toDoTodayOrders->count() }})
                    </h3>
                    <span class="text-[11px] text-zinc-400 font-mono">Haz clic en el checkbox al completar</span>
                </div>

                @if($toDoTodayOrders->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes programadas en 'TO DO TODAY' hoy.</p>
                @else
                    <div class="space-y-2 max-h-[65vh] overflow-y-auto pr-1 scrollbar-thin">
                        @foreach($toDoTodayOrders as $order)
                            <div class="bg-[#fcfcfb] border border-[#e9e9e7] rounded-lg p-3 flex items-center justify-between gap-3 hover:border-stone-400 transition min-w-0">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <button wire:click="markDoneToday({{ $order->id }})" class="w-4 h-4 rounded border flex items-center justify-center transition shrink-0 {{ $order->done_today ? 'bg-stone-800 border-stone-800 text-white font-bold' : 'border-stone-300 hover:border-stone-500 text-transparent' }}">
                                        <x-lucide-check class="w-3 h-3" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-medium text-xs text-zinc-900 truncate {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            @if($order->substatus)
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                    {{ $order->substatus->value }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-zinc-500 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2 py-0.5 rounded bg-stone-100 text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                        {{ $order->designer?->name }}
                                    </span>
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                        <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                        <span>Detalle</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 2: OVERDUE -->
        @if($activeTab === 'overdue')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-red-700 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-alert-octagon class="w-4 h-4 text-red-600" /> Órdenes Atrasadas / Overdue ({{ $overdueOrders->count() }})
                    </h3>
                </div>

                @if($overdueOrders->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes atrasadas en este momento.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($overdueOrders as $order)
                            <div class="bg-[#fcfcfb] border border-red-200 rounded-xl p-3.5 space-y-2.5 shadow-2xs min-w-0">
                                <div class="flex items-start justify-between gap-2 min-w-0">
                                    <div class="min-w-0 flex-1">
                                        <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 text-[9px] font-bold uppercase tracking-wider border border-red-200">
                                            {{ $order->substatus ? $order->substatus->value : 'OVERDUE' }}
                                        </span>
                                        <h4 class="font-medium text-xs text-zinc-900 mt-1 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <p class="text-[11px] text-zinc-500 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-stone-100 text-[10px] font-medium text-zinc-600 border border-stone-200 shrink-0 whitespace-nowrap">
                                        {{ $order->designer?->name ?? 'Sin Asignar' }}
                                    </span>
                                </div>

                                <div class="text-[11px] bg-stone-50 p-2.5 rounded-lg border border-stone-200 space-y-1">
                                    <div class="flex justify-between text-zinc-600">
                                        <span>Vencimiento:</span>
                                        <span class="font-mono text-red-600 font-bold">{{ $order->current_due_date ? $order->current_due_date->format('d M, Y') : 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between text-zinc-600">
                                        <span>Lista Actual:</span>
                                        <span class="font-medium text-zinc-800 truncate ml-2">{{ $order->core_status->label() }}</span>
                                    </div>
                                </div>

                                <div class="pt-1 flex items-center justify-between gap-2 min-w-0">
                                    <span class="text-[10px] text-red-700 font-medium italic truncate">Acción: Enviar Correo de Atraso</span>
                                    <div class="shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>Detalle</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 3: CAMILA -->
        @if($activeTab === 'camila')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-purple-800 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-user-check class="w-4 h-4 text-purple-600" /> Revisiones con Camila Pendientes ({{ $camilaFollowUpTasks->count() }})
                    </h3>
                </div>

                @if($camilaFollowUpTasks->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay tareas de seguimiento con Camila pendientes.</p>
                @else
                    <div class="space-y-2">
                        @foreach($camilaFollowUpTasks as $task)
                            <div class="bg-[#fcfcfb] border border-[#e9e9e7] rounded-lg p-3 flex items-center justify-between text-xs gap-3 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <span class="font-medium text-purple-900 block text-xs truncate">{{ $task->title }}</span>
                                    <span class="text-zinc-500 text-[11px] truncate block">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                </div>
                                <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-purple-50 text-purple-700 font-medium hover:bg-purple-100 text-xs border border-purple-200 shrink-0">
                                    Completar ✓
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 4: CLIENT -->
        @if($activeTab === 'client')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-sky-800 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-mail class="w-4 h-4 text-sky-600" /> Follow-ups Cliente ({{ $clientFollowUpTasks->count() }})
                    </h3>
                </div>

                @if($clientFollowUpTasks->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay tareas de seguimiento con cliente pendientes.</p>
                @else
                    <div class="space-y-2">
                        @foreach($clientFollowUpTasks as $task)
                            <div class="bg-[#fcfcfb] border border-[#e9e9e7] rounded-lg p-3 flex items-center justify-between text-xs gap-3 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <span class="font-medium text-sky-900 block text-xs truncate">{{ $task->title }}</span>
                                    <span class="text-zinc-500 text-[11px] truncate block">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                </div>
                                <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-sky-50 text-sky-700 font-medium hover:bg-sky-100 text-xs border border-sky-200 shrink-0">
                                    Completar ✓
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 5: ALTA -->
        @if($activeTab === 'alta')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-emerald-800 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-rocket class="w-4 h-4 text-emerald-600" /> Órdenes Listas para ALTA ({{ $readyForAltaOrders->count() }})
                    </h3>
                </div>

                @if($readyForAltaOrders->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes pendientes de poner en ALTA.</p>
                @else
                    <div class="space-y-2">
                        @foreach($readyForAltaOrders as $order)
                            <div class="bg-[#fcfcfb] border border-emerald-200 rounded-lg p-3 flex items-center justify-between text-xs gap-3 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <span class="font-medium text-zinc-900 block text-xs truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                                    <span class="text-emerald-700 text-[11px] font-medium truncate block">Subestatus: PONER EN ALTA • Diseñador: {{ $order->designer?->name }}</span>
                                </div>
                                <div class="shrink-0">
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                        <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                        <span>Detalle</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 6: RESOLVER -->
        @if($activeTab === 'resolver')
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                    <h3 class="font-semibold text-xs text-rose-700 uppercase tracking-wider flex items-center gap-2">
                        <x-lucide-shield-alert class="w-4 h-4 text-rose-600" /> Órdenes Bloqueadas / Vista Resolver ({{ $resolverOrders->count() }})
                    </h3>
                </div>

                @if($resolverOrders->isEmpty())
                    <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes bloqueadas o pendientes de resolución.</p>
                @else
                    <div class="space-y-2">
                        @foreach($resolverOrders as $order)
                            <div class="bg-[#fcfcfb] border border-rose-200 rounded-lg p-3 flex items-center justify-between gap-3 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <h4 class="font-medium text-xs text-zinc-900 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200 shrink-0 whitespace-nowrap">
                                            {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : 'BLOQUEADA') }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-zinc-500 mt-0.5 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                </div>

                                <div class="shrink-0">
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                        <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                        <span>Detalle</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </div>

</div>
