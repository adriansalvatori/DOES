<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    <div class="bg-[#f7f7f5] pb-6 space-y-4">
        <!-- Page Header -->
        <div class="border-b border-[#e9e9e7] bg-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-xl bg-red-100 text-red-600 shrink-0">
                    <x-lucide-trash-2 class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-base font-bold text-zinc-900 tracking-tight">{{ __('Papelera') }}</h1>
                    <p class="text-xs text-zinc-500">{{ __('Órdenes eliminadas — Restaura o borra permanentemente') }}</p>
                </div>
            </div>
            <a href="{{ route('kanban') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition">
                <x-lucide-arrow-left class="w-3.5 h-3.5" />
                {{ __('Volver al Kanban') }}
            </a>
        </div>

        <!-- Search Bar -->
        <div class="px-6 py-3 border-b border-[#e9e9e7] bg-white">
            <div class="relative max-w-md">
                <x-lucide-search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar en la papelera...') }}"
                    class="w-full pl-8 pr-3 py-1.5 text-xs bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg text-zinc-700 focus:outline-none focus:border-stone-400"
                />
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            @if(session('message'))
                <div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                    {{ session('message') }}
                </div>
            @endif

            @if($trashedOrders->isEmpty())
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="p-4 rounded-full bg-stone-100 mb-4">
                        <x-lucide-trash-2 class="w-8 h-8 text-zinc-400" />
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-700 mb-1">{{ __('La papelera está vacía') }}</h3>
                    <p class="text-xs text-zinc-400">{{ __('Las órdenes eliminadas aparecerán aquí antes de ser borradas permanentemente.') }}</p>
                </div>
            @else
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs text-zinc-500 font-medium">{{ $trashedOrders->count() }} {{ __('orden(es) en la papelera') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach($trashedOrders as $order)
                        <div class="bg-white border border-[#e9e9e7] rounded-xl p-3.5 shadow-sm hover:shadow-md transition group flex flex-col gap-2.5">

                            <!-- Top Row: WO + Date Trashed -->
                            <div class="flex items-center justify-between gap-2">
                                @if($order->wo_number)
                                    <span class="font-mono text-[10px] font-bold bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded border border-stone-200">
                                        {{ $order->wo_number }}
                                    </span>
                                @else
                                    <span class="font-mono text-[10px] text-zinc-400">{{ __('SIN WO') }}</span>
                                @endif
                                <span class="text-[9px] text-red-500 font-medium flex items-center gap-1">
                                    <x-lucide-clock class="w-3 h-3" />
                                    {{ $order->deleted_at->diffForHumans() }}
                                </span>
                            </div>

                            <!-- Company + Task Name -->
                            <div>
                                <h4 class="font-semibold text-xs text-zinc-800 truncate" title="{{ $order->company_name }}">
                                    {{ $order->company_name }}
                                </h4>
                                <p class="text-[11px] text-zinc-500 truncate mt-0.5" title="{{ $order->task_name }}">
                                    {{ $order->task_name }}
                                </p>
                            </div>

                            <!-- Status Badge -->
                            <div class="flex items-center gap-1.5">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-stone-100 text-zinc-600 border border-stone-200">
                                    {{ $order->core_status?->label() ?? '—' }}
                                </span>
                                @if($order->responsible_person)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] bg-indigo-50 text-indigo-700 border border-indigo-200 truncate max-w-[100px]">
                                        {{ $order->responsible_person }}
                                    </span>
                                @endif
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1.5 pt-1 border-t border-[#f0f0ee]">
                                <button
                                    wire:click="restoreOrder({{ $order->id }})"
                                    class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 hover:text-emerald-900 text-[10px] font-semibold transition"
                                    title="{{ __('Restaurar orden') }}"
                                >
                                    <x-lucide-undo-2 class="w-3 h-3" />
                                    {{ __('Restaurar') }}
                                </button>
                                <button
                                    wire:click="forceDeleteOrder({{ $order->id }})"
                                    wire:confirm="{{ __('¿Eliminar permanentemente? Esta acción no se puede deshacer.') }}"
                                    class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 hover:text-red-800 text-[10px] font-semibold transition"
                                    title="{{ __('Eliminar permanentemente') }}"
                                >
                                    <x-lucide-trash class="w-3 h-3" />
                                    {{ __('Eliminar') }}
                                </button>
                            </div>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
