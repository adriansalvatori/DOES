{{-- Client Flyout Skeleton UI (Ultra-delicate, borderless, soft fills, zero-lag) --}}
<div class="flex flex-col h-full w-full bg-white select-none animate-pulse overflow-hidden">
    <!-- Flyout Header (Borderless, soft subtle fills) -->
    <div class="px-6 py-4.5 bg-white flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-zinc-900/90 flex items-center justify-center shrink-0">
                <div class="w-4 h-4 rounded-full bg-white/20"></div>
            </div>
            <div class="space-y-1.5 min-w-0">
                <div class="h-4 w-44 bg-stone-200/80 rounded-md"></div>
                <div class="h-3 w-32 bg-stone-100/80 rounded-md"></div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button 
                type="button" 
                @click="confirmClose(() => $wire.close())" 
                class="p-1.5 text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 rounded-lg transition cursor-pointer"
                title="{{ __('Cerrar panel (Esc)') }}"
                aria-label="{{ __('Cerrar panel') }}">
                <x-lucide-x class="w-4 h-4" />
            </button>
        </div>
    </div>

    <!-- Tab Bar (Borderless pill style) -->
    <div class="px-6 py-2.5 bg-white flex items-center gap-2 overflow-x-auto scrollbar-none shrink-0">
        <div class="h-8 px-4 bg-stone-100/90 rounded-lg flex items-center gap-2 shrink-0">
            <div class="w-3.5 h-3.5 rounded bg-stone-300/80"></div>
            <div class="h-3 w-16 bg-stone-300/80 rounded-md"></div>
        </div>
        <div class="h-8 px-4 bg-stone-50 rounded-lg flex items-center gap-2 shrink-0 opacity-50">
            <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
            <div class="h-3 w-20 bg-stone-200 rounded-md"></div>
        </div>
        <div class="h-8 px-4 bg-stone-50 rounded-lg flex items-center gap-2 shrink-0 opacity-50">
            <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
            <div class="h-3 w-16 bg-stone-200 rounded-md"></div>
        </div>
        <div class="h-8 px-4 bg-stone-50 rounded-lg flex items-center gap-2 shrink-0 opacity-50">
            <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
            <div class="h-3 w-14 bg-stone-200 rounded-md"></div>
        </div>
    </div>

    <!-- Body (Borderless subtle filled fields) -->
    <div class="px-6 py-5 space-y-5 flex-1 overflow-y-auto scrollbar-thin">
        <!-- Client Name -->
        <div class="space-y-1.5">
            <div class="h-3 w-28 bg-stone-200/60 rounded-md"></div>
            <div class="h-10 w-full bg-stone-100/70 rounded-xl"></div>
        </div>

        <!-- Row: Website & Alias -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
                <div class="h-3 w-24 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
            <div class="space-y-1.5">
                <div class="h-3 w-20 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
        </div>

        <!-- Telephones Box (Soft subtle container, no border) -->
        <div class="p-4 rounded-2xl bg-stone-50/80 space-y-3">
            <div class="flex items-center justify-between">
                <div class="h-3.5 w-32 bg-stone-200/70 rounded-md"></div>
                <div class="h-6 w-20 bg-stone-100 rounded-lg"></div>
            </div>
            <div class="h-9 w-full bg-white/80 rounded-xl"></div>
        </div>

        <!-- Notes Area -->
        <div class="space-y-1.5">
            <div class="h-3 w-28 bg-stone-200/60 rounded-md"></div>
            <div class="h-20 w-full bg-stone-100/70 rounded-xl"></div>
        </div>

        <!-- Locations Box (Soft subtle container, no border) -->
        <div class="p-4 rounded-2xl bg-stone-50/80 space-y-3">
            <div class="flex items-center justify-between">
                <div class="h-3.5 w-28 bg-stone-200/70 rounded-md"></div>
                <div class="h-6 w-24 bg-stone-100 rounded-lg"></div>
            </div>
            <div class="h-12 w-full bg-white/80 rounded-xl"></div>
        </div>
    </div>

    <!-- Footer (Borderless, soft subtle background) -->
    <div class="px-6 py-4 bg-stone-50/60 flex items-center justify-between gap-3 shrink-0">
        <div class="h-4 w-28 bg-stone-200/50 rounded-md"></div>
        <div class="flex items-center gap-2.5">
            <div class="h-8 w-20 bg-stone-200/50 rounded-lg"></div>
            <div class="h-8 w-28 bg-stone-300/60 rounded-lg"></div>
        </div>
    </div>
</div>
