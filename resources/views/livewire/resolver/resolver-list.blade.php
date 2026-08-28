@php
    $totalActionCount = $blockedOrders->count() + $resolverTasks->count();
    $hasActionRequired = $totalActionCount > 0;
@endphp

<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Top Notion Header -->
    <div id="tour-resolver-header" class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs shrink-0">
        <div class="flex items-center gap-3">
            @if($hasActionRequired)
                <div class="w-9 h-9 rounded-lg bg-orange-50 border border-orange-200 flex items-center justify-center shrink-0">
                    <x-lucide-alert-triangle class="w-5 h-5 text-orange-600" />
                </div>
            @else
                <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                    <x-lucide-check-circle-2 class="w-5 h-5 text-emerald-600" />
                </div>
            @endif
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight">{{ __('Action Required') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Órdenes y tareas que requieren intervención de Manager / Admin.') }}</p>
            </div>
        </div>

        <div id="tour-resolver-cases-badge" class="flex items-center gap-2">
            @if($hasActionRequired)
                <span class="px-3 py-1 rounded-md bg-orange-50 border border-orange-200 text-orange-700 font-medium text-xs">
                    {{ $totalActionCount }} {{ __('Casos Pendientes') }}
                </span>
            @else
                <span class="px-3 py-1 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 font-medium text-xs">
                    {{ __('¡Todo al día!') }}
                </span>
            @endif
        </div>
    </div>

    @if(!$hasActionRequired)
        <!-- Empty State Card -->
        <div class="bg-white border border-[#e9e9e7] rounded-xl p-12 text-center shadow-2xs flex flex-col items-center justify-center space-y-3 my-auto">
            <div class="w-12 h-12 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center shadow-2xs">
                <x-lucide-sparkles class="w-6 h-6 text-emerald-600" />
            </div>
            <div class="space-y-1 max-w-sm">
                <h3 class="font-bold text-base text-zinc-900 tracking-tight">{{ __('Nothing here to be done') }}</h3>
                <p class="text-xs text-zinc-500 leading-relaxed">{{ __('No hay órdenes ni tareas que requieran intervención en este momento. Todo está funcionando según lo programado.') }}</p>
            </div>
        </div>
    @else
        <!-- Blocked Orders Table / Grid -->
        @if($blockedOrders->isNotEmpty())
            <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
                <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider border-b border-[#e9e9e7] pb-2">{{ __('Órdenes que no pueden avanzar normalmente') }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($blockedOrders as $order)
                        <div class="rounded-lg p-3.5 space-y-2.5 shadow-2xs {{ $order->isOverdue() ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-rose-200') }}"
                             @if($order->isOverdue()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday()) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                            <div class="flex items-start justify-between min-w-0 gap-2">
                                <div class="min-w-0 flex-1">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        {{ $order->blocking_reason?->label() ?? ($order->substatus ? $order->substatus->label() : __('BLOQUEADA')) }}
                                    </span>
                                    <h4 class="font-semibold text-xs text-zinc-900 mt-1 truncate" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
                                    <p class="text-[11px] text-zinc-500 truncate" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
                                </div>

                                <span class="px-2 py-0.5 rounded bg-stone-100 text-[10px] font-medium text-zinc-700 border border-stone-200 shrink-0 whitespace-nowrap">
                                    {{ $order->designer?->name }}
                                </span>
                            </div>

                            @if($order->blocking_reason_other)
                                <div class="p-2 rounded bg-stone-50 text-[11px] text-zinc-700 font-mono border border-stone-200">
                                    {{ __('Motivo:') }} {{ $order->blocking_reason_other }}
                                </div>
                            @endif

                            @if($order->done_today)
                                <div class="mt-2 pt-2 border-t border-emerald-200 flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block w-full">{{ __('Trabajo de hoy completado — Confirmar envío:') }}</span>
                                    <button wire:click="sendToCamila({{ $order->id }})" class="px-2 py-1 rounded bg-purple-600 hover:bg-purple-700 text-white text-[10px] font-semibold transition flex items-center gap-1 shadow-2xs">
                                        <x-lucide-send class="w-3 h-3" />
                                        <span>{{ __('Enviado a Camila') }}</span>
                                    </button>
                                    <button wire:click="sendToClient({{ $order->id }})" class="px-2 py-1 rounded bg-sky-600 hover:bg-sky-700 text-white text-[10px] font-semibold transition flex items-center gap-1 shadow-2xs">
                                        <x-lucide-send class="w-3 h-3" />
                                        <span>{{ __('Enviado al Cliente') }}</span>
                                    </button>
                                    @if($order->isApproved() || $order->substatus === \App\Enums\Substatus::PONER_EN_ALTA)
                                        <button wire:click="sendToProduction({{ $order->id }})" class="px-2 py-1 rounded bg-pink-600 hover:bg-pink-700 text-white text-[10px] font-semibold transition flex items-center gap-1 shadow-2xs">
                                            <x-lucide-factory class="w-3 h-3" />
                                            <span>{{ __('Enviado a Producción') }}</span>
                                        </button>
                                    @endif
                                    <button wire:click="keepOnPendingWork({{ $order->id }})" class="px-2 py-1 rounded bg-stone-600 hover:bg-stone-700 text-white text-[10px] font-semibold transition flex items-center gap-1 shadow-2xs">
                                        <x-lucide-clock class="w-3 h-3" />
                                        <span>{{ __('Conservar en trabajo pendiente') }}</span>
                                    </button>
                                </div>
                            @endif

                            <div class="flex items-center justify-between pt-2 border-t border-[#e9e9e7]">
                                <span class="text-[11px] text-zinc-500">{{ __('Estado:') }} <strong class="text-zinc-800">{{ $order->core_status->label() }}</strong></span>
                                <div class="flex items-center gap-1.5">
                                    <button wire:click="openUnblockModal({{ $order->id }})" class="px-2 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-[10px] font-semibold text-emerald-700 transition flex items-center gap-1">
                                        <x-lucide-unlock class="w-3 h-3 text-emerald-600" />
                                        <span>{{ __('Desbloquear') }}</span>
                                    </button>
                                    <button wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1">
                                        <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                        <span>{{ __('Detalle') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Resolver Tasks Grid -->
        @if($resolverTasks->isNotEmpty())
            <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
                <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider border-b border-[#e9e9e7] pb-2">{{ __('Tareas Pendientes de Resolución') }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($resolverTasks as $task)
                        <div class="rounded-lg p-3 bg-[#fcfcfb] border border-orange-200 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                        {{ $task->type?->label() ?? $task->type }}
                                    </span>
                                    @if($task->order)
                                        <span class="text-xs font-semibold text-zinc-900 truncate">{{ $task->order->company_name }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-700 mt-1">{{ $task->title }}</p>
                            </div>
                            @if($task->order)
                                <button wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order->id }} })" class="px-2 py-0.5 rounded bg-stone-100 hover:bg-stone-200 border border-stone-200 text-[10px] font-medium text-zinc-700 hover:text-zinc-900 transition flex items-center gap-1 shrink-0">
                                    <x-lucide-panel-right class="w-3 h-3 text-zinc-500" />
                                    <span>{{ __('Detalle') }}</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    @if($showUnblockModal && $unblockingOrder)
        <div 
            class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" 
            @keydown.window.escape.prevent="$wire.closeUnblockModal()"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.confirmUnblock()"
            wire:keydown.escape="closeUnblockModal">
            <div class="bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-w-lg w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-[#e9e9e7] pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0">
                            <x-lucide-unlock class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900">{{ __('Desbloquear Orden') }}</h3>
                            <p class="text-xs text-zinc-500">{{ $unblockingOrder->company_name }} &mdash; {{ $unblockingOrder->task_name }}</p>
                        </div>
                    </div>
                    <button wire:click="closeUnblockModal" class="text-zinc-400 hover:text-zinc-600 transition">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-medium text-zinc-700">
                        {{ __('¿Cómo se resolvió el bloqueo o qué acción se tomó?') }} <span class="text-rose-500">*</span>
                    </label>

                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="selectPresetReason('Medidas confirmadas y recibidas')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Medidas confirmadas') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Cliente aprobó información')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Cliente aprobó información') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Estimado aprobado')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Estimado aprobado') }}
                        </button>
                        <button type="button" wire:click="selectPresetReason('Resuelto por Atención a Clientes')" class="px-2 py-1 rounded bg-stone-100 hover:bg-emerald-50 hover:border-emerald-300 text-stone-700 hover:text-emerald-800 border border-stone-200 text-[11px] transition">
                            ✓ {{ __('Resuelto por Atención a Clientes') }}
                        </button>
                    </div>

                    <textarea wire:model="unblockReason" rows="3" class="w-full rounded-lg border border-stone-300 p-2.5 text-xs text-zinc-900 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder-zinc-400" placeholder="{{ __('Describe el motivo o detalles de la resolución...') }}"></textarea>

                    @error('unblockReason')
                        <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e9e9e7]">
                    <button wire:click="closeUnblockModal" type="button" class="px-3 py-1.5 rounded-lg border border-stone-200 bg-stone-50 hover:bg-stone-100 text-xs font-medium text-zinc-700 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="confirmUnblock" wire:loading.attr="disabled" type="button" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <x-lucide-check-circle-2 class="w-3.5 h-3.5" />
                        <span>{{ __('Confirmar Desbloqueo') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
