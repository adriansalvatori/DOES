{{--
    Merge modal for work orders, deliverables (samples included) and tasks: pick the main record, then resolve every
    field where the records disagree. Opened with $dispatch('open-merge', { kind, ids }).
--}}
<div
    x-data="{
        open: @js($isOpen),
        init() {
            this.$watch('$wire.isOpen', (value) => {
                this.open = Boolean(value);
                if (this.open) {
                    window.KudosModalStack?.register('merge-modal', this.$el, () => $wire.close());
                    window.KudosModalStack?.bringToFront('merge-modal');
                } else {
                    window.KudosModalStack?.unregister('merge-modal');
                }
            });
            this.$cleanup(() => window.KudosModalStack?.unregister('merge-modal'));
        },
    }"
    @keydown.window.escape="if (open && (window.KudosModalStack ? window.KudosModalStack.isTop('merge-modal') : true)) $wire.close()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-stone-900/40 p-4"
    data-merge-modal
>
    @if($isOpen)
        @php
            $preview = $this->preview;
            $records = collect($preview['records'])->keyBy('id');
            $conflicts = collect($preview['fields'])->where('conflict', true);
            $sameCount = collect($preview['fields'])->where('conflict', false)->count();
            $title = match ($kind) {
                'work_order' => __('Merge work orders'),
                'deliverable' => __('Merge cards'),
                default => __('Merge tasks'),
            };
        @endphp
        <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-panel border border-line bg-surface shadow-panel" role="dialog" aria-modal="true" aria-label="{{ $title }}">
            <div class="flex items-start justify-between gap-3 border-b border-line px-6 py-4">
                <div class="min-w-0">
                    <h2 class="text-section font-semibold text-ink">{{ $title }}</h2>
                    <p class="text-meta text-ink-3">{{ __('Choose the main record, then which value to keep wherever they disagree.') }}</p>
                </div>
                <button type="button" wire:click="close" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" aria-label="{{ __('Close') }}">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                @if($error)
                    <div class="rounded-card bg-danger-soft px-3 py-2 text-body text-danger" role="alert">{{ $error }}</div>
                @endif

                <section class="space-y-2" aria-label="{{ __('Records') }}">
                    <h3 class="ui-section-title">{{ __('Main record') }}</h3>
                    <ul class="divide-y divide-line rounded-card border border-line">
                        @foreach($preview['records'] as $record)
                            <li class="flex items-center gap-3 px-3 py-2" wire:key="merge-record-{{ $record['id'] }}">
                                <input type="radio" name="merge-main" id="merge-main-{{ $record['id'] }}" value="{{ $record['id'] }}" @checked($mainId === $record['id']) wire:click="setMain({{ $record['id'] }})" class="border-line-strong text-ink">
                                <label for="merge-main-{{ $record['id'] }}" class="min-w-0 flex-1 cursor-pointer">
                                    <span class="block truncate text-body font-medium text-ink">{{ $record['label'] }}</span>
                                    <span class="block truncate text-meta text-ink-3">{{ $record['detail'] }}</span>
                                </label>
                                @if($mainId === $record['id'])
                                    <x-ui.tag>{{ __('Main') }}</x-ui.tag>
                                @endif
                                @if(count($preview['records']) > 2 || $kind === 'deliverable')
                                    <button type="button" wire:click="removeRecord({{ $record['id'] }})" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm shrink-0" aria-label="{{ __('Remove from the merge') }}">
                                        <x-lucide-x class="h-3.5 w-3.5" />
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if($kind === 'deliverable')
                        <div class="space-y-1.5">
                            <label class="ui-label" for="merge-search">{{ __('Add a card to merge') }}</label>
                            <input id="merge-search" type="text" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="{{ __('Search by WO, company or name') }}">
                            @if($this->searchResults->isNotEmpty())
                                <ul class="divide-y divide-line rounded-card border border-line" data-merge-results>
                                    @foreach($this->searchResults as $result)
                                        <li class="flex items-center gap-3 px-3 py-1.5" wire:key="merge-result-{{ $result->id }}">
                                            <x-wo-badge :number="$result->workOrder?->number" />
                                            <span class="min-w-0 flex-1 truncate text-body text-ink">{{ $result->task_name }}</span>
                                            <span class="shrink-0 text-meta text-ink-3">{{ $result->status->label() }}</span>
                                            <button type="button" wire:click="addRecord({{ $result->id }})" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">{{ __('Add') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </section>

                @if(count($preview['records']) >= 2)
                    <section class="space-y-3" aria-label="{{ __('Conflicts') }}">
                        <h3 class="ui-section-title">{{ __('Conflicts') }}</h3>
                        @forelse($conflicts as $conflict)
                            @php
                                $seen = [];
                            @endphp
                            <fieldset class="rounded-card border border-line px-3 py-2.5" wire:key="merge-field-{{ $conflict['field'] }}" data-merge-field="{{ $conflict['field'] }}">
                                <legend class="px-1 text-meta font-medium text-ink-2">{{ $conflict['label'] }}</legend>
                                <div class="space-y-1.5">
                                    @foreach($conflict['values'] as $recordId => $text)
                                        @continue(in_array($text, $seen, true))
                                        @php($seen[] = $text)
                                        <label class="flex cursor-pointer items-start gap-2 text-body">
                                            <input type="radio" wire:model="choices.{{ $conflict['field'] }}" value="{{ $recordId }}" class="mt-0.5 border-line-strong text-ink">
                                            <span class="min-w-0 flex-1">
                                                <span @class(['break-words', 'text-ink' => $text !== '', 'italic text-ink-3' => $text === ''])>{{ $text !== '' ? $text : __('(empty)') }}</span>
                                                <span class="block truncate text-meta text-ink-3">{{ $records[$recordId]['label'] ?? '' }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    @if($conflict['field'] === 'points' && $preview['pointsSum'] !== null)
                                        <label class="flex cursor-pointer items-start gap-2 text-body">
                                            <input type="radio" wire:model="choices.points" value="sum" class="mt-0.5 border-line-strong text-ink">
                                            <span>{{ __('Add them up (:sum)', ['sum' => $preview['pointsSum']]) }}</span>
                                        </label>
                                    @endif
                                </div>
                            </fieldset>
                        @empty
                            <p class="text-body text-ink-3">{{ __('No conflicts: every field has the same value.') }}</p>
                        @endforelse
                        @if($sameCount > 0 && $conflicts->isNotEmpty())
                            <p class="text-meta text-ink-3">{{ trans_choice('{1} :count other field is the same in all of them.|[2,*] :count other fields are the same in all of them.', $sameCount, ['count' => $sameCount]) }}</p>
                        @endif
                    </section>

                    <p class="rounded-card bg-sunken px-3 py-2 text-body text-ink-2" data-merge-summary>{{ $preview['summary'] }}</p>
                @else
                    <p class="text-body text-ink-3">{{ __('Add at least one more record to merge.') }}</p>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-line px-6 py-3">
                <button type="button" wire:click="close" class="ui-btn ui-btn-ghost">{{ __('Cancel') }}</button>
                <button type="button" wire:click="merge" wire:loading.attr="disabled" wire:target="merge" class="ui-btn ui-btn-primary" @disabled(count($preview['records']) < 2)>
                    {{ trans_choice('{0} Merge|{1} Merge|[2,*] Merge :count records', count($preview['records']), ['count' => count($preview['records'])]) }}
                </button>
            </div>
        </div>
    @endif
</div>
