<div
    x-data="{
        isBackingUp: false,
        handleKeydown(e) {
            const isCmdOrCtrl = e.metaKey || e.ctrlKey;
            if (isCmdOrCtrl && !e.shiftKey && !e.altKey && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                e.stopPropagation();

                if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                    return;
                }

                if (this.isBackingUp) {
                    return;
                }

                this.triggerBackup();
            }
        },
        triggerBackup() {
            if (this.isBackingUp) return;
            this.isBackingUp = true;

            $wire.createBackup()
                .then(() => {
                    this.isBackingUp = false;
                })
                .catch(() => {
                    this.isBackingUp = false;
                });
        }
    }"
    @keydown.window="handleKeydown($event)"
    @trigger-database-backup.window="triggerBackup()"
>
    <!-- Floating status pill when backup is being generated -->
    <div
        x-show="isBackingUp"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
        style="display: none;"
        class="fixed top-5 right-5 z-[9999] bg-stone-900/95 backdrop-blur text-white text-xs font-medium px-4 py-2.5 rounded-xl shadow-2xl border border-stone-700/80 flex items-center gap-3 pointer-events-none select-none"
    >
        <x-lucide-refresh-cw class="w-4 h-4 text-amber-400 animate-spin shrink-0" />
        <span class="font-medium tracking-tight">{{ __('Generando respaldo de base de datos...') }}</span>
        <span class="text-[10px] font-mono text-zinc-300 bg-stone-800 px-1.5 py-0.5 rounded border border-stone-700">⌘S</span>
    </div>
</div>
