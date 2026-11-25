<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pb-6"
     x-data="{ dialog: null, note: '', ownerId: '', open(kind, id) { this.dialog = { kind, id }; this.note = ''; this.ownerId = ''; }, close() { this.dialog = null; } }">
    <x-ui.page-header id="tour-resolver-header" :title="__('Resolver')" :subtitle="__('Blocks and holds that need customer service, oldest first.')">
        <x-slot:toolbar>
            <div class="flex items-center gap-1 rounded-control bg-sunken p-0.5" role="tablist">
                <button type="button" role="tab" wire:click="setTab('blocked')" aria-selected="{{ $tab === 'blocked' ? 'true' : 'false' }}"
                    @class(['ui-btn ui-btn-sm', 'ui-btn-secondary' => $tab === 'blocked', 'ui-btn-ghost' => $tab !== 'blocked'])>
                    {{ __('Blocked') }} <span class="tabular-nums text-ink-3">{{ $this->counts['blocked'] }}</span>
                </button>
                <button type="button" role="tab" wire:click="setTab('hold')" aria-selected="{{ $tab === 'hold' ? 'true' : 'false' }}"
                    @class(['ui-btn ui-btn-sm', 'ui-btn-secondary' => $tab === 'hold', 'ui-btn-ghost' => $tab !== 'hold'])>
                    {{ __('On hold') }} <span class="tabular-nums text-ink-3">{{ $this->counts['hold'] }}</span>
                </button>
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    @if($this->visibleGroups === [])
        <div class="ui-card">
            <x-ui.empty icon="check-circle-2" :title="$tab === 'hold' ? __('Nothing on hold') : __('Nothing blocked')" :description="__('Everything is moving.')" />
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->visibleGroups as $group)
                @php
                    $workOrder = $group->workOrder;
                    $ageTone = $group->oldestAgeInDays >= 7 ? 'danger' : ($group->oldestAgeInDays >= 3 ? 'warn' : 'neutral');
                @endphp
                <section class="ui-card" wire:key="group-{{ $workOrder->id }}-{{ $tab }}">
                    <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
                        <x-ui.tag :tone="$ageTone" class="tabular-nums">{{ __(':days d', ['days' => $group->oldestAgeInDays]) }}</x-ui.tag>
                        <x-wo-badge :number="$workOrder->number" />
                        <span class="text-row font-medium text-ink truncate">{{ \App\Support\TitleCase::from($workOrder->client?->name) }}</span>
                        <x-orders.location :location="\App\Support\TitleCase::from($workOrder->clientLocation?->name)" />
                    </div>
                    <ul class="divide-y divide-line">
                        @foreach($group->items as $item)
                            @php
                                $phoneDigits = preg_replace('/\D/', '', (string) $item->clientPhone);
                                $contactName = \App\Support\TitleCase::from($workOrder->contact?->name);
                            @endphp
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3" wire:key="item-{{ $item->type }}-{{ $item->record->id }}">
                                <div class="min-w-0 flex-1 basis-64">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.tag :tone="$item->type === 'block' ? 'warn' : 'info'">{{ $item->reason->label() }}</x-ui.tag>
                                        <button type="button" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $item->deliverable->id }} })"
                                            class="truncate text-body font-medium text-ink hover:underline">{{ $item->deliverable->task_name }}</button>
                                    </div>
                                    @if(filled($item->note))
                                        <p class="mt-1 text-meta text-ink-3">{{ $item->note }}</p>
                                    @endif
                                </div>
                                <div class="min-w-32 text-meta text-ink-2">
                                    @if($item->type === 'block')
                                        <x-ui.person :person="$item->owner" size="sm" show-name />
                                    @else
                                        <x-ui.person :person="$item->deliverable->assignee" size="sm" show-name />
                                    @endif
                                </div>
                                <div class="min-w-40 text-meta text-ink-2">
                                    @if(filled($contactName))<p class="truncate">{{ $contactName }}</p>@endif
                                    @if(filled($item->clientPhone))
                                        <p class="flex items-center gap-2 tabular-nums">
                                            <a href="tel:{{ $phoneDigits }}" class="hover:underline">{{ $item->clientPhone }}</a>
                                            <a href="https://wa.me/{{ strlen($phoneDigits) === 10 ? '1'.$phoneDigits : $phoneDigits }}" target="_blank" rel="noopener" class="text-ink-3 hover:text-ink" title="{{ __('Open in WhatsApp') }}">{{ __('WhatsApp') }}</a>
                                        </p>
                                    @else
                                        <p class="text-ink-3">{{ __('No phone on file') }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if($item->type === 'block')
                                        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-on:click="open('owner', {{ $item->record->id }})">{{ __('Reassign owner') }}</button>
                                        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm" x-on:click="open('followup', {{ $item->record->id }})">{{ __('Log follow-up') }}</button>
                                        <button type="button" class="ui-btn ui-btn-primary ui-btn-sm" x-on:click="open('resolve', {{ $item->record->id }})">{{ __('Resolve') }}</button>
                                    @else
                                        <button type="button" class="ui-btn ui-btn-primary ui-btn-sm" wire:click="resume({{ $item->deliverable->id }})" wire:loading.attr="disabled">{{ __('Resume') }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif

    {{-- One shared dialog for every row: resolve, follow-up and reassign. --}}
    <div x-show="dialog" x-cloak style="display: none;" x-on:keydown.escape.window="close()" class="fixed inset-0 z-[500] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md space-y-3 rounded-panel border border-line bg-surface p-5 shadow-panel" x-on:click.outside="close()">
            <h3 class="text-row font-semibold text-ink">
                <span x-show="dialog?.kind === 'resolve'">{{ __('Resolve block') }}</span>
                <span x-show="dialog?.kind === 'followup'">{{ __('Log follow-up') }}</span>
                <span x-show="dialog?.kind === 'owner'">{{ __('Reassign owner') }}</span>
            </h3>
            <template x-if="dialog?.kind === 'owner'">
                <select x-model="ownerId" class="ui-select" aria-label="{{ __('Owner') }}">
                    <option value="">{{ __('Customer service queue') }}</option>
                    @foreach($this->owners as $owner)
                        <option value="{{ $owner['id'] }}">{{ $owner['name'] }}</option>
                    @endforeach
                </select>
            </template>
            <template x-if="dialog?.kind !== 'owner'">
                <textarea x-model="note" rows="3" class="ui-input" placeholder="{{ __('Note') }}"></textarea>
            </template>
            <div class="flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn-ghost" x-on:click="close()">{{ __('Cancel') }}</button>
                <button type="button" class="ui-btn ui-btn-primary"
                    x-bind:disabled="dialog?.kind === 'followup' && note.trim() === ''"
                    x-on:click="
                        if (dialog.kind === 'resolve') { $wire.resolveBlock(dialog.id, note); }
                        else if (dialog.kind === 'followup') { $wire.logFollowUp(dialog.id, note); }
                        else { $wire.reassignOwner(dialog.id, ownerId === '' ? null : Number(ownerId)); }
                        close();
                    ">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
