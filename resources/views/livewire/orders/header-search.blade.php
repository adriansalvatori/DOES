{{--
    ⌘K command palette. The top bar shows a search-style trigger and "New work order".
    ⌘K / Ctrl+K or a click opens the palette: live work order, deliverable and client search (server-side),
    "Ir a" destinations from NavigationService (filtered in the browser) and quick actions.
    Keyboard: ↑ ↓ move, Enter opens, Esc closes. Tab moves focus normally inside the dialog.
--}}
<div
    x-data="{
        open: false,
        query: '',
        selectedIndex: 0,
        openPalette() {
            this.open = true;
            this.selectedIndex = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        closePalette() {
            this.open = false;
            this.query = '';
            this.selectedIndex = 0;
            $wire.clearSearch();
        },
        matches(label) {
            const needle = this.query.trim().toLowerCase();
            return needle === '' || label.includes(needle);
        },
        items() {
            return this.$refs.list ? Array.from(this.$refs.list.querySelectorAll('[data-palette-item]')).filter((el) => el.offsetParent !== null) : [];
        },
        isSelected(el) {
            return this.items()[this.selectedIndex] === el;
        },
        move(step) {
            const total = this.items().length;
            if (total === 0) return;
            this.selectedIndex = (this.selectedIndex + step + total) % total;
            this.$nextTick(() => this.items()[this.selectedIndex]?.scrollIntoView({ block: 'nearest' }));
        },
        activate() {
            this.items()[this.selectedIndex]?.click();
        }
    }"
    @keydown.window.cmd.k.prevent="open ? closePalette() : openPalette()"
    @keydown.window.ctrl.k.prevent="open ? closePalette() : openPalette()"
    @open-command-palette.window="openPalette()"
    class="flex items-center gap-2 min-w-0"
>
    <!-- Top bar trigger -->
    <button
        type="button"
        @click="openPalette()"
        class="w-44 md:w-64 lg:w-72 min-w-0 h-8 px-2.5 rounded-control border border-line bg-surface text-body text-ink-3 hover:border-line-strong transition flex items-center gap-2 cursor-pointer"
        aria-haspopup="dialog">
        <x-lucide-search class="w-4 h-4 shrink-0" aria-hidden="true" />
        <span class="truncate">{{ __('Search or jump to…') }}</span>
        <kbd class="ui-kbd ml-auto hidden sm:inline-flex">⌘K</kbd>
    </button>

    <button
        type="button"
        @click="$dispatch('open-create-work-order')"
        class="ui-btn ui-btn-primary"
        title="{{ __('New work order') }}">
        <x-lucide-plus aria-hidden="true" />
        <span class="hidden sm:inline">{{ __('New work order') }}</span>
    </button>

    <!-- Palette dialog -->
    <div
        x-show="open"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[12vh]"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Search or jump to…') }}"
        @keydown.escape.prevent.stop="closePalette()"
    >
        <div class="fixed inset-0 bg-stone-900/30" @click="closePalette()"></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-trap.noscroll="open"
            class="relative w-full max-w-xl bg-surface border border-line rounded-panel shadow-panel overflow-hidden flex flex-col max-h-[70vh]"
        >
            <div class="flex items-center gap-2 px-3 border-b border-line">
                <x-lucide-search class="w-4 h-4 text-ink-3 shrink-0" aria-hidden="true" />
                <input
                    x-ref="input"
                    type="text"
                    wire:model.live.debounce.200ms="search"
                    @input="query = $event.target.value; selectedIndex = 0"
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter.prevent="activate()"
                    placeholder="{{ __('WO, client, deliverable or section…') }}"
                    autocomplete="off"
                    spellcheck="false"
                    class="flex-1 h-12 bg-transparent text-row text-ink placeholder:text-ink-3 focus:outline-none"
                    aria-label="{{ __('Search or jump to…') }}"
                />
                <span wire:loading wire:target="search" class="text-ink-3" aria-hidden="true">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                </span>
            </div>

            <div x-ref="list" class="overflow-y-auto p-1.5 space-y-3 custom-vertical-scrollbar" role="listbox">
                @php $hasQuery = mb_strlen(trim($search)) >= 1; @endphp

                @if($hasQuery && count($workOrders) > 0)
                    <div>
                        <p class="px-2 py-1 text-meta text-ink-3">{{ __('Work orders') }}</p>
                        @foreach($workOrders as $workOrder)
                            <a
                                wire:key="palette-wo-{{ $workOrder->id }}"
                                href="{{ route('work-orders.show', $workOrder) }}"
                                wire:navigate
                                data-palette-item
                                role="option"
                                tabindex="-1"
                                :aria-selected="isSelected($el).toString()"
                                :class="isSelected($el) ? 'bg-sunken' : ''"
                                @mouseenter="selectedIndex = items().indexOf($el)"
                                @click="closePalette()"
                                class="flex items-center gap-3 px-2 py-2 rounded-control cursor-pointer"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        @if($workOrder->number)
                                            <x-wo-badge :number="$workOrder->number" />
                                        @endif
                                        <span class="text-row font-medium text-ink truncate">{{ \App\Support\TitleCase::from($workOrder->displayName()) }}</span>
                                        <x-orders.location :location="\App\Support\TitleCase::from($workOrder->clientLocation?->name)" class="shrink-0" />
                                    </div>
                                    @if($workOrder->description)
                                        <p class="text-body text-ink-2 truncate">{{ $workOrder->description }}</p>
                                    @endif
                                </div>
                                <span class="text-meta text-ink-3 shrink-0">{{ trans_choice(':count deliverable|:count deliverables', $workOrder->deliverables_count, ['count' => $workOrder->deliverables_count]) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if($hasQuery && count($deliverables) > 0)
                    <div>
                        <p class="px-2 py-1 text-meta text-ink-3">{{ __('Deliverables') }}</p>
                        @foreach($deliverables as $deliverable)
                            <div
                                wire:key="palette-deliverable-{{ $deliverable->id }}"
                                data-palette-item
                                role="option"
                                tabindex="-1"
                                :aria-selected="isSelected($el).toString()"
                                :class="isSelected($el) ? 'bg-sunken' : ''"
                                @mouseenter="selectedIndex = items().indexOf($el)"
                                @click="open = false; query = ''; $wire.selectDeliverable({{ $deliverable->id }})"
                                class="flex items-center gap-3 px-2 py-2 rounded-control cursor-pointer"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        @if($deliverable->workOrder?->number)
                                            <x-wo-badge :number="$deliverable->workOrder->number" />
                                        @endif
                                        <span class="text-row font-medium text-ink truncate">{{ $deliverable->task_name }}</span>
                                    </div>
                                    <p class="text-body text-ink-2 truncate">{{ \App\Support\TitleCase::from($deliverable->workOrder?->displayName() ?: $deliverable->company_name) }}</p>
                                </div>
                                <span class="text-meta text-ink-3 shrink-0">{{ $deliverable->status?->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($hasQuery && count($clientResults) > 0)
                    <div>
                        <p class="px-2 py-1 text-meta text-ink-3">{{ __('Clients') }}</p>
                        @foreach($clientResults as $client)
                            <div
                                wire:key="palette-client-{{ $client->id }}"
                                data-palette-item
                                role="option"
                                tabindex="-1"
                                :aria-selected="isSelected($el).toString()"
                                :class="isSelected($el) ? 'bg-sunken' : ''"
                                @mouseenter="selectedIndex = items().indexOf($el)"
                                @click="open = false; query = ''; $wire.selectClient({{ $client->id }})"
                                class="flex items-center gap-3 px-2 py-2 rounded-control cursor-pointer"
                            >
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-control bg-sunken text-ink-2">
                                    <x-lucide-building-2 class="w-4 h-4" aria-hidden="true" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-row font-medium text-ink truncate">{{ \App\Support\TitleCase::from($client->name) }}</p>
                                    <p class="text-meta text-ink-3 truncate">{{ trans_choice(':count active order|:count active orders', $client->active_orders_count, ['count' => $client->active_orders_count]) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Quick actions -->
                <div x-show="matches(@js(mb_strtolower(__('New work order') . ' crear')))">
                    <p class="px-2 py-1 text-meta text-ink-3">{{ __('Actions') }}</p>
                    <div
                        data-palette-item
                        role="option"
                        tabindex="-1"
                        :aria-selected="isSelected($el).toString()"
                        :class="isSelected($el) ? 'bg-sunken' : ''"
                        @mouseenter="selectedIndex = items().indexOf($el)"
                        @click="closePalette(); $dispatch('open-create-work-order')"
                        class="flex items-center gap-3 px-2 py-2 rounded-control cursor-pointer"
                    >
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-control bg-ink text-white">
                            <x-lucide-plus class="w-4 h-4" aria-hidden="true" />
                        </span>
                        <span class="text-row text-ink">{{ __('New work order') }}</span>
                    </div>
                </div>

                <!-- Navigation -->
                @php $destinationGroups = collect($destinations)->groupBy('section'); @endphp
                @foreach($destinationGroups as $section => $items)
                    <div x-show="@js($items->map(fn ($item) => mb_strtolower($item['label'] . ' ' . $item['title']))->values()).some((label) => matches(label))">
                        <p class="px-2 py-1 text-meta text-ink-3">{{ $section }}</p>
                        @foreach($items as $item)
                            <a
                                href="{{ $item['href'] }}"
                                wire:navigate
                                data-palette-item
                                role="option"
                                tabindex="-1"
                                x-show="matches(@js(mb_strtolower($item['label'] . ' ' . $item['title'])))"
                                :aria-selected="isSelected($el).toString()"
                                :class="isSelected($el) ? 'bg-sunken' : ''"
                                @mouseenter="selectedIndex = items().indexOf($el)"
                                @click="open = false"
                                class="flex items-center gap-3 px-2 py-2 rounded-control cursor-pointer"
                            >
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-control bg-sunken text-ink-2">
                                    {{ svg('lucide-' . $item['icon'], 'w-4 h-4', ['aria-hidden' => 'true']) }}
                                </span>
                                <span class="text-row text-ink truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                @if($hasQuery && count($workOrders) === 0 && count($deliverables) === 0 && count($clientResults) === 0)
                    <p class="px-2 py-6 text-center text-body text-ink-3" x-show="items().length === 0">
                        {{ __('No results for “:query”.', ['query' => trim($search)]) }}
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-4 px-3 py-2 border-t border-line text-meta text-ink-3">
                <span class="inline-flex items-center gap-1"><kbd class="px-1 rounded border border-line font-sans">↑</kbd><kbd class="px-1 rounded border border-line font-sans">↓</kbd> {{ __('to move') }}</span>
                <span class="inline-flex items-center gap-1"><kbd class="px-1 rounded border border-line font-sans">↵</kbd> {{ __('to open') }}</span>
                <span class="inline-flex items-center gap-1"><kbd class="px-1 rounded border border-line font-sans">Esc</kbd> {{ __('to close') }}</span>
            </div>
        </div>
    </div>
</div>
