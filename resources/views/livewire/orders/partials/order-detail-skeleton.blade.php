{{-- Order Detail Skeleton UI (Ultra-delicate, borderless, soft fills, zero-lag) --}}
<div class="flex flex-col h-full w-full bg-white overflow-hidden select-none animate-pulse">
    <!-- Header -->
    <div class="px-5 sm:px-7 py-5 bg-white shrink-0 space-y-4">
        <!-- Top Action Bar -->
        <div class="flex items-center justify-between gap-3 min-w-0">
            <!-- Left: WO & Date Pill -->
            <div class="flex items-center gap-2.5 shrink-0">
                <div class="h-6 w-24 bg-stone-100 rounded-lg"></div>
                <div class="h-4 w-28 bg-stone-100/60 rounded-md hidden sm:block"></div>
            </div>

            <!-- Right: Actions & Close Button -->
            <div class="flex items-center gap-2">
                <div class="h-7 w-20 bg-stone-100 rounded-lg hidden sm:block"></div>
                <div class="h-7 w-28 bg-amber-500/10 rounded-lg"></div>

                <!-- Close Button -->
                <button 
                    type="button" 
                    @click="confirmClose(() => $wire.closeModal())" 
                    class="h-8 w-8 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-stone-100 transition flex items-center justify-center shrink-0 cursor-pointer ml-0.5" 
                    title="{{ __('Cerrar panel (Esc)') }}"
                    aria-label="{{ __('Cerrar panel') }}">
                    <x-lucide-x class="w-4 h-4 shrink-0" />
                </button>
            </div>
        </div>

        <!-- Title & Client Pill -->
        <div class="space-y-2.5 pt-1">
            <div class="h-7 w-3/5 max-w-md bg-stone-200/60 rounded-xl"></div>
            <div class="flex items-center gap-2">
                <div class="h-5 w-32 bg-stone-100 rounded-lg"></div>
                <div class="h-4 w-20 bg-stone-100/70 rounded-md"></div>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="overflow-y-auto flex-1 min-h-0 scrollbar-thin">
        <!-- Properties Grid (Borderless, soft subtle boxes) -->
        <div class="px-5 sm:px-7 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
                <!-- Diseñadores -->
                <div class="space-y-2">
                    <div class="h-3 w-16 bg-stone-200/50 rounded-md"></div>
                    <div class="h-6 w-24 bg-stone-100/80 rounded-lg"></div>
                </div>
                <!-- ID Tarjeta Trello -->
                <div class="space-y-2">
                    <div class="h-3 w-20 bg-stone-200/50 rounded-md"></div>
                    <div class="h-6 w-28 bg-stone-100/80 rounded-lg"></div>
                </div>
                <!-- Fecha Límite -->
                <div class="space-y-2">
                    <div class="h-3 w-16 bg-stone-200/50 rounded-md"></div>
                    <div class="h-6 w-20 bg-stone-100/80 rounded-lg"></div>
                </div>
                <!-- Revisiones Cliente -->
                <div class="space-y-2">
                    <div class="h-3 w-20 bg-stone-200/50 rounded-md"></div>
                    <div class="h-6 w-12 bg-stone-100/80 rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation Bar (Borderless pill style) -->
        <div class="px-5 sm:px-7 pt-2 pb-1 bg-white sticky top-0 z-20">
            <div class="flex items-center gap-2">
                <!-- Tab 1 Pill -->
                <div class="h-8 px-3.5 bg-stone-100/90 rounded-lg flex items-center gap-2">
                    <div class="w-3.5 h-3.5 rounded bg-stone-300/80"></div>
                    <div class="h-3 w-16 bg-stone-300/80 rounded-md"></div>
                    <div class="h-3.5 w-7 bg-stone-200/70 rounded-full"></div>
                </div>
                <!-- Tab 2 Pill -->
                <div class="h-8 px-3.5 bg-stone-50 rounded-lg flex items-center gap-2 opacity-50">
                    <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
                    <div class="h-3 w-16 bg-stone-200 rounded-md"></div>
                </div>
                <!-- Tab 3 Pill -->
                <div class="h-8 px-3.5 bg-stone-50 rounded-lg flex items-center gap-2 opacity-50">
                    <div class="w-3.5 h-3.5 rounded bg-stone-200"></div>
                    <div class="h-3 w-14 bg-stone-200 rounded-md"></div>
                </div>
            </div>
        </div>

        <!-- Tab Content Area -->
        <div class="px-5 sm:px-7 pt-5 pb-24 space-y-6">
            <!-- Piezas Section (Soft box, no borders) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-32 bg-stone-200/60 rounded-lg"></div>
                    <div class="h-6 w-20 bg-stone-100 rounded-lg"></div>
                </div>
                <div class="p-4 rounded-2xl bg-stone-50/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="h-4 w-44 bg-stone-200/60 rounded-md"></div>
                        <div class="h-4 w-12 bg-stone-200/40 rounded-md"></div>
                    </div>
                    <div class="h-3 w-3/4 bg-stone-150/70 rounded-md"></div>
                </div>
            </div>

            <!-- Subtareas Section (Soft box list, no borders) -->
            <div class="space-y-3 pt-2">
                <!-- Header & Progress Bar -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="h-4 w-24 bg-stone-200/60 rounded-lg"></div>
                        <div class="h-3 w-12 bg-stone-150 rounded-md"></div>
                    </div>
                    <div class="h-1.5 w-full bg-stone-100/70 rounded-full overflow-hidden">
                        <div class="h-full w-2/5 bg-stone-200/70 rounded-full"></div>
                    </div>
                </div>

                <!-- Subtask Rows (Delicate soft rows) -->
                <div class="space-y-2 pt-1">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-stone-50/70">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-4 h-4 rounded-md bg-stone-200/60 shrink-0"></div>
                            <div class="h-3.5 w-3/5 bg-stone-200/50 rounded-md"></div>
                        </div>
                        <div class="h-4 w-14 rounded-md bg-stone-150/60 shrink-0"></div>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-stone-50/70">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-4 h-4 rounded-md bg-stone-200/60 shrink-0"></div>
                            <div class="h-3.5 w-4/5 bg-stone-200/50 rounded-md"></div>
                        </div>
                        <div class="h-4 w-14 rounded-md bg-stone-150/60 shrink-0"></div>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-stone-50/70">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-4 h-4 rounded-md bg-stone-200/60 shrink-0"></div>
                            <div class="h-3.5 w-1/2 bg-stone-200/50 rounded-md"></div>
                        </div>
                        <div class="h-4 w-14 rounded-md bg-stone-150/60 shrink-0"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
