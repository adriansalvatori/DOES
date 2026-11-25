@props(['columns'])

{{-- One actions menu for the whole board, opened from any card's "..." button. --}}
<div
    x-data="{
        open: false,
        movePanel: false,
        orderId: null,
        status: null,
        trelloUrl: null,
        top: 0,
        left: 0,
        show(detail) {
            const rect = detail.anchor.getBoundingClientRect();
            this.orderId = detail.orderId;
            this.status = detail.status;
            this.trelloUrl = detail.trelloUrl;
            this.movePanel = false;
            this.top = Math.min(rect.bottom + 4, window.innerHeight - 280);
            this.left = Math.max(8, Math.min(rect.right - 224, window.innerWidth - 232));
            this.open = true;
            this.$nextTick(() => this.$refs.menu.querySelector('button, a')?.focus());
        },
        close() { this.open = false; },
        move(value) { this.close(); $wire.moveOrder(this.orderId, value); },
        duplicate() { this.close(); $wire.$dispatch('open-duplicate-order', { orderId: this.orderId }); },
        trash() {
            const id = this.orderId;
            this.close();
            if (confirm(@js(__('¿Enviar esta orden a la papelera (archivar en Trello)?')))) {
                $wire.trashOrder(id);
            }
        },
    }"
    @kanban-card-menu.window="show($event.detail)"
    @keydown.escape.window="close()"
    @click.window="if (open && !$refs.menu.contains($event.target)) close()"
    @scroll.window.capture="close()">
    <div
        x-ref="menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        role="menu"
        :style="`position: fixed; top: ${top}px; left: ${left}px;`"
        class="z-50 w-56 bg-surface border border-line rounded-card shadow-menu p-1 text-body text-ink"
        style="display: none;">
        <button type="button" role="menuitem" @click="movePanel = !movePanel" :aria-expanded="movePanel.toString()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
            <x-lucide-arrow-left-right class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
            <span class="flex-1">{{ __('Mover a...') }}</span>
            <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition-transform" x-bind:class="{ 'rotate-180': movePanel }" aria-hidden="true" />
        </button>
        <div x-show="movePanel" x-collapse class="max-h-56 overflow-y-auto pl-4" style="display: none;">
            @foreach($columns as $columnOption)
                <button type="button" role="menuitem" x-show="status !== {{ \Illuminate\Support\Js::from($columnOption->value) }}" @click="move({{ \Illuminate\Support\Js::from($columnOption->value) }})" class="w-full px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">{{ $columnOption->label() }}</button>
            @endforeach
        </div>
        <button type="button" role="menuitem" @click="duplicate()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
            <x-lucide-copy class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
            <span>{{ __('Duplicar Orden') }}</span>
        </button>
        <a x-show="trelloUrl" x-cloak :href="trelloUrl" target="_blank" rel="noopener noreferrer" role="menuitem" @click="close()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
            <x-lucide-external-link class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
            <span>{{ __('Abrir en Trello') }}</span>
        </a>
        @if(auth()->user()?->canDeleteOrders())
            <div class="my-1 border-t border-line"></div>
            <button type="button" role="menuitem" @click="trash()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-danger-soft text-danger text-left cursor-pointer">
                <x-lucide-trash-2 class="w-4 h-4 shrink-0" aria-hidden="true" />
                <span>{{ __('Enviar a la papelera (archivar)') }}</span>
            </button>
        @endif
    </div>
</div>
