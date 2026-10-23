{{-- Create Order Skeleton UI (Ultra-delicate, borderless, soft fills, zero-lag) --}}
<div class="flex flex-col w-full bg-white rounded-xl overflow-hidden select-none animate-pulse">
    <!-- Modal Header (Borderless, soft subtle fills) -->
    <div class="px-6 py-4.5 bg-white flex items-center justify-between">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-stone-900/90 flex items-center justify-center shrink-0">
                <div class="w-4 h-4 rounded-full bg-white/20"></div>
            </div>
            <div class="space-y-1.5">
                <div class="h-4 w-36 bg-stone-200/80 rounded-md"></div>
                <div class="h-3 w-56 bg-stone-100/80 rounded-md hidden sm:block"></div>
            </div>
        </div>
        <button 
            type="button" 
            @click="confirmClose(() => $wire.closeModal())" 
            class="p-1.5 text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 rounded-lg transition cursor-pointer"
            title="{{ __('Cerrar (Esc)') }}"
            aria-label="{{ __('Cerrar') }}">
            <x-lucide-x class="w-4 h-4" />
        </button>
    </div>

    <!-- Modal Body (Borderless, soft subtle filled inputs) -->
    <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto scrollbar-thin">
        <!-- Row 1: WO & Status -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
                <div class="h-3 w-16 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
            <div class="space-y-1.5">
                <div class="h-3 w-20 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
        </div>

        <!-- Row 2: Client & Location -->
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

        <!-- Row 3: Task Name (Full width) -->
        <div class="space-y-1.5">
            <div class="h-3 w-28 bg-stone-200/60 rounded-md"></div>
            <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
        </div>

        <!-- Row 4: Responsible & Due Date -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
                <div class="h-3 w-32 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
            <div class="space-y-1.5">
                <div class="h-3 w-24 bg-stone-200/60 rounded-md"></div>
                <div class="h-9 w-full bg-stone-100/70 rounded-xl"></div>
            </div>
        </div>

        <!-- Row 5: Designers (Pills) -->
        <div class="space-y-2 pt-1">
            <div class="h-3 w-36 bg-stone-200/60 rounded-md"></div>
            <div class="flex flex-wrap gap-2">
                <div class="h-7 w-20 bg-stone-100/80 rounded-lg"></div>
                <div class="h-7 w-24 bg-stone-100/80 rounded-lg"></div>
                <div class="h-7 w-20 bg-stone-100/80 rounded-lg"></div>
            </div>
        </div>

        <!-- Row 6: Pieces Section (Soft container, no borders) -->
        <div class="pt-2">
            <div class="p-4 rounded-2xl bg-stone-50/80 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
                        <div class="h-3.5 w-32 bg-stone-200/70 rounded-md"></div>
                    </div>
                    <div class="h-6 w-24 bg-stone-100 rounded-lg"></div>
                </div>
                <!-- Piece placeholder item -->
                <div class="h-10 w-full bg-white/80 rounded-xl"></div>
            </div>
        </div>
    </div>

    <!-- Modal Footer (Borderless, soft subtle background) -->
    <div class="px-6 py-4 bg-stone-50/60 flex items-center justify-between gap-3">
        <!-- Left: Trello toggle placeholder -->
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-stone-200/70"></div>
            <div class="h-3 w-28 bg-stone-200/60 rounded-md hidden sm:block"></div>
        </div>

        <!-- Right: Action buttons -->
        <div class="flex items-center gap-2.5">
            <div class="h-8 w-20 bg-stone-200/50 rounded-lg"></div>
            <div class="h-8 w-28 bg-stone-300/60 rounded-lg"></div>
        </div>
    </div>
</div>
