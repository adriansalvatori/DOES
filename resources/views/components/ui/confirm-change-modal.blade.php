{{--
    Global confirmation for changes to record data: WO, company name, contact details, address,
    linked company or contact, merges, prices and estimate amounts, Trello card links.
    Everyday edits (substatus, due date, designer, stage, tasks) save instantly instead.

    Open it from JavaScript / Alpine with the promise helper defined in resources/js/app.js:
        KudosConfirmChange({ field: 'WO', from: '16412', to: '16413' })
            .then((confirmed) => confirmed ? $wire.quickUpdateField(id, 'wo_number', value) : revert());
    Mounted once in the app layout.
--}}
<div
    x-data="{
        show: false,
        field: '',
        from: '',
        to: '',
        note: '',
        confirmText: @js(__('Confirmar cambio')),
        resolver: null,
        open(detail) {
            this.field = detail.field ?? '';
            this.from = (detail.from ?? '') === '' ? @js(__('(vacío)')) : String(detail.from);
            this.to = (detail.to ?? '') === '' ? @js(__('(vacío)')) : String(detail.to);
            this.note = detail.note ?? '';
            this.confirmText = detail.confirmText || @js(__('Confirmar cambio'));
            this.resolver = typeof detail.resolve === 'function' ? detail.resolve : null;
            this.show = true;
            this.$nextTick(() => this.$refs.confirmButton?.focus());
        },
        finish(confirmed) {
            this.show = false;
            if (this.resolver) {
                this.resolver(confirmed);
            }
            this.resolver = null;
        }
    }"
    @kudos-confirm-change.window="open($event.detail)"
    @keydown.window.escape="if (show) { $event.stopPropagation(); finish(false); }"
    x-show="show"
    x-cloak
    style="display: none;"
    class="fixed inset-0 z-[10000] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-change-title"
>
    <div class="fixed inset-0 bg-stone-900/40" @click="finish(false)"></div>

    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-trap.noscroll="show"
        class="relative w-full max-w-md bg-surface border border-line rounded-panel shadow-panel p-5 space-y-4"
    >
        <div class="space-y-1">
            <h2 id="confirm-change-title" class="text-section font-semibold text-ink">
                {{ __('¿Cambiar') }} <span x-text="field"></span>?
            </h2>
            <p class="text-body text-ink-3">{{ __('Este dato identifica la orden o el cliente. Revisa el cambio antes de guardarlo.') }}</p>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded-card bg-sunken p-3">
            <div class="min-w-0">
                <p class="text-meta text-ink-3">{{ __('Antes') }}</p>
                <p class="text-row text-ink-2 line-through break-words" x-text="from"></p>
            </div>
            <x-lucide-arrow-right class="w-4 h-4 text-ink-3" aria-hidden="true" />
            <div class="min-w-0">
                <p class="text-meta text-ink-3">{{ __('Después') }}</p>
                <p class="text-row font-semibold text-ink break-words" x-text="to"></p>
            </div>
        </div>

        <p x-show="note" x-text="note" class="text-meta text-ink-3"></p>

        <div class="flex justify-end gap-2">
            <button type="button" @click="finish(false)" class="h-8 px-3 rounded-control border border-line-strong bg-surface text-body font-medium text-ink hover:bg-sunken transition cursor-pointer">
                {{ __('Cancelar') }}
            </button>
            <button type="button" x-ref="confirmButton" @click="finish(true)" class="h-8 px-3 rounded-control bg-ink text-white text-body font-medium hover:bg-black transition cursor-pointer" x-text="confirmText"></button>
        </div>
    </div>
</div>
