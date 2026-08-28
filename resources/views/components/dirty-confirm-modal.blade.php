<div 
    x-data="{
        show: false,
        title: @js(__('¿Guardar cambios sin guardar?')),
        description: @js(__('Tienes datos o modificaciones que no han sido guardadas.')),
        cancelText: @js(__('Cancelar')),
        discardText: @js(__('No guardar')),
        saveText: @js(__('Guardar')),
        onCancelCallback: null,
        onDiscardCallback: null,
        onSaveCallback: null,

        open(detail) {
            this.title = detail.title || @js(__('¿Guardar cambios sin guardar?'));
            this.description = detail.description || @js(__('Tienes datos o modificaciones que no han sido guardadas.'));
            this.cancelText = detail.cancelText || @js(__('Cancelar'));
            this.discardText = detail.discardText || detail.confirmText || @js(__('No guardar'));
            this.saveText = detail.saveText || @js(__('Guardar'));
            this.onCancelCallback = detail.onCancel || null;
            this.onDiscardCallback = detail.onDiscard || detail.onConfirm || null;
            this.onSaveCallback = detail.onSave || null;
            this.show = true;
        },

        cancel() {
            this.show = false;
            if (window.KudosDirtyGuard) {
                window.KudosDirtyGuard.closeConfirmModal();
            }
            if (typeof this.onCancelCallback === 'function') {
                this.onCancelCallback();
            }
        },

        discard() {
            this.show = false;
            if (window.KudosDirtyGuard) {
                window.KudosDirtyGuard.closeConfirmModal();
            }
            if (typeof this.onDiscardCallback === 'function') {
                this.onDiscardCallback();
            }
        },

        save() {
            this.show = false;
            if (window.KudosDirtyGuard) {
                window.KudosDirtyGuard.closeConfirmModal();
            }
            if (typeof this.onSaveCallback === 'function') {
                this.onSaveCallback();
            } else if (typeof this.onDiscardCallback === 'function') {
                this.onDiscardCallback();
            }
        }
    }"
    @open-dirty-confirm-modal.window="open($event.detail)"
    @keydown.window.escape.stop.prevent="if (show) cancel()"
    @keydown.window.enter.stop.prevent="if (show) save()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[200] flex items-center justify-center p-4 overflow-y-auto"
>
    <!-- Smooth Animated Backdrop Overlay -->
    <div 
        x-show="show" 
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="cancel()"
        class="fixed inset-0 bg-stone-900/50 backdrop-blur-2xs transition-opacity"
    ></div>

    <!-- Compact Modal Box Container -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        class="relative bg-white border border-[#e9e9e7] rounded-xl shadow-xl max-w-md w-full p-4 sm:p-5 space-y-4 z-10"
    >
        <!-- Compact Header with Warning Badge -->
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 border border-amber-200/80 flex items-center justify-center shrink-0 shadow-2xs mt-0.5">
                <x-lucide-alert-triangle class="w-4 h-4" />
            </div>
            <div class="space-y-0.5 min-w-0 flex-1">
                <h3 class="font-bold text-sm text-zinc-900 tracking-tight leading-snug" x-text="title"></h3>
                <p class="text-xs text-zinc-500 leading-normal font-normal" x-text="description"></p>
            </div>
        </div>

        <!-- 3 Compact Action Buttons: Cancel, Don't Save (Red), Save (Green) -->
        <div class="flex items-center justify-end gap-2 pt-3 border-t border-stone-100">
            <button 
                type="button" 
                @click="cancel()" 
                class="px-3 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-700 text-xs font-medium border border-stone-200 transition cursor-pointer text-center"
                x-text="cancelText"
            ></button>
            <button 
                type="button" 
                @click="discard()" 
                class="px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition cursor-pointer flex items-center gap-1 shadow-2xs"
            >
                <x-lucide-trash-2 class="w-3.5 h-3.5 text-rose-600" />
                <span x-text="discardText"></span>
            </button>
            <button 
                type="button" 
                @click="save()" 
                class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition cursor-pointer flex items-center gap-1.5"
            >
                <x-lucide-check class="w-3.5 h-3.5 text-white" />
                <span x-text="saveText"></span>
            </button>
        </div>
    </div>
</div>
