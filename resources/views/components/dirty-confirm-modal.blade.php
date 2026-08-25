<div 
    x-data="{
        show: false,
        title: @js(__('¿Descartar cambios sin guardar?')),
        description: @js(__('Tienes información editada que no ha sido guardada. Si sales ahora, estos cambios se perderán.')),
        confirmText: @js(__('Sí, descartar cambios')),
        cancelText: @js(__('Continuar editando')),
        onConfirmCallback: null,
        onCancelCallback: null,

        open(detail) {
            this.title = detail.title || @js(__('¿Descartar cambios sin guardar?'));
            this.description = detail.description || @js(__('Tienes información editada que no ha sido guardada. Si sales ahora, estos cambios se perderán.'));
            this.confirmText = detail.confirmText || @js(__('Sí, descartar cambios'));
            this.cancelText = detail.cancelText || @js(__('Continuar editando'));
            this.onConfirmCallback = detail.onConfirm || null;
            this.onCancelCallback = detail.onCancel || null;
            this.show = true;
        },

        confirm() {
            this.show = false;
            if (window.KudosDirtyGuard) {
                window.KudosDirtyGuard.closeConfirmModal();
            }
            if (typeof this.onConfirmCallback === 'function') {
                this.onConfirmCallback();
            }
        },

        cancel() {
            this.show = false;
            if (window.KudosDirtyGuard) {
                window.KudosDirtyGuard.closeConfirmModal();
            }
            if (typeof this.onCancelCallback === 'function') {
                this.onCancelCallback();
            }
        }
    }"
    @open-dirty-confirm-modal.window="open($event.detail)"
    @keydown.window.escape="if (show) cancel()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
>
    <!-- Smooth Animated Backdrop -->
    <div 
        x-show="show" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="cancel()"
        class="fixed inset-0 bg-stone-900/60 backdrop-blur-xs transition-opacity"
    ></div>

    <!-- Modal Box Container -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-3"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-3"
        class="relative bg-white border border-[#e9e9e7] rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-5 z-10"
    >
        <!-- Header Banner with Warning Icon Badge -->
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-200/80 flex items-center justify-center shrink-0 shadow-2xs">
                <x-lucide-alert-triangle class="w-5 h-5" />
            </div>
            <div class="space-y-1 min-w-0 flex-1">
                <h3 class="font-bold text-base text-zinc-900 tracking-tight leading-snug" x-text="title"></h3>
                <p class="text-xs text-zinc-600 leading-relaxed font-normal" x-text="description"></p>
            </div>
        </div>

        <!-- Unambiguous Action Buttons -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-2 border-t border-stone-100">
            <button 
                type="button" 
                @click="cancel()" 
                class="w-full sm:w-auto px-4 py-2 rounded-xl bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-semibold border border-stone-200 transition cursor-pointer text-center"
                x-text="cancelText"
            ></button>
            <button 
                type="button" 
                @click="confirm()" 
                class="w-full sm:w-auto px-4.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5 shadow-2xs"
            >
                <x-lucide-trash-2 class="w-3.5 h-3.5 text-rose-600" />
                <span x-text="confirmText"></span>
            </button>
        </div>
    </div>
</div>
