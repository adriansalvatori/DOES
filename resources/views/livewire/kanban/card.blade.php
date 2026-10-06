@php
    $isBlocked = $order->isBlocked() || $order->core_status === \App\Enums\CoreStatus::ENTRANTE;
    $isUrgent = $order->isUrgente();
@endphp

<div 
    wire:key="order-card-{{ $order->id }}"
    x-data="{ showTasks: false }"
    @click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
    draggable="true"
    @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
    class="kanban-card p-3 space-y-2 transition cursor-pointer active:cursor-grabbing group relative select-none hover:shadow-md {{ $isUrgent ? 'rounded-xl hover:shadow-lg ' . ($isBlocked ? 'bg-stone-100/90 border border-stone-300 text-zinc-500 opacity-60 grayscale-[50%] shadow-none ring-0' : ($order->done_today ? 'bg-[#fafaf9] border border-stone-200/90 shadow-2xs opacity-75 ring-0' : 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40')) : 'rounded-lg shadow-2xs ' . $order->getCardBgClass() }}"
    @if(!$isUrgent)
        @if($isBlocked) style="border: 1px solid #d6d3d1 !important; background-color: #f5f5f4 !important;" 
        @elseif($order->is_missing_from_trello) style="border: 1.5px dashed #a8a29e !important; background-color: #f5f5f4 !important; opacity: 0.75 !important;" 
        @elseif($order->isOverdue()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" 
        @elseif($order->isDueToday()) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" 
        @elseif($order->isApproved() || $order->isInProduction()) style="border: 1px solid #f472b6 !important; background-color: #fdf2f8 !important;" 
        @endif
    @endif
>
    <!-- Card Header: Badges & Designer -->
    <div class="flex items-start justify-between gap-1.5 min-w-0">
        <div class="flex flex-wrap gap-1 min-w-0">
            @if($order->is_missing_from_trello)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-stone-200 text-stone-700 border border-stone-300 shrink-0 whitespace-nowrap flex items-center gap-0.5">
                    <x-lucide-alert-triangle class="w-2.5 h-2.5 text-stone-600" />
                    <span>{{ __('FALTA EN TRELLO') }}</span>
                </span>
            @endif

            @if($order->wo_number)
                <x-wo-badge :number="$order->wo_number" variant="dark" />
            @endif

            @if($isUrgent)
                @if($order->done_today || $isBlocked)
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-stone-200 text-stone-600 border border-stone-300 flex items-center gap-1 shrink-0 opacity-80" title="{{ __('Urgente') }}">
                        @if($order->done_today)
                            <x-lucide-check class="w-2.5 h-2.5 text-stone-500" />
                        @endif
                        <span>{{ __('URGENTE') }}</span>
                    </span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-red-600 text-white shadow-2xs shadow-red-500/30 flex items-center gap-1.5 shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                        <span>{{ __('URGENTE') }}</span>
                    </span>
                @endif
            @elseif($order->approved && $order->substatus !== \App\Enums\Substatus::PONER_EN_ALTA)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0 whitespace-nowrap flex items-center gap-0.5 {{ $isBlocked ? 'bg-stone-200 text-stone-700 border border-stone-300' : 'bg-pink-100 text-pink-800 border border-pink-300' }}">
                    <x-lucide-check-circle-2 class="w-2.5 h-2.5 {{ $isBlocked ? 'text-stone-500' : 'text-pink-600' }}" />
                    <span>{{ __('APROBADA') }}</span>
                </span>
            @endif

            @if($order->responsible_person)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0 whitespace-nowrap flex items-center gap-1 {{ $isBlocked ? 'bg-stone-200 text-stone-700 border border-stone-300' : 'bg-indigo-50 text-indigo-800 border border-indigo-200' }}">
                    <x-lucide-user class="w-2.5 h-2.5 {{ $isBlocked ? 'text-stone-500' : 'text-indigo-600' }} shrink-0" />
                    <span>{{ $order->responsible_person }}</span>
                </span>
            @endif

            @if($order->substatus && $order->substatus->value !== 'URGENTE')
                <span class="px-1.5 py-0.5 rounded text-[9px] font-medium border shrink-0 whitespace-nowrap {{ $isBlocked ? 'bg-stone-200 text-stone-700 border-stone-300' : $order->substatus->badgeStyle() }}" style="{{ ! $isBlocked ? $order->substatus->getInlineBadgeStyle() : '' }}">
                    {{ $order->substatus->value }}
                </span>
            @endif

            @if($order->customer_service_required)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0 whitespace-nowrap {{ $isBlocked ? 'bg-stone-200 text-stone-700 border border-stone-300' : 'bg-pink-50 text-pink-700 border border-pink-200' }}">
                    {{ __('ATENCIÓN CLIENTE') }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1 shrink-0 ml-1">
            @if($order->trello_url)
                <a href="{{ $order->trello_url }}" @click.stop target="_blank" rel="noopener noreferrer" class="p-1 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition shrink-0" title="Abrir en Trello.com">
                    <x-lucide-external-link class="w-3.5 h-3.5" />
                </a>
            @endif
            <div class="flex flex-wrap items-center gap-1 shrink-0 justify-end">
                @forelse($order->assigned_designers as $des)
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 whitespace-nowrap {{ $des->badge_style }}" style="{{ $des->badge_inline_style }}">
                        {{ $des->name }}
                    </span>
                @empty
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded border border-amber-300 bg-amber-100 text-amber-800 shrink-0 whitespace-nowrap">
                        {{ __('Sin Asignar') }}
                    </span>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Card Title & Company -->
    <div class="min-w-0 flex items-start gap-2">
        <button 
            wire:click="toggleDoneToday({{ $order->id }})" 
            @click.stop
            type="button"
            class="w-4 h-4 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-emerald-500 border-emerald-500 text-white shadow-2xs' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent hover:text-emerald-500/40' }}"
            title="{{ $order->done_today ? __('Completado (Clic para desmarcar)') : __('Marcar como completado') }}">
            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
        </button>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-1.5 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                    <h4 class="font-normal text-[11px] {{ $isUrgent ? 'text-zinc-600' : 'text-zinc-500' }} truncate leading-snug min-w-0 uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                    @if($order->location_text)
                        <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-stone-600 bg-stone-100 px-1.5 py-0.2 rounded border border-stone-200/90 shrink-0 {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="Locación: {{ $order->location_text }}">
                            <x-lucide-map-pin class="w-2.5 h-2.5 text-rose-500 shrink-0" />
                            <span class="truncate max-w-[120px]">{{ $order->location_text }}</span>
                        </span>
                    @endif
                </div>
                @if($isBlocked)
                    <button 
                        wire:click="openUnblockModal({{ $order->id }})" 
                        @click.stop 
                        type="button"
                        class="px-2.5 py-1 rounded-lg bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-bold text-xs shadow-md transition flex items-center gap-1 shrink-0 cursor-pointer opacity-100 filter-none"
                        title="{{ __('Desbloquear orden') }}">
                        <x-lucide-unlock class="w-3.5 h-3.5 text-white stroke-[2.5]" />
                        <span>{{ __('Desbloquear') }}</span>
                    </button>
                @endif
            </div>
            <p class="font-bold text-xs text-zinc-900 group-hover:text-stone-800 transition truncate mt-0.5 uppercase {{ $order->done_today ? 'line-through text-zinc-400' : '' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
        </div>
    </div>

    <!-- Metadata & Due Date -->
    <div class="flex items-center justify-between text-[10px] {{ $isUrgent && !$order->done_today ? 'border-red-200/60' : ($order->done_today ? 'border-stone-200 text-zinc-500' : 'text-zinc-500 border-[#f0f0ee]') }} pt-1.5 border-t gap-1">
        <div class="flex items-center gap-1 min-w-0">
            @if($order->core_status === \App\Enums\CoreStatus::ENVIADO_AL_CLIENTE)
                @php
                    $sentDate = $order->last_sent_to_client_at ?? $order->last_meaningful_update ?? $order->updated_at ?? now();
                    $daysElapsed = $sentDate ? $sentDate->diffInWeekdays(now()) : 0;
                    $daysRemaining = max(0, 9 - $daysElapsed);
                @endphp
                <x-lucide-send class="w-3 h-3 text-sky-500 shrink-0" />
                <span class="font-mono font-medium truncate text-sky-800" title="Enviado el {{ $sentDate->format('d M, Y') }} ({{ $daysElapsed }}d hábiles transcurridos)">
                    {{ __('Enviado hace') }} {{ $daysElapsed }}d hábiles <span class="text-sky-600 font-normal">({{ $daysRemaining }}d a Hold)</span>
                </span>
            @else
                <x-lucide-calendar class="w-3.5 h-3.5 {{ $order->done_today ? 'text-zinc-400' : ($isUrgent ? 'text-red-600' : 'text-zinc-400') }} shrink-0" />
                <span class="font-mono font-medium truncate {{ $order->done_today ? 'text-zinc-500' : ($isUrgent ? 'text-red-700 font-bold' : ($order->isOverdue() ? 'text-red-600 font-bold' : ($order->isDueToday() ? 'text-amber-800 font-bold' : 'text-zinc-700'))) }}">
                    {{ $order->current_due_date ? ($order->current_due_date->isToday() ? __('Hoy') . ' (' . $order->current_due_date->format('d M') . ')' : $order->current_due_date->format('d M')) : 'N/A' }}
                </span>
            @endif
        </div>

        @if($order->relatedTasks->count() > 0)
            <button 
                @click.stop="showTasks = !showTasks"
                type="button"
                class="px-1.5 py-0.5 rounded font-semibold border flex items-center gap-1 shrink-0 whitespace-nowrap text-[10px] transition cursor-pointer {{ $order->relatedTasks->where('status', 'todo')->count() > 0 ? 'bg-amber-100 text-amber-900 border-amber-300 hover:bg-amber-200' : 'bg-stone-100 text-stone-700 border-stone-200 hover:bg-stone-200' }}"
                title="{{ __('Ver subtareas de la orden') }}">
                <x-lucide-check-square class="w-3 h-3 text-amber-700 shrink-0" />
                <span>{{ $order->relatedTasks->where('status', 'done')->count() }}/{{ $order->relatedTasks->count() }} {{ __('Tareas') }}</span>
                <x-lucide-chevron-down class="w-3 h-3 transition-transform duration-200" x-bind:class="{ 'rotate-180': showTasks }" />
            </button>
        @endif
    </div>

    <!-- Expandable Subtasks List -->
    @if($order->relatedTasks->count() > 0)
        <div x-show="showTasks" x-collapse @click.stop class="pt-2 border-t {{ $isUrgent && !$order->done_today ? 'border-red-200/60' : 'border-[#f0f0ee]' }} space-y-1.5 min-w-0">
            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider flex items-center justify-between">
                <span>{{ __('Subtareas & Acciones') }}</span>
                <span class="text-[9px] text-zinc-400 font-normal">{{ $order->relatedTasks->where('status', 'todo')->count() }} {{ __('pendientes') }}</span>
            </div>
            @foreach($order->relatedTasks as $task)
                <div class="flex items-center justify-between gap-1.5 p-1.5 rounded {{ $isUrgent ? 'bg-white/90' : 'bg-[#fbfbfa]' }} border border-stone-200 text-[11px] shadow-2xs">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <button 
                            wire:click="toggleTaskComplete({{ $task->id }})"
                            @click.stop
                            type="button" 
                            class="w-3.5 h-3.5 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $task->isDone() ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-stone-300 hover:border-emerald-500 bg-white text-transparent' }}">
                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                        </button>
                        <span class="font-medium truncate text-[11px] {{ $task->isDone() ? 'line-through text-zinc-400' : 'text-zinc-800' }}" title="{{ $task->title }}">
                            {{ $task->title }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        @if($task->type === \App\Enums\RelatedTaskType::SUBTASK)
                            <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-amber-100 text-amber-800 border border-amber-200 shrink-0">
                                {{ __('SUBTAREA') }}
                            </span>
                            <button 
                                wire:click="deleteTask({{ $task->id }})"
                                wire:confirm="{{ __('¿Eliminar esta subtarea?') }}"
                                @click.stop
                                type="button"
                                class="p-0.5 rounded text-zinc-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                title="{{ __('Eliminar subtarea') }}">
                                <x-lucide-x class="w-3 h-3" />
                            </button>
                        @else
                            <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-violet-100 text-violet-800 border border-violet-200 shrink-0">
                                {{ __('ACCION') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Quick Move Select & Modal Trigger -->
    <div class="pt-1.5 flex items-center justify-between gap-1.5 border-t min-w-0 {{ $order->done_today ? 'border-stone-200' : ($isUrgent ? 'border-red-200/60' : 'border-[#f0f0ee]') }}">
        <select wire:change="moveOrder({{ $order->id }}, $event.target.value)" @click.stop class="rounded px-1.5 py-0.5 text-[10px] focus:outline-none w-full min-w-0 truncate font-medium {{ $order->done_today ? 'bg-stone-50 border-stone-200 text-zinc-600' : ($isUrgent ? 'bg-white border-red-200 hover:border-red-300 text-zinc-800' : 'bg-[#fbfbfa] border border-[#e9e9e7] text-zinc-700') }}">
            <option value="">{{ __('Mover a...') }}</option>
            @foreach($allColumns as $colOption)
                @if($colOption !== $order->core_status)
                    <option value="{{ $colOption->value }}">{{ $colOption->label() }}</option>
                @endif
            @endforeach
        </select>

        <div class="shrink-0 flex items-center gap-1">
            <button wire:click="$dispatch('open-duplicate-order', { orderId: {{ $order->id }} })" @click.stop class="px-1.5 py-0.5 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-600' : ($isUrgent ? 'bg-white hover:bg-rose-100 border-red-200 text-zinc-700 hover:text-zinc-900' : 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-700 hover:text-zinc-900') }}" title="{{ __('Duplicar Orden') }}">
                <x-lucide-copy class="w-3 h-3 text-zinc-500" />
                <span>{{ __('Duplicar') }}</span>
            </button>
            <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" @click.stop class="p-1 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-600' : ($isUrgent ? 'bg-white hover:bg-rose-100 border-red-200 text-zinc-700 hover:text-zinc-900' : 'bg-stone-100 hover:bg-stone-200 border-stone-200 text-zinc-700 hover:text-zinc-900') }}" title="{{ __('Ver detalle de la orden') }}">
                <x-lucide-panel-right class="w-3.5 h-3.5 text-zinc-600" />
            </button>
            @if(!auth()->user()?->isSales() && !(auth()->user()?->isDesigner() && ! $order->hasNoWo()))
                <button
                    wire:click="trashOrder({{ $order->id }})"
                    @click.stop
                    wire:confirm="{{ __('¿Mover esta orden a la papelera?') }}"
                    class="px-1.5 py-0.5 rounded border text-[10px] font-medium transition flex items-center gap-1 {{ $order->done_today ? 'bg-stone-100 hover:bg-red-50 border-stone-200 text-zinc-500 hover:text-red-600' : ($isUrgent ? 'bg-white hover:bg-red-100 border-red-200 text-red-600 hover:text-red-800' : 'bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 hover:text-red-800') }}"
                    title="{{ __('Mover a la papelera') }}"
                >
                    <x-lucide-trash-2 class="w-3 h-3" />
                </button>
            @endif
        </div>
    </div>
</div>
