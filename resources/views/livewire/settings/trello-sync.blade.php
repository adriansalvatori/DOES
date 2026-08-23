<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">
    
    <!-- Top Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs shrink-0">
        <div class="flex items-center gap-3">
            <x-lucide-refresh-cw class="w-5 h-5 text-zinc-700" />
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 tracking-tight">Sincronización en Vivo con Trello</h2>
                <p class="text-xs text-zinc-500">Conecta tu tablero Trello real, importa tarjetas y gestiona la sincronización.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- Clear Demo Data Button -->
            <button wire:click="clearDemoData" wire:confirm="¿Estás seguro de eliminar todas las órdenes y tareas?" class="px-3 py-1.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-medium text-xs transition flex items-center gap-1.5">
                <x-lucide-trash-2 class="w-3.5 h-3.5 text-rose-600" />
                <span>Limpiar Datos</span>
            </button>

            <!-- Sync Button with Loading Animation -->
            <button 
                wire:click="runTrelloSync" 
                wire:loading.attr="disabled"
                class="px-3.5 py-1.5 rounded-md bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 text-white font-medium text-xs shadow-2xs transition flex items-center gap-2 cursor-pointer">
                <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="runTrelloSync" class="w-3.5 h-3.5" />
                <span wire:loading.remove wire:target="runTrelloSync">Sincronizar Desde Trello</span>
                <span wire:loading wire:target="runTrelloSync">Sincronizando con Trello...</span>
            </button>
        </div>
    </div>

    <!-- Animated Fullscreen Loading Overlay during Trello Sync -->
    <div wire:loading.flex wire:target="runTrelloSync" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl p-6 max-w-sm w-full text-center space-y-4 animate-in fade-in zoom-in duration-150">
            <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-stone-100 animate-ping opacity-75"></div>
                <div class="w-14 h-14 rounded-full bg-stone-900 text-white flex items-center justify-center shadow-lg">
                    <x-lucide-refresh-cw class="w-7 h-7 animate-spin text-emerald-400" />
                </div>
            </div>
            <div>
                <h3 class="font-bold text-sm text-zinc-900 tracking-tight">Sincronizando con Trello...</h3>
                <p class="text-xs text-zinc-500 mt-1 leading-relaxed">Conectando a la API REST de Trello, analizando listas y actualizando tarjetas en tiempo real.</p>
            </div>
        </div>
    </div>

    <!-- Interactive Sync Summary Report Modal -->
    @if($syncReport['show'])
        <div class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-stone-200 shadow-2xl max-w-xl w-full p-6 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold shadow-2xs">
                            <x-lucide-check-circle-2 class="w-5 h-5 text-emerald-600" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-zinc-900 tracking-tight">Resumen de Sincronización Trello</h3>
                            <p class="text-[11px] text-zinc-400 font-mono">{{ $syncReport['timestamp'] }}</p>
                        </div>
                    </div>
                    <button wire:click="closeReportModal" class="text-zinc-400 hover:text-zinc-700 cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Metrics Grid Report -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <button 
                        wire:click="setFilter('created')"
                        class="p-3 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'created' ? 'bg-emerald-100/70 border-emerald-400 ring-2 ring-emerald-400/30' : 'bg-emerald-50/70 border-emerald-200 hover:bg-emerald-100/50' }}">
                        <span class="text-[10px] uppercase font-bold text-emerald-800 tracking-wider block">Nuevas Importadas</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-xl font-bold text-emerald-900 font-mono">+{{ $syncReport['added'] }}</span>
                            <span class="text-[11px] text-emerald-700">tarjetas</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('moved')"
                        class="p-3 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'moved' ? 'bg-sky-100/70 border-sky-400 ring-2 ring-sky-400/30' : 'bg-sky-50/70 border-sky-200 hover:bg-sky-100/50' }}">
                        <span class="text-[10px] uppercase font-bold text-sky-800 tracking-wider block">Movidas de Estado</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-xl font-bold text-sky-900 font-mono">{{ $syncReport['moved'] }}</span>
                            <span class="text-[11px] text-sky-700">tarjetas</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('updated')"
                        class="p-3 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'updated' ? 'bg-amber-100/70 border-amber-400 ring-2 ring-amber-400/30' : 'bg-amber-50/70 border-amber-200 hover:bg-amber-100/50' }}">
                        <span class="text-[10px] uppercase font-bold text-amber-800 tracking-wider block">Información Actualizada</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-xl font-bold text-amber-900 font-mono">{{ $syncReport['updated'] }}</span>
                            <span class="text-[11px] text-amber-700">tarjetas</span>
                        </div>
                    </button>

                    <button 
                        wire:click="setFilter('deleted')"
                        class="p-3 rounded-xl border text-left transition cursor-pointer {{ $activeFilter === 'deleted' ? 'bg-rose-100/70 border-rose-400 ring-2 ring-rose-400/30' : 'bg-rose-50/70 border-rose-200 hover:bg-rose-100/50' }}">
                        <span class="text-[10px] uppercase font-bold text-rose-800 tracking-wider block">Archivadas en Trello</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-xl font-bold text-rose-900 font-mono">{{ $syncReport['deleted'] }}</span>
                            <span class="text-[11px] text-rose-700">tarjetas</span>
                        </div>
                    </button>
                </div>

                <div class="bg-stone-50 border border-stone-200 rounded-xl p-3 flex items-center justify-between text-xs font-medium text-zinc-700">
                    <span class="flex items-center gap-1.5">
                        <x-lucide-layers class="w-4 h-4 text-zinc-500" /> Total Procesadas en Tablero:
                    </span>
                    <span class="font-bold font-mono text-zinc-900">{{ $syncReport['total'] }} tarjetas</span>
                </div>

                <!-- Complete Scrollable List of Changed Cards -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
                            <x-lucide-list-checks class="w-4 h-4 text-stone-600" /> 
                            Lista de Tarjetas Que Cambiaron ({{ count($syncReport['changes']) }})
                        </span>

                        <!-- Filter Category Tabs -->
                        <div class="flex items-center gap-1 text-[11px]">
                            <button 
                                wire:click="setFilter('all')" 
                                class="px-2 py-0.5 rounded-md font-medium transition cursor-pointer {{ $activeFilter === 'all' ? 'bg-zinc-900 text-white font-semibold' : 'text-zinc-600 hover:bg-stone-100' }}">
                                Todas ({{ count($syncReport['changes']) }})
                            </button>
                        </div>
                    </div>

                    @php
                        $filteredChanges = collect($syncReport['changes'])->filter(function ($chg) use ($activeFilter) {
                            if ($activeFilter === 'all') return true;
                            return $chg['action'] === $activeFilter;
                        });
                    @endphp

                    @if($filteredChanges->isEmpty())
                        <div class="p-6 text-center text-zinc-400 bg-stone-50 rounded-xl border border-stone-200 text-xs">
                            <p>No hay tarjetas registradas en esta categoría de cambios.</p>
                        </div>
                    @else
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1 scrollbar-thin text-xs">
                            @foreach($filteredChanges as $chg)
                                <div 
                                    @if(isset($chg['order_id']))
                                        wire:click="$dispatch('open-order-detail', { orderId: {{ $chg['order_id'] }} })"
                                    @endif
                                    class="p-3 bg-[#fbfbfa] hover:bg-stone-100/90 rounded-xl border border-stone-200 flex items-center justify-between gap-3 transition cursor-pointer group shadow-2xs"
                                    title="Haz clic para ver el detalle de esta orden">
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <span class="font-bold text-zinc-900 group-hover:text-stone-900 flex items-center gap-1.5 truncate text-xs">
                                            <span>{{ $chg['company'] }}</span>
                                            <x-lucide-external-link class="w-3.5 h-3.5 text-zinc-400 group-hover:text-zinc-700 opacity-0 group-hover:opacity-100 transition shrink-0" />
                                        </span>
                                        @if($chg['task'])
                                            <span class="text-[11px] text-zinc-500 block truncate font-normal">{{ $chg['task'] }}</span>
                                        @endif
                                    </div>

                                    <div class="shrink-0 text-right">
                                        @if($chg['action'] === 'created')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                + Nueva Orden
                                            </span>
                                        @elseif($chg['action'] === 'moved')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200 flex items-center gap-1">
                                                <span>{{ $chg['previous_status'] }}</span>
                                                <x-lucide-arrow-right class="w-3 h-3 text-sky-600 inline" />
                                                <span>{{ $chg['new_status'] }}</span>
                                            </span>
                                        @elseif($chg['action'] === 'updated')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                Actualizada
                                            </span>
                                        @elseif($chg['action'] === 'deleted')
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                Archivada en Trello
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-2 border-t border-stone-100 flex justify-end">
                    <button 
                        wire:click="closeReportModal" 
                        class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white font-semibold text-xs rounded-xl shadow-2xs transition cursor-pointer">
                        Entendido / Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-lg text-xs font-medium flex items-center gap-2">
            <x-lucide-alert-octagon class="w-4 h-4 text-rose-600 shrink-0" />
            <span class="truncate">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Main Setup Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Board & Token Credentials Setup -->
        <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-4 shadow-2xs">
            <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider border-b border-[#e9e9e7] pb-2 flex items-center gap-2">
                <x-lucide-key class="w-4 h-4 text-zinc-500" /> Configuración del Tablero & Autenticación
            </h3>

            <div class="space-y-3.5 text-xs">
                <!-- Trello Board URL / ID Input -->
                <div>
                    <label class="text-zinc-700 block font-medium mb-1">1. URL o ID de tu Tablero Trello:</label>
                    <input type="text" wire:model="boardId" placeholder="Ej: https://trello.com/b/ABC123xyz/kudos-design-ops o ABC123xyz" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-2 text-xs text-zinc-900 focus:outline-none w-full font-mono">
                    <p class="text-[11px] text-zinc-400 mt-1">Pega el link de tu tablero Trello o el ID de 8 caracteres.</p>
                </div>

                <!-- Trello API Key -->
                <div>
                    <label class="text-zinc-500 block text-[10px] uppercase font-semibold">API Key Trello:</label>
                    <div class="bg-[#f7f7f5] p-2 rounded-md border border-[#e9e9e7] font-mono text-zinc-800 font-medium flex items-center justify-between">
                        <span>{{ $apiKey }}</span>
                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px]">Configurada</span>
                    </div>
                </div>

                <!-- User Token Input -->
                <div>
                    <label class="text-zinc-700 block font-medium mb-1">2. Token de Usuario Trello (Requerido para tableros privados):</label>
                    <input type="text" wire:model="userToken" placeholder="Pega aquí el Token de 64 caracteres de Trello..." class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-md px-3 py-2 text-xs text-zinc-900 focus:outline-none w-full font-mono">

                    <!-- Token Generator Banner -->
                    <div class="mt-2.5 bg-stone-100 border border-stone-200 rounded-lg p-3 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-800">¿No tienes un Token de Usuario?</span>
                            <a href="https://trello.com/1/authorize?expiration=never&name=KudosDesignOps&scope=read,write,account&response_type=token&key={{ $apiKey }}" target="_blank" class="px-2.5 py-1 rounded bg-stone-800 hover:bg-stone-700 text-white font-medium text-[11px] flex items-center gap-1 transition">
                                <x-lucide-key class="w-3 h-3 text-white shrink-0" />
                                <span>Generar Token</span>
                                <x-lucide-external-link class="w-3 h-3" />
                            </a>
                        </div>
                        <p class="text-[11px] text-zinc-500 leading-relaxed">Haz clic en Generar Token, da clic en "Permitir" en Trello y copia el Token de 64 caracteres.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Console & Rate Limits -->
        <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 space-y-3 flex flex-col justify-between shadow-2xs">
            <div>
                <h3 class="font-semibold text-xs text-zinc-800 uppercase tracking-wider border-b border-[#e9e9e7] pb-2 flex items-center gap-2">
                    <x-lucide-terminal class="w-4 h-4 text-zinc-500" /> Consola de Diagnóstico & Sincronización
                </h3>
                
                <div class="mt-3 bg-[#fbfbfa] p-3 rounded-lg border border-[#e9e9e7] font-mono text-[11px] space-y-1.5 min-h-[220px] max-h-[300px] overflow-y-auto scrollbar-thin">
                    <div class="text-zinc-400 font-medium">--- Log de Ejecución Trello REST API ---</div>
                    @forelse($syncLog as $log)
                        <div class="leading-relaxed {{ str_contains(strtolower($log), 'error') || str_contains(strtolower($log), 'fallo') ? 'text-red-600 font-semibold' : (str_contains(strtolower($log), 'exito') || str_contains(strtolower($log), 'completad') ? 'text-emerald-700 font-medium' : 'text-zinc-700') }}">
                            {{ $log }}
                        </div>
                    @empty
                        <div class="text-zinc-400 italic">Ingresa la URL de tu tablero y da clic en "Sincronizar Desde Trello".</div>
                    @endforelse
                </div>
            </div>

            <div class="p-2.5 bg-stone-50 rounded-lg border border-stone-200 text-[11px] text-zinc-600 space-y-0.5">
                <span class="font-medium text-zinc-800 flex items-center gap-1">
                    <x-lucide-info class="w-3 h-3 text-zinc-500" /> Límites de la API de Trello:
                </span>
                <p class="text-zinc-500">300 solicitudes por 10 segundos por API Key. Sincronización idempotente.</p>
            </div>
        </div>

    </div>

</div>
