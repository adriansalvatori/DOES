<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Top Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs shrink-0">
        <div class="flex items-center gap-3">
            <x-lucide-alert-triangle class="w-5 h-5 text-rose-600" />
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight">Vista Resolver / Intervención Operativa</h2>
                <p class="text-xs text-zinc-500">Órdenes y tareas que requieren intervención de Manager / Admin.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-md bg-rose-50 border border-rose-200 text-rose-700 font-medium text-xs">
                {{ $blockedOrders->count() }} Casos Pendientes de Resolver
            </span>
        </div>
    </div>

    <!-- Blocked Orders Table / Grid -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs">
        <h3 class="font-semibold text-xs text-zinc-700 uppercase tracking-wider border-b border-[#e9e9e7] pb-2">Órdenes que no pueden avanzar normalmente</h3>

        @if($blockedOrders->isEmpty())
            <div class="text-center py-10 text-xs text-zinc-400 flex items-center justify-center gap-2">
                <x-lucide-sparkles class="w-4 h-4 text-emerald-600" />
                <span>¡No hay órdenes en estado de bloqueo o intervención!</span>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($blockedOrders as $order)
                    <div class="rounded-lg p-3.5 space-y-2.5 shadow-2xs {{ $order->isOverdue() ? 'bg-rose-50 border border-red-400' : ($order->isDueToday() ? 'bg-amber-50 border border-amber-300' : 'bg-[#fcfcfb] border border-rose-200') }}"
                         @if($order->isOverdue()) style="border: 1px solid #ef4444 !important; background-color: #fef2f2 !important;" @elseif($order->isDueToday()) style="border: 1px solid #f59e0b !important; background-color: #fffbeb !important;" @endif>
                        <div class="flex items-start justify-between min-w-0 gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    {{ $order->blocking_reason?->value ?? ($order->substatus ? $order->substatus->value : 'BLOQUEADA') }}
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
                                Motivo: {{ $order->blocking_reason_other }}
                            </div>
                        @endif

                        <div class="flex items-center justify-between pt-2 border-t border-[#e9e9e7]">
                            <span class="text-[11px] text-zinc-500">Estado: <strong class="text-zinc-800">{{ $order->core_status->label() }}</strong></span>
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
