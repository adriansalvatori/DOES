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

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto shrink-0">
            <!-- Quick Role / Persona View Switcher -->
            <div class="inline-flex rounded-lg bg-stone-100 p-0.5 border border-stone-200 text-[11px] font-semibold shrink-0">
                <button 
                    wire:click="setUserRole('all')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 {{ $userRole === 'all' ? 'bg-white text-zinc-900 shadow-2xs' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-layout-grid class="w-3 h-3 text-zinc-400" />
                    <span>{{ __('Vista General') }}</span>
                </button>
                <button 
                    wire:click="setUserRole('designer')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 {{ $userRole === 'designer' ? 'bg-amber-500 text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-palette class="w-3 h-3" />
                    <span>{{ __('Diseñador') }}</span>
                </button>
                <button 
                    wire:click="setUserRole('manager')" 
                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1 {{ $userRole === 'manager' ? 'bg-sky-600 text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-800' }}">
                    <x-lucide-briefcase class="w-3 h-3" />
                    <span>{{ __('Gestión / Account') }}</span>
                </button>
            </div>

            <!-- Search -->
            <div class="relative flex-1 sm:flex-none w-full sm:w-60">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5 shrink-0" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar por empresa o tarea...') }}" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg pl-8 pr-3 py-1.5 h-8 text-xs text-zinc-800 focus:border-stone-400 focus:outline-none w-full">
            </div>

            <!-- Designer Filter Dropdown (Searchable) -->
            <div class="relative flex-1 sm:flex-none" 
                 x-data="{ 
                     open: false,
                     search: '',
                     selectDesigner(val) {
                         $wire.set('selectedDesigner', val);
                         this.open = false;
                     }
                 }"
                 x-dropdown-nav>
                <button 
                    type="button" 
                    @click="open = !open" 
                    @click.outside="open = false"
                    class="w-full sm:w-48 bg-[#fbfbfa] hover:bg-white border border-[#e9e9e7] hover:border-stone-300 rounded-lg px-2.5 h-8 text-xs text-zinc-700 font-medium flex items-center justify-between gap-1 truncate transition shadow-2xs">
                    <span class="truncate">
                        @if($selectedDesigner === 'all')
                            {{ __('Diseñadores (Todos)') }}
                        @else
                            {{ $designers->firstWhere('id', $selectedDesigner)?->name ?? __('Diseñadores (Todos)') }}
                        @endif
                    </span>
                    <x-lucide-chevron-down class="w-3 h-3 text-zinc-400 shrink-0" />
                </button>

                <div 
                    x-show="open" 
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute right-0 top-full mt-1 z-50 bg-white border border-[#e9e9e7] rounded-lg shadow-xl w-48 max-h-56 overflow-y-auto divide-y divide-stone-100 text-xs">
                    <div class="p-1.5 sticky top-0 bg-white border-b border-stone-100">
                        <input 
                            type="text" 
                            x-model="search" 
                            placeholder="{{ __('Buscar diseñador...') }}" 
                            class="w-full bg-stone-50 border border-stone-200 rounded px-2 py-1 text-[11px] text-zinc-800 focus:outline-none">
                    </div>
                    <button 
                        type="button"
                        @click="selectDesigner('all')" 
                        class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                        <span>{{ __('Diseñadores (Todos)') }}</span>
                        @if($selectedDesigner === 'all')
                            <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                        @endif
                    </button>
                    @foreach($designers as $designer)
                        <button 
                            type="button"
                            x-show="!search || '{{ strtolower(addslashes($designer->name)) }}'.includes(search.toLowerCase())"
                            @click="selectDesigner('{{ $designer->id }}')" 
                            class="w-full text-left p-2 hover:bg-stone-100 focus:bg-stone-100 focus:outline-none cursor-pointer font-medium text-zinc-800 transition flex items-center justify-between">
                            <span class="truncate">{{ $designer->name }}</span>
                            @if((string)$selectedDesigner === (string)$designer->id)
                                <x-lucide-check class="w-3.5 h-3.5 text-emerald-600 stroke-[3] shrink-0" />
                            @endif
                        </button>
                    @endforeach
                </div>
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
    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2.5">
        <!-- 1. PARA HOY -->
        <button 
            wire:click="setActiveTab('today')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'today' ? 'bg-amber-50/70 border-2 border-amber-400 ring-4 ring-amber-300/40 shadow-xs' : 'bg-white border-amber-200/60 hover:border-amber-300 hover:bg-amber-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-amber-900 truncate">{{ __('Para Hoy') }}</span>
                <x-lucide-pin class="w-3.5 h-3.5 text-amber-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-amber-950 font-mono leading-none">{{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }}</span>
        </button>

        <!-- 2. ATRASADAS -->
        <button 
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

        <!-- 4. RESOLVER -->
        <button 
            wire:click="setActiveTab('resolver')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'resolver' ? 'bg-orange-50/70 border-2 border-orange-500 ring-4 ring-orange-300/40 shadow-xs' : 'bg-white border-orange-200/80 hover:border-orange-300 hover:bg-orange-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-orange-900 truncate">{{ __('Action Required') }}</span>
                <x-lucide-shield-alert class="w-3.5 h-3.5 text-orange-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-orange-700 font-mono leading-none">{{ $resolverOrders->count() }}</span>
        </button>

        <!-- 5. LISTO ALTA -->
        <button 
            wire:click="setActiveTab('alta')" 
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'alta' ? 'bg-emerald-50/70 border-2 border-emerald-500 ring-4 ring-emerald-300/40 shadow-xs' : 'bg-white border-emerald-200/80 hover:border-emerald-300 hover:bg-emerald-50/30' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-emerald-800 truncate">{{ __('Listo ALTA') }}</span>
                <x-lucide-rocket class="w-3.5 h-3.5 text-emerald-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-emerald-800 font-mono leading-none">{{ $readyForAltaOrders->count() }}</span>
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
            class="p-3 rounded-2xl border text-left transition flex flex-col justify-between h-20 cursor-pointer select-none {{ $activeTab === 'client' ? 'bg-sky-50/70 border-2 border-sky-400 ring-4 ring-sky-300/40 shadow-xs' : 'bg-white border-stone-200 hover:border-sky-300 hover:bg-stone-50' }}">
            <div class="flex items-center justify-between text-xs min-w-0">
                <span class="font-bold text-xs text-sky-900 truncate">{{ __('Cliente') }}</span>
                <x-lucide-mail class="w-3.5 h-3.5 text-sky-600 shrink-0 ml-1" />
            </div>
            <span class="text-xl font-bold text-sky-900 font-mono leading-none">{{ $clientFollowUpTasks->count() }}</span>
        </button>
    </div>

    <!-- Main Content Area: Default Overview 4 Cards vs Single Full-Width Card -->
    @if($activeTab === 'all')
        <!-- DEFAULT OVERVIEW: 2x2 GRID OF ALL 4 CORE CARDS -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            
            <!-- 1. SECTION: PARA HOY -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-[#e9e9e7]">
                        <h3 class="h-8 font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-pin class="w-4 h-4 text-amber-600" /> {{ __('Trabajo Programado Para Hoy') }} ({{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-zinc-400 font-mono">{{ __('Checkbox = Completar') }}</span>
                    </div>

                    @if($toDoTodayOrders->isEmpty() && $toDoTodayTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay trabajo programado para hoy.</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            <!-- Today's Scheduled Subtasks -->
                            @foreach($toDoTodayTasks as $tTask)
                                <div class="bg-violet-50/70 border border-violet-200 hover:border-violet-300 rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="completeTask({{ $tTask->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border border-violet-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40 transition flex items-center justify-center shrink-0 cursor-pointer"
                                            title="Completar subtarea">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="px-1.5 py-0.2 rounded bg-violet-700 text-white text-[9px] font-bold shrink-0">SUBTAREA</span>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate" title="{{ $tTask->title }}">{{ $tTask->title }}</h4>
                                            </div>
                                            @if($tTask->order)
                                                <p class="text-[11px] text-violet-800 font-medium truncate mt-0.5" title="{{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}">
                                                    {{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-white text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $tTask->assignee?->name ?? 'Sin Asignar' }}
                                        </span>
                                        @if($tTask->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $tTask->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-violet-100 border border-violet-200 text-[10px] font-medium text-violet-800 transition flex items-center gap-1">
                                                <x-lucide-panel-right class="w-3 h-3 text-violet-500" />
                                                <span>Detalle</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <!-- Today's Scheduled Orders -->
                            @foreach($toDoTodayOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() && !$order->done_today ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-[#e9e9e7] hover:border-stone-400')) }}"
                                     @if($order->isOverdue() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="markDoneToday({{ $order->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                            title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-bold text-xs text-zinc-900 truncate leading-snug {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-normal text-[11px] text-zinc-500 truncate mt-0.5 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
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
            </div>

            <!-- 2. SECTION: ATRASADAS -->
            <div class="bg-white border border-red-200/80 rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-red-100">
                        <h3 class="h-8 font-bold text-xs text-red-700 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-alert-circle class="w-4 h-4 text-red-600" /> Órdenes Atrasadas ({{ $overdueOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-red-700 font-semibold bg-red-50 px-2 py-0.5 rounded border border-red-200">Overdue</span>
                    </div>

                    @if($overdueOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes atrasadas en este momento.</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($overdueOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-rose-50 border border-red-400 hover:border-red-500 shadow-2xs' }}"
                                     @if(!$order->isUrgente()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @endif>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.2 rounded bg-red-100 text-red-800 text-[9px] font-mono font-bold shrink-0">
                                                {{ $order->current_due_date ? $order->current_due_date->format('d M') : 'VENCIDO' }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-red-100 border border-red-200 text-[10px] font-medium text-red-800 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3" />
                                            <span>Detalle</span>
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
                            <x-lucide-user-check class="w-4 h-4 text-purple-600" /> Revisiones Camila ({{ $camilaFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-purple-600 font-semibold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">Seguimiento</span>
                    </div>

                    @if($camilaFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay tareas o revisiones de Camila pendientes.</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($camilaFollowUpTasks as $task)
                                <div class="bg-purple-50/40 border border-purple-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-purple-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-purple-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-purple-100 border border-purple-200 text-[10px] font-medium text-purple-800 transition flex items-center gap-1">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>Orden</span>
                                            </button>
                                        @endif
                                        <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            Completar ✓
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. SECTION: RESOLVER -->
            <div class="bg-white border border-orange-200/80 rounded-2xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-orange-100">
                        <h3 class="h-8 font-bold text-xs text-orange-800 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-shield-alert class="w-4 h-4 text-orange-600" /> {{ __('Action Required') }} ({{ $resolverOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-orange-700 font-semibold bg-orange-50 px-2 py-0.5 rounded border border-orange-200">Bloqueos</span>
                    </div>

                    @if($resolverOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('Nothing here to be done') }}</p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($resolverOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-orange-200' }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-800 border border-orange-200 shrink-0 whitespace-nowrap">
                                                {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : 'BLOQUEADA') }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 mt-0.5 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
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
            </div>

        </div>
    @else
        <!-- FULL-WIDTH EXPANDED VIEW WHEN A FILTER CARD IS SELECTED -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4 shadow-2xs space-y-3">
            
            <!-- 1. FULL-WIDTH: PARA HOY -->
            @if($activeTab === 'today')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-[#e9e9e7]">
                        <h3 class="h-8 font-bold text-xs text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-pin class="w-4 h-4 text-amber-600" /> Trabajo Programado Para Hoy ({{ $toDoTodayOrders->count() + $toDoTodayTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-zinc-400 font-mono">Checkbox = Completar</span>
                    </div>

                    @if($toDoTodayOrders->isEmpty() && $toDoTodayTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay trabajo programado para hoy.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            <!-- Subtasks -->
                            @foreach($toDoTodayTasks as $tTask)
                                <div class="bg-violet-50/70 border border-violet-200 hover:border-violet-300 rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="completeTask({{ $tTask->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border border-violet-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40 transition flex items-center justify-center shrink-0 cursor-pointer"
                                            title="Completar subtarea">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="px-1.5 py-0.2 rounded bg-violet-700 text-white text-[9px] font-bold shrink-0">SUBTAREA</span>
                                                <h4 class="font-bold text-xs text-zinc-900 truncate" title="{{ $tTask->title }}">{{ $tTask->title }}</h4>
                                            </div>
                                            @if($tTask->order)
                                                <p class="text-[11px] text-violet-800 font-medium truncate mt-0.5" title="{{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}">
                                                    {{ $tTask->order->company_name }} — {{ $tTask->order->task_name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-white text-[10px] font-medium text-zinc-600 border border-stone-200 whitespace-nowrap">
                                            {{ $tTask->assignee?->name ?? 'Sin Asignar' }}
                                        </span>
                                        @if($tTask->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $tTask->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-violet-100 border border-violet-200 text-[10px] font-medium text-violet-800 transition flex items-center gap-1">
                                                <x-lucide-panel-right class="w-3 h-3 text-violet-500" />
                                                <span>Detalle</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <!-- Orders -->
                            @foreach($toDoTodayOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : ($order->isOverdue() && !$order->done_today ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() && !$order->done_today ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-[#e9e9e7] hover:border-stone-400')) }}"
                                     @if($order->isOverdue() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday() && !$order->isUrgente() && !$order->done_today) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button 
                                            wire:click="markDoneToday({{ $order->id }})" 
                                            type="button"
                                            class="w-4.5 h-4.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
                                            title="{{ $order->done_today ? 'Completado (Clic para desmarcar)' : 'Marcar como completado' }}">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-bold text-xs text-zinc-900 truncate leading-snug {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-normal text-[11px] text-zinc-500 truncate mt-0.5 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
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

            <!-- 2. FULL-WIDTH: ATRASADAS -->
            @if($activeTab === 'overdue')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-red-100">
                        <h3 class="h-8 font-bold text-xs text-red-700 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-alert-circle class="w-4 h-4 text-red-600" /> Órdenes Atrasadas ({{ $overdueOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-red-700 font-semibold bg-red-50 px-2 py-0.5 rounded border border-red-200">Overdue</span>
                    </div>

                    @if($overdueOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes atrasadas en este momento.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($overdueOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-rose-50 border border-red-400 hover:border-red-500 shadow-2xs' }}"
                                     @if(!$order->isUrgente()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @endif>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.2 rounded bg-red-100 text-red-800 text-[9px] font-mono font-bold shrink-0">
                                                {{ $order->current_due_date ? $order->current_due_date->format('d M') : 'VENCIDO' }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-red-100 border border-red-200 text-[10px] font-medium text-red-800 transition flex items-center gap-1">
                                            <x-lucide-panel-right class="w-3 h-3" />
                                            <span>Detalle</span>
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
                            <x-lucide-user-check class="w-4 h-4 text-purple-600" /> Revisiones Camila ({{ $camilaFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-purple-600 font-semibold bg-purple-50 px-2 py-0.5 rounded border border-purple-200">Seguimiento</span>
                    </div>

                    @if($camilaFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay tareas o revisiones de Camila pendientes.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($camilaFollowUpTasks as $task)
                                <div class="bg-purple-50/40 border border-purple-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-purple-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-purple-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-purple-100 border border-purple-200 text-[10px] font-medium text-purple-800 transition flex items-center gap-1">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>Orden</span>
                                            </button>
                                        @endif
                                        <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            Completar ✓
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- 4. FULL-WIDTH: RESOLVER -->
            @if($activeTab === 'resolver')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-orange-100">
                        <h3 class="h-8 font-bold text-xs text-orange-800 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-shield-alert class="w-4 h-4 text-orange-600" /> {{ __('Action Required') }} ({{ $resolverOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-orange-700 font-semibold bg-orange-50 px-2 py-0.5 rounded border border-orange-200">Bloqueos</span>
                    </div>

                    @if($resolverOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">{{ __('Nothing here to be done') }}</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($resolverOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-orange-200' }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <h4 class="font-normal text-xs text-zinc-500 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-800 border border-orange-200 shrink-0 whitespace-nowrap">
                                                {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : 'BLOQUEADA') }}
                                            </span>
                                        </div>
                                        <p class="font-bold text-xs text-zinc-900 mt-0.5 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
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

            <!-- 5. FULL-WIDTH: LISTO ALTA -->
            @if($activeTab === 'alta')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-emerald-100">
                        <h3 class="h-8 font-bold text-xs text-emerald-800 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-rocket class="w-4 h-4 text-emerald-600" /> Órdenes Listas para ALTA ({{ $readyForAltaOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Producción</span>
                    </div>

                    @if($readyForAltaOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes pendientes de poner en ALTA.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($readyForAltaOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-emerald-200' }}">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-normal text-xs text-zinc-500 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                        <p class="font-bold text-xs text-zinc-900 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-semibold border border-emerald-300 whitespace-nowrap">
                                            Diseñador: {{ $order->designer?->name ?? 'Sin Asignar' }}
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

            <!-- 6. FULL-WIDTH: PRONÓSTICO ALTA -->
            @if($activeTab === 'pronostico')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-indigo-100">
                        <h3 class="h-8 font-bold text-xs text-indigo-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-trending-up class="w-4 h-4 text-indigo-600" /> Pronóstico de ALTA ({{ $pronosticoAltaOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-indigo-700 font-semibold bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">Pronóstico</span>
                    </div>

                    @if($pronosticoAltaOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes enviadas a cliente esta semana en el pronóstico.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($pronosticoAltaOrders as $order)
                                <div class="rounded-xl p-3 flex items-center justify-between gap-3 transition min-w-0 {{ $order->isUrgente() ? ($order->done_today ? 'bg-[#fafaf9] border border-stone-200 opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40') : 'bg-[#fcfcfb] border border-indigo-100 hover:border-indigo-300' }}">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600 shrink-0">
                                            <x-lucide-send class="w-3.5 h-3.5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <h4 class="font-normal text-xs text-zinc-500 truncate leading-snug" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                @if($order->substatus)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $order->substatus->badgeStyle() }}">
                                                        {{ $order->substatus->value }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="font-bold text-xs text-zinc-900 truncate mt-0.5" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        <div class="text-right text-[10px] hidden sm:block">
                                            <span class="text-zinc-400 block uppercase font-medium">Enviado</span>
                                            <span class="font-mono font-medium text-indigo-700">{{ $order->updated_at ? $order->updated_at->format('d M (H:i)') : 'N/A' }}</span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1 shrink-0">
                                            @forelse($order->assigned_designers as $des)
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 whitespace-nowrap {{ $des->badge_style }}">
                                                    {{ $des->name }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-amber-300 bg-amber-100 text-amber-800 shrink-0">
                                                    Sin Asignar
                                                </span>
                                            @endforelse
                                        </div>

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

            <!-- 7. FULL-WIDTH: NUEVAS TRELLO -->
            @if($activeTab === 'new_orders')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-sky-100">
                        <h3 class="h-8 font-bold text-xs text-sky-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-sparkles class="w-4 h-4 text-sky-600 animate-pulse" /> New Orders from Trello ({{ $newTrelloOrders->count() }})
                        </h3>
                        <span class="text-[10px] text-sky-800 font-semibold bg-sky-50 px-2 py-0.5 rounded border border-sky-200">Trello</span>
                    </div>

                    @if($newTrelloOrders->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay órdenes nuevas de Trello pendientes en el Backlog.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($newTrelloOrders as $order)
                                <div class="bg-gradient-to-r from-sky-50/70 via-white to-cyan-50/40 border border-sky-300 rounded-xl p-3 flex items-center justify-between gap-3 min-w-0 transition hover:border-sky-400">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-1.5 rounded-lg bg-sky-100 text-sky-700 shrink-0">
                                            <x-lucide-sparkles class="w-3.5 h-3.5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 min-w-0 mb-0.5">
                                                <h4 class="font-normal text-xs text-zinc-500 truncate leading-snug" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                                <span class="px-1.5 py-0.2 rounded bg-sky-100 text-sky-800 text-[9px] font-bold uppercase tracking-wider border border-sky-300 shrink-0">
                                                    NUEVA
                                                </span>
                                                @if($order->trello_card_id)
                                                    <a href="https://trello.com/c/{{ $order->trello_card_id }}" target="_blank" class="text-[10px] text-sky-600 hover:underline flex items-center gap-0.5 shrink-0" title="Ver en Trello">
                                                        <x-lucide-external-link class="w-3 h-3" />
                                                        <span>Trello</span>
                                                    </a>
                                                @endif
                                            </div>
                                            <p class="font-bold text-xs text-zinc-900 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="moveToWorkspace({{ $order->id }})" class="px-3 py-1 rounded bg-sky-600 hover:bg-sky-700 text-white font-medium text-xs transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                            <x-lucide-arrow-right class="w-3 h-3" />
                                            <span>Mover a Workspace</span>
                                        </button>
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

            <!-- 8. FULL-WIDTH: CLIENTE -->
            @if($activeTab === 'client')
                <div class="space-y-3">
                    <div class="h-8 flex items-center justify-between border-b border-sky-100">
                        <h3 class="h-8 font-bold text-xs text-sky-900 uppercase tracking-wider flex items-center gap-2">
                            <x-lucide-mail class="w-4 h-4 text-sky-600" /> Follow-ups Cliente ({{ $clientFollowUpTasks->count() }})
                        </h3>
                        <span class="text-[10px] text-sky-700 font-semibold bg-sky-50 px-2 py-0.5 rounded border border-sky-200">Cliente</span>
                    </div>

                    @if($clientFollowUpTasks->isEmpty())
                        <p class="text-xs text-zinc-400 text-center py-12">No hay tareas de seguimiento con cliente pendientes.</p>
                    @else
                        <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1 scrollbar-thin">
                            @foreach($clientFollowUpTasks as $task)
                                <div class="bg-sky-50/40 border border-sky-200 rounded-xl p-3 flex items-center justify-between text-xs gap-3 min-w-0 hover:border-sky-300 transition">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-sky-950 block text-xs truncate">{{ $task->title }}</span>
                                        <span class="text-zinc-500 text-[11px] truncate block mt-0.5">{{ $task->order?->company_name }} — {{ $task->order?->task_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($task->order)
                                            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-white hover:bg-sky-100 border border-sky-200 text-[10px] font-medium text-sky-800 transition flex items-center gap-1">
                                                <x-lucide-panel-right class="w-3 h-3" />
                                                <span>Orden</span>
                                            </button>
                                        @endif
                                        <button wire:click="completeTask({{ $task->id }})" class="px-3 py-1 rounded bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                            Completar ✓
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
</div>
</div>
