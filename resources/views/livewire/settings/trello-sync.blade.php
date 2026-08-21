<div class="space-y-5">
    
    <!-- Top Notion Header -->
    <div class="bg-white border border-[#e9e9e7] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xs">
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

            <!-- Sync Button -->
            <button wire:click="runTrelloSync" class="px-3.5 py-1.5 rounded-md bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs shadow-2xs transition flex items-center gap-1.5">
                <x-lucide-refresh-cw class="w-3.5 h-3.5" />
                <span>Sincronizar Desde Trello</span>
            </button>
        </div>
    </div>

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
                                <span>🔑 Generar Token</span>
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
                        <div class="leading-relaxed {{ str_contains($log, '❌') ? 'text-red-600 font-semibold' : (str_contains($log, '🎉') || str_contains($log, '✓') ? 'text-emerald-700 font-medium' : 'text-zinc-700') }}">
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
