@props(['abilities'])

{{--
    The board's one shared card menu plus its three small sheets (block, hold, confirm received).
    Cards only dispatch `board-card-menu` / `board-receive`; every action calls a BoardPage method.
--}}
@php
    $statusesByArea = collect(\App\Domain\Delivery\Enums\DeliverableStatus::cases())->groupBy(fn ($status) => $status->area()->value);
    $workOrderUrl = \Illuminate\Support\Facades\Route::has('work-orders.show') ? route('work-orders.show', '__ID__') : null;
@endphp
<div
    x-data="{
        open: false, movePanel: false, card: null, top: 0, left: 0,
        sheet: null, reason: '', note: '', method: 'delivery', receivedOn: '', receive: null,
        show(detail) {
            const rect = detail.anchor.getBoundingClientRect();
            this.card = detail;
            this.movePanel = false;
            this.top = Math.min(rect.bottom + 4, window.innerHeight - 340);
            this.left = Math.max(8, Math.min(rect.right - 224, window.innerWidth - 232));
            this.open = true;
            this.$nextTick(() => this.$refs.menu.querySelector('button, a')?.focus());
        },
        close() { this.open = false; },
        move(status) { this.close(); $wire.move(this.card.id, status); },
        openSheet(kind, firstReason) { this.close(); this.sheet = kind; this.reason = firstReason; this.note = ''; },
        submitSheet() {
            if (this.sheet === 'block') { $wire.block(this.card.id, this.reason, this.note); }
            if (this.sheet === 'hold') { $wire.putOnHold(this.card.id, this.reason, this.note); }
            if (this.sheet === 'resolve') { $wire.resolveBlock(this.card.id, this.note); }
            if (this.sheet === 'receive') { $wire.confirmReceived(this.receive.scope, this.receive.id, this.method, this.receivedOn); }
            this.sheet = null;
        },
        direct(method) { this.close(); $wire[method](this.card.id); },
        trash() { this.close(); if (confirm(@js(__('Move this deliverable to the trash?')))) { $wire.trash(this.card.id); } },
        openPanel() { this.close(); $dispatch('open-deliverable', { deliverableId: this.card.id }); },
    }"
    @board-card-menu.window="show($event.detail)"
    @board-receive.window="receive = $event.detail; sheet = 'receive'; method = 'delivery'; receivedOn = new Date().toISOString().slice(0, 10)"
    @keydown.escape.window="close(); sheet = null"
    @click.window="if (open && !$refs.menu.contains($event.target)) close()"
    @scroll.window.capture="close()">

    <div x-ref="menu" x-show="open" x-cloak x-transition.opacity.duration.100ms role="menu"
         :style="`position: fixed; top: ${top}px; left: ${left}px;`"
         class="z-50 w-56 bg-surface border border-line rounded-card shadow-menu p-1 text-body text-ink" style="display: none;">
        <button type="button" role="menuitem" @click="openPanel()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
            <x-lucide-panel-right-open class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
            <span>{{ __('Open') }}</span>
        </button>
        @if($workOrderUrl)
            <a :href="@js($workOrderUrl).replace('__ID__', card?.workOrderId)" wire:navigate role="menuitem" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-file-text class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Open work order') }}</span>
            </a>
        @endif

        @if($abilities['move'])
            <button type="button" role="menuitem" @click="movePanel = !movePanel" :aria-expanded="movePanel.toString()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-arrow-left-right class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span class="flex-1">{{ __('Move to...') }}</span>
                <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition-transform" x-bind:class="{ 'rotate-180': movePanel }" aria-hidden="true" />
            </button>
            <div x-show="movePanel" x-collapse class="max-h-56 overflow-y-auto pl-4" style="display: none;">
                @foreach($statusesByArea as $areaValue => $statuses)
                    <p class="px-2 pt-1.5 text-meta text-ink-3">{{ \App\Domain\Delivery\Enums\Area::from($areaValue)->label() }}</p>
                    @foreach($statuses as $status)
                        <button type="button" role="menuitem" x-show="card?.status !== '{{ $status->value }}'" @click="move('{{ $status->value }}')" class="w-full px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">{{ $status->label() }}</button>
                    @endforeach
                @endforeach
            </div>
        @endif

        @if($abilities['block'])
            <button type="button" role="menuitem" x-show="card && !card.blocked" @click="openSheet('block', @js(\App\Domain\Delivery\Enums\BlockReason::cases()[0]->value))" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-octagon-alert class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Block...') }}</span>
            </button>
        @endif
        @if($abilities['manage'])
            <button type="button" role="menuitem" x-show="card && card.blocked" @click="openSheet('resolve', '')" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-circle-check class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Resolve block') }}</span>
            </button>
            <button type="button" role="menuitem" x-show="card && !card.onHold" @click="openSheet('hold', @js(\App\Domain\Delivery\Enums\HoldReason::cases()[0]->value))" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-pause class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Put on hold...') }}</span>
            </button>
            <button type="button" role="menuitem" x-show="card && card.onHold" @click="direct('resume')" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-play class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Resume') }}</span>
            </button>
        @endif
        @if($abilities['duplicate'])
            <button type="button" role="menuitem" @click="direct('duplicate')" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken text-left cursor-pointer">
                <x-lucide-copy class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <span>{{ __('Duplicate') }}</span>
            </button>
        @endif
        @if($abilities['trash'])
            <div class="my-1 border-t border-line"></div>
            <button type="button" role="menuitem" @click="trash()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-danger-soft text-danger text-left cursor-pointer">
                <x-lucide-trash-2 class="w-4 h-4 shrink-0" aria-hidden="true" />
                <span>{{ __('Trash') }}</span>
            </button>
        @endif
    </div>

    <div x-show="sheet" x-cloak style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="sheet = null"></div>
        <form @submit.prevent="submitSheet()" role="dialog" aria-modal="true" class="relative w-full max-w-sm bg-surface border border-line rounded-card shadow-menu p-4 space-y-3">
            <h2 class="text-body font-semibold text-ink">
                <span x-show="sheet === 'block'">{{ __('Block this deliverable') }}</span>
                <span x-show="sheet === 'hold'">{{ __('Put this deliverable on hold') }}</span>
                <span x-show="sheet === 'resolve'">{{ __('Resolve the block') }}</span>
                <span x-show="sheet === 'receive'">{{ __('Confirm received') }}</span>
            </h2>

            <div x-show="sheet === 'block'" class="space-y-1">
                <label class="ui-label" for="board-block-reason">{{ __('Reason') }}</label>
                <select id="board-block-reason" x-model="reason" class="ui-select">
                    @foreach(\App\Domain\Delivery\Enums\BlockReason::cases() as $blockReason)
                        <option value="{{ $blockReason->value }}">{{ $blockReason->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="sheet === 'hold'" class="space-y-1">
                <label class="ui-label" for="board-hold-reason">{{ __('Reason') }}</label>
                <select id="board-hold-reason" x-model="reason" class="ui-select">
                    @foreach(\App\Domain\Delivery\Enums\HoldReason::cases() as $holdReason)
                        <option value="{{ $holdReason->value }}">{{ $holdReason->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="sheet === 'receive'" class="space-y-3">
                <p class="text-meta text-ink-3" x-text="receive && receive.count > 1 ? @js(__('Everything waiting in this work order will be marked as received.')) : ''"></p>
                <div class="space-y-1">
                    <label class="ui-label" for="board-receive-method">{{ __('How was it received?') }}</label>
                    <select id="board-receive-method" x-model="method" class="ui-select">
                        @foreach(\App\Domain\Delivery\Enums\FulfillmentMethod::cases() as $fulfillmentMethod)
                            <option value="{{ $fulfillmentMethod->value }}">{{ $fulfillmentMethod->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="ui-label" for="board-receive-date">{{ __('Date received') }}</label>
                    <input id="board-receive-date" type="date" x-model="receivedOn" class="ui-input">
                </div>
            </div>
            <div x-show="sheet === 'block' || sheet === 'hold' || sheet === 'resolve'" class="space-y-1">
                <label class="ui-label" for="board-sheet-note">{{ __('Note (optional)') }}</label>
                <textarea id="board-sheet-note" x-model="note" rows="2" @keydown.enter.stop class="ui-input"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="ui-btn ui-btn-secondary" @click="sheet = null">{{ __('Cancel') }}</button>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('Confirm') }}</button>
            </div>
        </form>
    </div>
</div>
