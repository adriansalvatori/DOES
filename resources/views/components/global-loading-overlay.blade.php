{{-- Global Loading Overlay (Debounced, Hardware-accelerated, Zero-lag) --}}
<div 
    id="kudos-global-loader" 
    class="fixed inset-0 z-[9990] flex items-center justify-center bg-stone-900/15 opacity-0 pointer-events-none invisible transition-opacity duration-200 select-none"
    style="display: flex;"
    aria-hidden="true"
>
    <div 
        data-loader-card
        class="bg-white/95 dark:bg-stone-900/95 shadow-2xl border border-stone-200/80 dark:border-stone-800 p-3.5 rounded-2xl flex items-center justify-center transform scale-95 transition-transform duration-200 pointer-events-auto"
    >
        <svg class="animate-spin w-7 h-7 text-[#eda621] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
            <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
</div>
