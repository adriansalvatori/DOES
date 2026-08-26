<div class="w-full">
    <!-- Toggle Button -->
    <button 
        wire:click="togglePause" 
        type="button"
        title="{{ $isPaused ? __('Sincronización con Trello Pausada (Clic para Reactivar)') : __('Sincronización con Trello Activa (Clic para Pausar)') }}"
        class="w-full px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center justify-between transition-colors border {{ $isPaused ? 'bg-amber-50/80 text-amber-900 border-amber-200/80 hover:bg-amber-100/80' : 'bg-stone-50/80 text-zinc-700 border-stone-200/60 hover:bg-stone-100/80' }}">
        
        <div class="flex items-center gap-2 min-w-0">
            @if($isPaused)
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <x-lucide-pause-circle class="w-3.5 h-3.5 text-amber-600 shrink-0" />
            @else
                <span class="inline-flex rounded-full h-2 w-2 bg-emerald-500 shrink-0"></span>
                <x-lucide-refresh-cw class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
            @endif

            <span x-show="sidebarOpen" x-transition.opacity class="truncate text-[11px] font-medium">
                {{ $isPaused ? __('Sync Trello: Pausado') : __('Sync Trello: Activo') }}
            </span>
        </div>

        <div x-show="sidebarOpen" x-transition.opacity class="shrink-0 ml-1">
            <!-- Inline Mini Toggle Switch -->
            <div class="w-7 h-4 rounded-full p-0.5 transition-colors duration-200 ease-in-out flex items-center {{ $isPaused ? 'bg-amber-500 justify-start' : 'bg-emerald-500 justify-end' }}">
                <div class="w-3 h-3 rounded-full bg-white shadow-xs transition-all duration-200 ease-in-out {{ $isPaused ? 'ml-0' : 'ml-auto' }}"></div>
            </div>
        </div>
    </button>

    <!-- Reactivation Prompt Banner -->
    @if($showReactivationPrompt)
        <div x-show="sidebarOpen" x-transition.opacity class="mt-1.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-900 flex flex-col gap-1">
            <div class="flex items-center justify-between">
                <span class="font-semibold flex items-center gap-1">
                    <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600" />
                    {{ __('Sync Reactivado') }}
                </span>
                <button wire:click="dismissPrompt" class="text-emerald-700 hover:text-emerald-900 font-bold px-1">✕</button>
            </div>
            <p class="text-[10px] text-emerald-800 leading-tight">
                {{ __('Las acciones futuras se enviarán a Trello.') }}
            </p>
            <a href="/trello-sync" class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-medium text-emerald-700 hover:text-emerald-900 underline">
                <x-lucide-arrow-right class="w-3 h-3" />
                {{ __('Ir a Conciliar / Sincronizar Tablero') }}
            </a>
        </div>
    @endif
</div>
