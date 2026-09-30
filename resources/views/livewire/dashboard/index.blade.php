<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Top Header Bar (Sober Light Style) -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <x-lucide-layout-dashboard class="w-4.5 h-4.5 text-stone-100" />
            </div>
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">{{ __('Centro de Control Operativo') }}</h1>
                <p class="text-xs text-zinc-500 truncate mt-0.5">
                    {{ __('Respondiendo la pregunta clave:') }} <span class="text-zinc-700 font-medium italic">{{ __('¿Qué necesita atención hoy, por qué y quién es responsable?') }}</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto shrink-0">
            <!-- Quick Role / Persona View Switcher -->
            <div id="tour-role-switcher" class="inline-flex rounded-lg bg-stone-100 p-0.5 border border-stone-200 text-[11px] font-semibold shrink-0">
                <button 
                    wire:click="setUserRole('all')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 cursor-pointer {{ $userRole === 'all' ? 'bg-white text-zinc-900 shadow-2xs font-semibold' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-layout-grid class="w-3 h-3 text-zinc-400" />
                    <span>{{ __('Vista General') }}</span>
                </button>
                <button 
                    wire:click="setUserRole('designer')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 cursor-pointer {{ $userRole === 'designer' ? 'bg-emerald-600 text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-palette class="w-3 h-3" />
                    <span>{{ __('Diseñador') }}</span>
                </button>
                <button 
                    wire:click="setUserRole('manager')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 cursor-pointer {{ $userRole === 'manager' ? 'bg-sky-600 text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-briefcase class="w-3 h-3" />
                    <span>{{ __('Gestión / Account') }}</span>
                </button>
            </div>

            <!-- Designer Filter Labels / Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto max-w-full py-0.5 custom-scrollbar">
                <button 
                    type="button" 
                    wire:click="selectDesigner('all')"
                    class="px-2.5 py-1 rounded-full text-[11px] font-medium transition cursor-pointer flex items-center gap-1.5 shrink-0 border {{ $selectedDesigner === 'all' ? 'bg-zinc-900 text-white border-zinc-900 shadow-2xs font-semibold' : 'bg-white text-zinc-600 border-stone-200 hover:border-stone-400 hover:text-zinc-900' }}">
                    <span>{{ __('Todos') }}</span>
                </button>
                @foreach($designers as $designer)
                    <button 
                        type="button" 
                        wire:key="designer-filter-{{ $designer->id }}"
                        wire:click="selectDesigner('{{ $designer->id }}')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-medium transition cursor-pointer flex items-center gap-1.5 shrink-0 border {{ (string)$selectedDesigner === (string)$designer->id ? 'bg-zinc-900 text-white border-zinc-900 shadow-2xs font-semibold' : 'bg-white text-zinc-600 border-stone-200 hover:border-stone-400 hover:text-zinc-900' }}"
                        title="{{ $designer->name }}">
                        <span class="w-2 h-2 rounded-full {{ $designer->dot_color_class }} shrink-0"></span>
                        <span class="truncate max-w-[110px]">{{ $designer->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Flash Message -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Top Section Filter Cards Grid: Single Horizontal Row for all 8 buttons -->
    <div id="tour-dashboard-stats" class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2.5">
        <!-- 1. PARA HOY (Working Today Green) -->
        <button 
            id="tour-stats-today"
            wire:click="setActiveTab('today')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'today' ? 'bg-emerald-50/70 border-2 border-emerald-500 ring-4 ring-emerald-300/40 shadow-xs' : 'bg-white border-emerald-200/80 hover:border-emerald-300 hover:bg-emerald-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-emerald-900 truncate">{{ __('Para Hoy') }}</span>
                <x-lucide-pin class="w-3.5 h-3.5 text-emerald-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-emerald-950 font-mono leading-none">{{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }}</span>
        </button>

        <!-- 2. ATRASADAS -->
        <button 
            id="tour-stats-overdue"
            wire:click="setActiveTab('overdue')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'overdue' ? 'bg-red-50/70 border-2 border-red-500 ring-4 ring-red-300/40 shadow-xs' : 'bg-white border-red-200/80 hover:border-red-300 hover:bg-red-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-red-700 truncate">{{ __('Atrasadas') }}</span>
                <x-lucide-alert-circle class="w-3.5 h-3.5 text-red-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-red-700 font-mono leading-none">{{ $overdueOrders->count() }}</span>
        </button>

        <!-- 3. CAMILA -->
        <button 
            wire:click="setActiveTab('camila')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'camila' ? 'bg-purple-50/70 border-2 border-purple-500 ring-4 ring-purple-300/40 shadow-xs' : 'bg-white border-purple-200/80 hover:border-purple-300 hover:bg-purple-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-purple-900 truncate">{{ __('Camila') }}</span>
                <x-lucide-user-check class="w-3.5 h-3.5 text-purple-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-purple-900 font-mono leading-none">{{ $camilaFollowUpTasks->count() }}</span>
        </button>

        <!-- 4. RESOLVER / ACTION REQUIRED -->
        @if(!auth()->user()?->isDesigner())
            <button 
                id="tour-stats-resolver"
                wire:click="setActiveTab('resolver')" 
                class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'resolver' ? 'bg-orange-50/70 border-2 border-orange-500 ring-4 ring-orange-300/40 shadow-xs' : 'bg-white border-orange-200/80 hover:border-orange-300 hover:bg-orange-50/30' }}">
                <div class="flex items-center justify-between text-xs min-w-0">
                    <span class="font-bold text-xs text-orange-900 truncate">{{ __('Action Required') }}</span>
                    <x-lucide-shield-alert class="w-3.5 h-3.5 text-orange-600 shrink-0 ml-1" />
                </div>
                <span class="text-xl font-bold text-orange-700 font-mono leading-none">{{ $resolverOrders->count() }}</span>
            </button>
        @endif

        <!-- 5. LISTO ALTA -->
        <button 
            wire:click="setActiveTab('alta')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'alta' ? 'bg-teal-50/70 border-2 border-teal-500 ring-4 ring-teal-300/40 shadow-xs' : 'bg-white border-teal-200/80 hover:border-teal-300 hover:bg-teal-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-teal-800 truncate">{{ __('Listo ALTA') }}</span>
                <x-lucide-rocket class="w-3.5 h-3.5 text-teal-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-teal-800 font-mono leading-none">{{ $readyForAltaOrders->count() }}</span>
        </button>

        <!-- 6. PRONÓSTICO ALTA -->
        <button 
            wire:click="setActiveTab('pronostico')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'pronostico' ? 'bg-indigo-50/70 border-2 border-indigo-500 ring-4 ring-indigo-300/40 shadow-xs' : 'bg-white border-indigo-200/80 hover:border-indigo-300 hover:bg-indigo-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-indigo-900 truncate">{{ __('Pronóstico') }}</span>
                <x-lucide-trending-up class="w-3.5 h-3.5 text-indigo-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-indigo-900 font-mono leading-none">{{ $pronosticoAltaOrders->count() }}</span>
        </button>

        <!-- 7. NUEVAS TRELLO -->
        <button 
            wire:click="setActiveTab('new_orders')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'new_orders' ? 'bg-sky-50/70 border-2 border-sky-500 ring-4 ring-sky-300/40 shadow-xs' : 'bg-white border-sky-200/80 hover:border-sky-300 hover:bg-sky-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-sky-900 truncate">{{ __('Nuevas') }}</span>
                <x-lucide-sparkles class="w-3.5 h-3.5 text-sky-600 animate-pulse shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-sky-900 font-mono leading-none">{{ $newTrelloOrders->count() }}</span>
        </button>

        <!-- 8. CLIENTE -->
        <button 
            wire:click="setActiveTab('client')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'client' ? 'bg-blue-50/70 border-2 border-blue-400 ring-4 ring-blue-300/40 shadow-xs' : 'bg-white border-stone-200 hover:border-blue-300 hover:bg-stone-50' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-blue-900 truncate">{{ __('Cliente') }}</span>
                <x-lucide-mail class="w-3.5 h-3.5 text-blue-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-blue-900 font-mono leading-none">{{ $clientFollowUpTasks->count() }}</span>
        </button>
    </div>

    <!-- Main Content Area: Default Overview 4 Cards vs Single Full-Width Card -->
    @if($activeTab === 'all')
        <!-- DEFAULT OVERVIEW: 2x2 GRID OF ALL 4 CORE CARDS -->
        <div class="grid grid-cols-1 {{ auth()->user()?->isDesigner() ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }} gap-4">
            
            <!-- 1. SECTION: PARA HOY (Working Today Green Style) -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-[#e9e9e7]">
                        <h3 class="h-8 font-bold text-xs text-emerald-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-pin class="w-4 h-4 text-emerald-600" /> {{ __('Trabajo Programado Para Hoy') }} ({{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-zinc-400 font-mono">{{ __('Checkbox = Completar') }}</span>
                    </div>

                    @if($toDoTodayOrders->isEmpty() && $toDoTodayTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay trabajo programado para hoy.') }}</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            <!-- Today's Scheduled Subtasks -->
                            @foreach($toDoTodayTasks as $tTask)
                                <div wire:key="today-task-{{ $tTask->id }}" class="bg-violet-50/70 border border-violet-200 hover:border-violet-300 rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="completeTask({{ $tTask->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border border-violet-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40 transition flex items-center justify-center shrink-0 cursor-pointer"
                                            title="{{ __('Completar subtarea') }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="px-1.5 py-0.2 rounded bg-violet-700 text-white text-[9px] font-bold shrink-0">{{ __('SUBTAREA') }}</span>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate" title="{{ $tTask->title }}">{{ $tTask->title }}</h4>
                                            </div>
                                            @if($tTask->order)
                                                <p class="text-[11px] text-violet-800 font-medium truncate mt-0.5 uppercase" title="{{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}">
                                                    {{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-white text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $tTask->assignee?->name ?? __('Sin Asignar') }}
                                        </span>
                                        @if($tTask->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $tTask->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-violet-100 border border-violet-200 text-[10px] font-medium text-violet-800 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-panel-right class="w-3 h-3 text-violet-500" />
                                                <span>{{ __('Detalle') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <!-- Today's Scheduled Orders -->
                            @foreach($toDoTodayOrders as $order)
                                <div wire:key="today-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() && !$order->done_today ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-[#e9e9e7] hover:border-stone-400')) }}">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="markDoneToday({{ $order->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                            title="{{ $order->done_today ? __('Completado (Clic para desmarcar)') : __('Marcar como completado') }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-bold text-xs text-zinc-900 truncate leading-snug uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-normal text-[11px] text-zinc-500 truncate mt-0.5 uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-stone-100 text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $order->designer?->name ?? __('Sin Asignar') }}
                                        </span>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 2. SECTION: ATRASADAS -->
            <div class="bg-white border border-red-200/80 rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-red-100">
                        <h3 class="h-8 font-bold text-xs text-red-700 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-alert-circle class="w-4 h-4 text-red-600" /> {{ __('Órdenes Atrasadas') }} ({{ $overdueOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-red-700 font-semibold bg-red-50 px-2 py-0.5 rounded border border-red-200">{{ __('Overdue') }}</span>
                    </div>

                    @if($overdueOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes atrasadas en este momento.') }}</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($overdueOrders as $order)
                                <div wire:key="overdue-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-rose-50 border border-red-400 hover:border-red-500 shadow-2xs' }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.2 rounded bg-red-100 text-red-800 text-[9px] font-mono font-bold shrink-0">
                                                {{ $order->current_due_date ? $order->current_due_date->format('d M') : __('VENCIDO') }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5 uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-red-100 border border-red-200 text-[10px] font-medium text-red-800 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 3. SECTION: CAMILA -->
            <div class="bg-white border border-purple-200/80 rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-purple-100">
                        <h3 class="h-8 font-bold text-xs text-purple-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-user-check class="w-4 h-4 text-purple-600" /> {{ __('Revisiones Camila') }} ({{ $camilaFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-purple-600 font-semibold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">{{ __('Seguimiento') }}</span>
                    </div>

                    @if($camilaFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay tareas o revisiones de Camila pendientes.') }}</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($camilaFollowUpTasks as $task)
                                <div wire:key="camila-task-{{ $task->id }}" class="bg-purple-50/40 border border-purple-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-purple-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-purple-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5 uppercase">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-purple-100 border border-purple-200 text-[10px] font-medium text-purple-800 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>{{ __('Orden') }}</span>
                                            </button>
                                        @endif
                                        <button wire:click="openCamilaModal({{ $task->id }})" class="px-3 py-1 rounded bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            {{ __('Completar ✓') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. SECTION: RESOLVER / ACTION REQUIRED -->
            @if(!auth()->user()?->isDesigner())
                <div class="bg-white border border-orange-200/80 rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="h-8 flex items-center justify-between border-b border-orange-100">
                            <h3 class="h-8 font-bold text-xs text-orange-800 uppercase tracking-wider flex items-center gap-2">
                                <x-lucide-shield-alert class="w-4 h-4 text-orange-600" /> {{ __('Action Required') }} ({{ $resolverOrders->count() }})
                            </h3>
                            <span class="text-[10px] text-orange-700 font-semibold bg-orange-50 px-2 py-0.5 rounded border border-orange-200">{{ __('Bloqueos') }}</span>
                        </div>

                        @if($resolverOrders->isEmpty())
                            <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes bloqueadas que requieran resolución.') }}</p>
                        @else
                            <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                                @foreach($resolverOrders as $order)
                                    <div wire:key="resolver-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-orange-200' }}">
                                        <div class="min-w-0 flex-1 cursor-pointer" wire:click="openResolveModal({{ $order->id }})">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-normal text-xs text-zinc-500 truncate uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->location_text)
                                                    <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-stone-600 bg-stone-100 px-1.5 py-0.2 rounded border border-stone-200/90 shrink-0" title="{{ __('Locación') }}: {{ $order->location_text }}">
                                                        <x-lucide-map-pin class="w-2.5 h-2.5 text-rose-500 shrink-0" />
                                                        <span class="uppercase truncate max-w-[100px]">{{ $order->location_text }}</span>
                                                    </span>
                                                @endif
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-800 border border-orange-200 shrink-0 whitespace-nowrap">
                                                    {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : __('BLOQUEADA')) }}
                                                </span>
                                            </div>
                                            <p class="font-bold text-xs text-zinc-900 mt-0.5 truncate uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button 
                                                wire:click="openResolveModal({{ $order->id }})" 
                                                class="px-2.5 py-1 rounded bg-amber-50 hover:bg-amber-100 border border-amber-300 text-[10px] font-bold text-amber-900 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-shield-alert class="w-3.5 h-3.5 text-amber-600" />
                                                <span>{{ __('Resolver') }}</span>
                                            </button>
                                            <button 
                                                wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" 
                                                class="px-2 py-1 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer"
                                                title="{{ __('Ver detalle general') }}">
                                                <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                                <span class="hidden sm:inline">{{ __('Detalle') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    @else
        <!-- FULL-WIDTH EXPANDED VIEW WHEN A FILTER CARD IS SELECTED -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs space-y-3">
            
            <!-- 1. FULL-WIDTH: PARA HOY (Working Today Green) -->
            @if($activeTab === 'today')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-[#e9e9e7]">
                        <h3 class="h-8 font-bold text-xs text-emerald-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-pin class="w-4 h-4 text-emerald-600" /> {{ __('Trabajo Programado Para Hoy') }} ({{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-zinc-400 font-mono">{{ __('Checkbox = Completar') }}</span>
                    </div>

                    @if($toDoTodayOrders->isEmpty() && $toDoTodayTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay trabajo programado para hoy.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            <!-- Subtasks -->
                            @foreach($toDoTodayTasks as $tTask)
                                <div wire:key="today-expanded-task-{{ $tTask->id }}" class="bg-violet-50/70 border border-violet-200 hover:border-violet-300 rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="completeTask({{ $tTask->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border border-violet-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40 transition flex items-center justify-center shrink-0 cursor-pointer"
                                            title="{{ __('Completar subtarea') }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="px-1.5 py-0.2 rounded bg-violet-700 text-white text-[9px] font-bold shrink-0">{{ __('SUBTAREA') }}</span>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate" title="{{ $tTask->title }}">{{ $tTask->title }}</h4>
                                            </div>
                                            @if($tTask->order)
                                                <p class="text-[11px] text-violet-800 font-medium truncate mt-0.5 uppercase" title="{{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}">
                                                    {{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-white text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $tTask->assignee?->name ?? __('Sin Asignar') }}
                                        </span>
                                        @if($tTask->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $tTask->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-violet-100 border border-violet-200 text-[10px] font-medium text-violet-800 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-panel-right class="w-3 h-3 text-violet-500" />
                                                <span>{{ __('Detalle') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <!-- Orders -->
                            @foreach($toDoTodayOrders as $order)
                                <div wire:key="today-expanded-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() && !$order->done_today ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-[#e9e9e7] hover:border-stone-400')) }}">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="markDoneToday({{ $order->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                            title="{{ $order->done_today ? __('Completado (Clic para desmarcar)') : __('Marcar como completado') }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-bold text-xs text-zinc-900 truncate leading-snug uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-normal text-[11px] text-zinc-500 truncate mt-0.5 uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-stone-100 text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $order->designer?->name ?? __('Sin Asignar') }}
                                        </span>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 2. FULL-WIDTH: ATRASADAS -->
            @if($activeTab === 'overdue')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-red-100">
                        <h3 class="h-8 font-bold text-xs text-red-700 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-alert-circle class="w-4 h-4 text-red-600" /> {{ __('Órdenes Atrasadas') }} ({{ $overdueOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-red-700 font-semibold bg-red-50 px-2 py-0.5 rounded border border-red-200">{{ __('Overdue') }}</span>
                    </div>

                    @if($overdueOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes atrasadas en este momento.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($overdueOrders as $order)
                                <div wire:key="overdue-expanded-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-rose-50 border border-red-400 hover:border-red-500 shadow-2xs' }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.2 rounded bg-red-100 text-red-800 text-[9px] font-mono font-bold shrink-0">
                                                {{ $order->current_due_date ? $order->current_due_date->format('d M') : __('VENCIDO') }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5 uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-red-100 border border-red-200 text-[10px] font-medium text-red-800 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 3. FULL-WIDTH: CAMILA -->
            @if($activeTab === 'camila')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-purple-100">
                        <h3 class="h-8 font-bold text-xs text-purple-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-user-check class="w-4 h-4 text-purple-600" /> {{ __('Revisiones Camila') }} ({{ $camilaFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-purple-600 font-semibold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">{{ __('Seguimiento') }}</span>
                    </div>

                    @if($camilaFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay tareas o revisiones de Camila pendientes.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($camilaFollowUpTasks as $task)
                                <div wire:key="camila-expanded-task-{{ $task->id }}" class="bg-purple-50/40 border border-purple-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-purple-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-purple-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5 uppercase">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-purple-100 border border-purple-200 text-[10px] font-medium text-purple-800 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>{{ __('Orden') }}</span>
                                            </button>
                                        @endif
                                        <button wire:click="openCamilaModal({{ $task->id }})" class="px-3 py-1 rounded bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            {{ __('Completar ✓') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 4. FULL-WIDTH: RESOLVER / ACTION REQUIRED -->
            @if(!auth()->user()?->isDesigner() && $activeTab === 'resolver')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-orange-100">
                        <h3 class="h-8 font-bold text-xs text-orange-800 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-shield-alert class="w-4 h-4 text-orange-600" /> {{ __('Action Required') }} ({{ $resolverOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-orange-700 font-semibold bg-orange-50 px-2 py-0.5 rounded border border-orange-200">{{ __('Bloqueos') }}</span>
                    </div>

                    @if($resolverOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes bloqueadas que requieran resolución.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($resolverOrders as $order)
                                <div wire:key="resolver-expanded-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-orange-200' }}">
                                    <div class="min-w-0 flex-1 cursor-pointer" wire:click="openResolveModal({{ $order->id }})">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            @if($order->location_text)
                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-stone-600 bg-stone-100 px-1.5 py-0.2 rounded border border-stone-200/90 shrink-0" title="{{ __('Locación') }}: {{ $order->location_text }}">
                                                    <x-lucide-map-pin class="w-2.5 h-2.5 text-rose-500 shrink-0" />
                                                    <span class="uppercase truncate max-w-[100px]">{{ $order->location_text }}</span>
                                                </span>
                                            @endif
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-800 border border-orange-200 shrink-0 whitespace-nowrap">
                                                {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : __('BLOQUEADA')) }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 mt-0.5 truncate uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button 
                                            wire:click="openResolveModal({{ $order->id }})" 
                                            class="px-2.5 py-1 rounded bg-amber-50 hover:bg-amber-100 border border-amber-300 text-[10px] font-bold text-amber-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-shield-alert class="w-3.5 h-3.5 text-amber-600" />
                                            <span>{{ __('Resolver') }}</span>
                                        </button>
                                        <button 
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" 
                                            class="px-2 py-1 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer"
                                            title="{{ __('Ver detalle general') }}">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span class="hidden sm:inline">{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 5. FULL-WIDTH: LISTO ALTA -->
            @if($activeTab === 'alta')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-teal-100">
                        <h3 class="h-8 font-bold text-xs text-teal-800 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-rocket class="w-4 h-4 text-teal-600" /> {{ __('Órdenes Listas para ALTA') }} ({{ $readyForAltaOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-teal-700 font-semibold bg-teal-50 px-2 py-0.5 rounded border border-teal-200">{{ __('Producción') }}</span>
                    </div>

                    @if($readyForAltaOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes pendientes de poner en ALTA.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($readyForAltaOrders as $order)
                                <div wire:key="alta-expanded-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-teal-200' }}">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-normal text-xs text-zinc-500 truncate uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5 uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-teal-100 text-teal-800 text-[10px] font-semibold border border-teal-300 whitespace-nowrap">
                                            {{ __('Diseñador') }}: {{ $order->designer?->name ?? __('Sin Asignar') }}
                                        </span>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 6. FULL-WIDTH: PRONÓSTICO ALTA -->
            @if($activeTab === 'pronostico')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-indigo-100">
                        <h3 class="h-8 font-bold text-xs text-indigo-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-trending-up class="w-4 h-4 text-indigo-600" /> {{ __('Pronóstico de ALTA') }} ({{ $pronosticoAltaOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-indigo-700 font-semibold bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">{{ __('Pronóstico') }}</span>
                    </div>

                    @if($pronosticoAltaOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes enviadas a cliente esta semana en el pronóstico.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($pronosticoAltaOrders as $order)
                                <div wire:key="pronostico-expanded-order-{{ $order->id }}" class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-indigo-100 hover:border-indigo-300' }}">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600 shrink-0">
                                            <x-lucide-send class="w-3.5 h-3.5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-normal text-xs text-zinc-500 truncate leading-snug uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-bold text-xs text-zinc-900 truncate mt-0.5 uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        <div class="text-right text-[10px] hidden sm:block">
                                            <span class="text-zinc-400 block uppercase font-medium">{{ __('Enviado') }}</span>
                                            <span class="font-mono font-medium text-indigo-700">
                                                {{ ($order->last_sent_to_client_at ?? $order->updated_at) ? ($order->last_sent_to_client_at ?? $order->updated_at)->format('d M (H:i)') : __('N/A') }}
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1 shrink-0">
                                            @forelse($order->assigned_designers as $des)
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 whitespace-nowrap {{ $des->badge_style }}">
                                                    {{ $des->name }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-amber-300 bg-amber-100 text-amber-800 shrink-0">
                                                    {{ __('Sin Asignar') }}
                                                </span>
                                            @endforelse
                                        </div>

                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 7. FULL-WIDTH: NUEVAS TRELLO -->
            @if($activeTab === 'new_orders')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-sky-100">
                        <h3 class="h-8 font-bold text-xs text-sky-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-sparkles class="w-4 h-4 text-sky-600 animate-pulse" /> {{ __('Órdenes Nuevas desde Trello') }} ({{ $newTrelloOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-sky-800 font-semibold bg-sky-50 px-2 py-0.5 rounded border border-sky-200">{{ __('Trello') }}</span>
                    </div>

                    @if($newTrelloOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay órdenes nuevas de Trello pendientes en el Backlog.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($newTrelloOrders as $order)
                                <div wire:key="trello-expanded-order-{{ $order->id }}" class="bg-gradient-to-r from-sky-50/70 via-white to-cyan-50/40 border border-sky-300 rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 transition hover:border-sky-400">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-1.5 rounded-lg bg-sky-100 text-sky-700 shrink-0">
                                            <x-lucide-sparkles class="w-3.5 h-3.5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0 mb-0.5">
                                                <h4 class="font-normal text-xs text-zinc-500 truncate leading-snug uppercase" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                <span class="px-1.5 py-0.2 rounded bg-sky-100 text-sky-800 text-[9px] font-bold uppercase tracking-wider border border-sky-300 shrink-0">
                                                    {{ __('NUEVA') }}
                                                </span>
                                                @if($order->trello_card_id)
                                                    <a href="https://trello.com/c/{{ $order->trello_card_id }}" target="_blank" class="text-[10px] text-sky-600 hover:underline flex items-center gap-0.5 shrink-0" title="{{ __('Ver en Trello') }}">
                                                        <x-lucide-external-link class="w-3 h-3" />
                                                        <span>{{ __('Trello') }}</span>
                                                    </a>
                                                @endif
                                            </div>
                                            <p class="font-bold text-xs text-zinc-900 truncate uppercase" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="moveToWorkspace({{ $order->id }})" class="px-3 py-1 rounded bg-sky-600 hover:bg-sky-700 text-white font-medium text-xs transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                            <x-lucide-arrow-right class="w-3 h-3" />
                                            <span>{{ __('Mover a Workspace') }}</span>
                                        </button>
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                            <span>{{ __('Detalle') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 8. FULL-WIDTH: CLIENTE -->
            @if($activeTab === 'client')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-blue-100">
                        <h3 class="h-8 font-bold text-xs text-blue-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-mail class="w-4 h-4 text-blue-600" /> {{ __('Follow-ups Cliente') }} ({{ $clientFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-blue-700 font-semibold bg-blue-50 px-2 py-0.5 rounded border border-blue-200">{{ __('Cliente') }}</span>
                    </div>

                    @if($clientFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('No hay tareas de seguimiento con cliente pendientes.') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($clientFollowUpTasks as $task)
                                <div wire:key="client-expanded-task-{{ $task->id }}" class="bg-blue-50/40 border border-blue-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-blue-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-blue-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5 uppercase">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-blue-100 border border-blue-200 text-[10px] font-medium text-blue-800 transition flex items-center gap-1 cursor-pointer">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>{{ __('Orden') }}</span>
                                            </button>
                                        @endif
                                        <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            {{ __('Completar ✓') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <!-- Dedicated Resolution Modal for Action Required -->
    @if($showResolveModal && $resolveOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-stone-900/50 backdrop-blur-xs transition-opacity" wire:click="closeResolveModal"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-stone-200">
                    <!-- Modal Header -->
                    <div class="bg-stone-50 px-5 py-4 border-b border-stone-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center shrink-0">
                                <x-lucide-shield-alert class="w-4.5 h-4.5" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-zinc-900 truncate leading-tight">{{ __('Resolución de Bloqueo') }}</h3>
                                <p class="text-[11px] text-zinc-500 truncate mt-0.5 uppercase">{{ $resolveOrder->company_name }} — {{ $resolveOrder->task_name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeResolveModal" type="button" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-stone-200/60 transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-5 space-y-4 text-xs">
                        <!-- Current Block Info -->
                        <div class="bg-orange-50/70 border border-orange-200 rounded-xl p-3.5 space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-orange-900 uppercase tracking-wider">{{ __('Motivo Actual del Bloqueo') }}</span>
                                <span class="px-2 py-0.5 rounded-full bg-orange-200 text-orange-900 font-bold text-[10px]">
                                    {{ $resolveOrder->blocking_reason?->value ?? ($resolveOrder->substatus ? $resolveOrder->substatus->value : __('BLOQUEADA')) }}
                                </span>
                            </div>
                            @if($resolveOrder->blocking_reason_other)
                                <p class="text-xs text-orange-950 font-medium">
                                    {{ $resolveOrder->blocking_reason_other }}
                                </p>
                            @endif
                            <div class="text-[10px] text-orange-700 flex flex-wrap items-center gap-3 pt-1 border-t border-orange-200/60">
                                <span><strong>{{ __('Diseñador') }}:</strong> {{ $resolveOrder->designer?->name ?? __('Sin Asignar') }}</span>
                                @if($resolveOrder->updated_at)
                                    <span><strong>{{ __('Bloqueado desde') }}:</strong> {{ $resolveOrder->updated_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Notes / Comments -->
                        <div class="space-y-1.5">
                            <label for="resolveComment" class="block font-bold text-xs text-zinc-800">
                                {{ __('Notas de resolución o seguimiento') }} <span class="text-zinc-400 font-normal">({{ __('opcional para desbloquear, requerida para mantener') }})</span>
                            </label>
                            <textarea 
                                id="resolveComment"
                                wire:model="resolveComment" 
                                rows="3" 
                                placeholder="{{ __('Escribe cómo se resolvió el bloqueo o qué novedad hay del cliente...') }}" 
                                class="w-full bg-[#fbfbfa] border border-stone-200 rounded-xl p-2.5 text-xs text-zinc-900 focus:border-stone-400 focus:outline-none"></textarea>
                            @error('resolveComment')
                                <span class="text-[11px] text-red-600 font-medium">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Quick Links -->
                        <div class="flex items-center justify-between pt-1 text-[11px] text-zinc-500">
                            <button 
                                type="button"
                                wire:click="$dispatch('open-order-detail', { orderId: {{ $resolveOrder->id }} }); closeResolveModal();"
                                class="hover:text-zinc-800 underline inline-flex items-center gap-1 cursor-pointer">
                                <x-lucide-panel-right class="w-3.5 h-3.5" />
                                <span>{{ __('Ver detalle completo de la orden') }}</span>
                            </button>
                            @if($resolveOrder->trello_card_id)
                                <a href="https://trello.com/c/{{ $resolveOrder->trello_card_id }}" target="_blank" class="hover:text-sky-600 underline inline-flex items-center gap-1">
                                    <x-lucide-external-link class="w-3 h-3" />
                                    <span>{{ __('Ver en Trello') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-stone-50 px-5 py-3.5 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-2">
                        <button 
                            type="button" 
                            wire:click="closeResolveModal"
                            class="w-full sm:w-auto px-3.5 py-1.5 rounded-lg border border-stone-300 bg-white text-zinc-700 hover:bg-stone-100 font-medium text-xs transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <button 
                                type="button" 
                                wire:click="keepOrderBlocked"
                                class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-lg border border-orange-300 bg-orange-100 text-orange-900 hover:bg-orange-200 font-semibold text-xs transition cursor-pointer flex items-center justify-center gap-1.5">
                                <x-lucide-clock class="w-3.5 h-3.5 text-orange-700" />
                                <span>{{ __('Mantener Bloqueada') }}</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="unblockOrder"
                                class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5">
                                <x-lucide-check class="w-3.5 h-3.5 stroke-[2.5]" />
                                <span>{{ __('Desbloquear Orden') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Dedicated Review / Confirmation Modal for Camila Follow-up -->
    @if($showCamilaModal && $camilaOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="camila-modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-stone-900/50 backdrop-blur-xs transition-opacity" wire:click="closeCamilaModal"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-purple-200">
                    <!-- Modal Header -->
                    <div class="bg-purple-50/70 px-5 py-4 border-b border-purple-200/80 flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                                <x-lucide-user-check class="w-4.5 h-4.5" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-zinc-900 truncate leading-tight">{{ __('Revisión Camila') }}</h3>
                                <p class="text-[11px] text-zinc-500 truncate mt-0.5 uppercase">{{ $camilaOrder->company_name }} — {{ $camilaOrder->task_name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeCamilaModal" type="button" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-purple-100/60 transition cursor-pointer">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-5 space-y-4 text-xs">
                        <!-- Current Task Info -->
                        <div class="bg-purple-50/40 border border-purple-200/80 rounded-xl p-3.5 space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-purple-950 uppercase tracking-wider">{{ __('Tarea a Completar') }}</span>
                                <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-900 font-bold text-[10px]">
                                    {{ $camilaOrder->core_status?->value ?? 'ENVIADO_A_CAMILA' }}
                                </span>
                            </div>
                            <p class="text-xs text-purple-950 font-medium">
                                {{ $camilaTask?->title ?? __('Revisión de diseño') }}
                            </p>
                            <div class="text-[10px] text-purple-700 flex flex-wrap items-center gap-3 pt-1 border-t border-purple-200/60">
                                <span><strong>{{ __('Diseñador') }}:</strong> {{ $camilaOrder->designer?->name ?? __('Sin Asignar') }}</span>
                                <span><strong>{{ __('Revisión interna #') }}:</strong> {{ ($camilaOrder->internal_revision_count ?? 0) + 1 }}</span>
                            </div>
                        </div>

                        <!-- Instructions / Prompt -->
                        <p class="text-zinc-600 text-[11px] leading-relaxed">
                            {{ __('Confirma la acción que deseas realizar con esta orden. Puedes solicitar ajustes al diseñador, pre-aprobar para enviar prueba al cliente, o solo registrar una nota en el timeline sin alterar el estado.') }}
                        </p>

                        <!-- Comments / Notes Field -->
                        <div class="space-y-1.5">
                            <label for="camilaComment" class="block font-bold text-xs text-zinc-800">
                                {{ __('Comentario o detalles') }} <span class="text-zinc-400 font-normal">({{ __('requerido para "Solo Comentario", opcional para las demás') }})</span>
                            </label>
                            <textarea 
                                id="camilaComment"
                                wire:model="camilaComment" 
                                rows="3" 
                                placeholder="{{ __('Ej. Camila necesita conseguir más info / Cambiar tamaño de logo...') }}" 
                                class="w-full bg-[#fbfbfa] border border-stone-200 rounded-xl p-2.5 text-xs text-zinc-900 focus:border-purple-400 focus:outline-none"></textarea>
                            @error('camilaComment')
                                <span class="text-[11px] text-red-600 font-medium">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Quick Links -->
                        <div class="flex items-center justify-between pt-1 text-[11px] text-zinc-500">
                            <button 
                                type="button"
                                wire:click="$dispatch('open-order-detail', { orderId: {{ $camilaOrder->id }} }); closeCamilaModal();"
                                class="hover:text-zinc-800 underline inline-flex items-center gap-1 cursor-pointer">
                                <x-lucide-panel-right class="w-3.5 h-3.5" />
                                <span>{{ __('Ver detalle completo de la orden') }}</span>
                            </button>
                            @if($camilaOrder->trello_card_id)
                                <a href="https://trello.com/c/{{ $camilaOrder->trello_card_id }}" target="_blank" class="hover:text-sky-600 underline inline-flex items-center gap-1">
                                    <x-lucide-external-link class="w-3 h-3" />
                                    <span>{{ __('Ver en Trello') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-stone-50 px-5 py-3.5 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-2">
                        <button 
                            type="button" 
                            wire:click="closeCamilaModal"
                            class="w-full sm:w-auto px-3 py-1.5 rounded-lg border border-stone-300 bg-white text-zinc-700 hover:bg-stone-100 font-medium text-xs transition cursor-pointer">
                            {{ __('Cancelar') }}
                        </button>
                        
                        <div class="flex flex-wrap items-center justify-end gap-2 w-full sm:w-auto">
                            <!-- 1. Just add comment -->
                            <button 
                                type="button" 
                                wire:click="camilaAddComment"
                                class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg border border-purple-200 bg-purple-50 text-purple-900 hover:bg-purple-100 font-semibold text-xs transition cursor-pointer flex items-center justify-center gap-1.5"
                                title="{{ __('Mantiene la orden en Revisión Camila y agrega una nota al timeline') }}">
                                <x-lucide-message-square class="w-3.5 h-3.5 text-purple-600" />
                                <span>{{ __('Solo Comentario') }}</span>
                            </button>

                            <!-- 2. Camila requests changes -->
                            <button 
                                type="button" 
                                wire:click="camilaRequestChanges"
                                class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs transition shadow-2xs cursor-pointer flex items-center justify-center gap-1.5"
                                title="{{ __('Mueve la orden a Para Hoy con subtarea de ajustes para el diseñador') }}">
                                <x-lucide-refresh-cw class="w-3.5 h-3.5" />
                                <span>{{ __('Ajustes de Camila') }}</span>
                            </button>

                            <!-- 3. Pre-approve / Send to Client -->
                            <button 
                                type="button" 
                                wire:click="camilaPreApprove"
                                class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5"
                                title="{{ __('Crea subtarea de enviar proof al cliente pre-aprobada por Camila') }}">
                                <x-lucide-send class="w-3.5 h-3.5 stroke-[2.5]" />
                                <span>{{ __('Enviar al Cliente') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
