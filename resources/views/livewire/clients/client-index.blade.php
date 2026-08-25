<div class="h-full flex flex-col space-y-3 min-h-0 max-w-xl mx-auto w-full">
    
    <!-- Notion Header Controls (Compact Width) -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs shrink-0">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <x-lucide-building-2 class="w-4 h-4 text-stone-100" />
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="text-xs sm:text-sm font-bold text-zinc-900 tracking-tight">{{ __('Base de Datos de Clientes') }}</h1>
                    <span class="px-2 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-[10px] font-bold text-zinc-600">{{ $totalClientsCount }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
            <!-- Searchbar -->
            <div class="relative flex-1 sm:w-48">
                <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-2" />
                <input 
                    type="text" 
                    wire:model.live.debounce.200ms="search" 
                    placeholder="{{ __('Buscar cliente...') }}" 
                    class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md pl-7 pr-2.5 py-1 text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-stone-400 w-full"
                >
            </div>

            <!-- Nuevo Cliente -->
            <button 
                type="button" 
                wire:click="openClientDetail"
                class="px-2.5 py-1 h-7 rounded-md bg-stone-900 hover:bg-stone-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1 cursor-pointer shrink-0"
            >
                <x-lucide-plus class="w-3 h-3 text-stone-100" />
                <span>{{ __('Nuevo') }}</span>
            </button>
        </div>
    </div>

    <!-- Notification Flash Message -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-2.5 rounded-lg text-xs font-medium flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 cursor-pointer">
                <x-lucide-x class="w-3 h-3" />
            </button>
        </div>
    @endif

    <!-- Ultra-Compact Slim Client List Card -->
    <div class="flex-1 min-h-0 bg-white border border-[#e9e9e7] rounded-xl shadow-2xs flex flex-col overflow-hidden">
        <div class="flex-1 min-h-0 overflow-y-auto custom-vertical-scrollbar">
            <table class="w-full text-left text-xs text-zinc-700">
                <thead class="bg-[#f7f7f5] text-zinc-500 font-semibold border-b border-[#e9e9e7] uppercase text-[10px] tracking-wider sticky top-0 z-10">
                    <tr>
                        <th class="py-2 px-3.5">{{ __('Cliente') }}</th>
                        <th class="py-2 px-3.5 text-right w-36">{{ __('Órdenes Activas') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e9e9e7]">
                    @forelse($clients as $client)
                        {{-- Clickable Row --}}
                        <tr 
                            wire:click="openClientDetail({{ $client->id }})" 
                            class="hover:bg-[#f7f7f5] transition cursor-pointer"
                        >
                            <td class="py-2.5 px-3.5">
                                <span class="font-bold text-zinc-900 text-xs tracking-tight uppercase">
                                    {{ $client->name }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3.5 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 inline-flex items-center gap-1">
                                    <x-lucide-zap class="w-3 h-3 text-emerald-600" />
                                    <span>{{ $client->active_orders_count }} activas</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="p-8 text-center text-zinc-400 italic">
                                <div class="flex flex-col items-center gap-1.5">
                                    <x-lucide-building-2 class="w-6 h-6 text-zinc-300" />
                                    <p class="text-xs font-medium text-zinc-600">{{ __('No hay clientes en Workspace.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-3.5 py-2 bg-[#fbfbfa] border-t border-[#e9e9e7] text-[11px] text-zinc-400 font-medium flex items-center justify-between shrink-0">
            <span>{{ __('Mostrando :count clientes de Workspace', ['count' => $clients->count()]) }}</span>
            <span class="text-zinc-400 text-[10px]">{{ __('Haz clic en cualquier fila para abrir') }}</span>
        </div>
    </div>

    <!-- Slide-over Flyout Panel -->
    <livewire:clients.client-flyout-panel />
</div>
