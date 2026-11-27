<div
    x-data="{
        open: @js($isOpen),
        menu: false,
        uploading: '',
        progress: 0,
        uploadError: '',
        init() {
            this.$watch('$wire.isOpen', (value) => {
                this.open = Boolean(value);
                this.menu = false;
                if (this.open) {
                    window.KudosModalStack?.register('deliverable-panel', this.$el, () => this.close());
                    window.KudosModalStack?.bringToFront('deliverable-panel');
                } else {
                    window.KudosModalStack?.unregister('deliverable-panel');
                }
            });
            this.$cleanup(() => window.KudosModalStack?.unregister('deliverable-panel'));
        },
        close() {
            this.open = false;
            this.menu = false;
            $wire.closePanel();
        },
        text(key, value, phaseNames, flagLabels) {
            if (key === 'flags') {
                return (value || []).map((flag) => flagLabels[flag] || flag).join(', ');
            }
            if (key === 'phase_id') {
                return value ? (phaseNames[value] || String(value)) : '';
            }
            return value === null || value === undefined ? '' : String(value).trim();
        },
        toggleFlag(flag) {
            const flags = Array.from($wire.edit.flags || []);
            $wire.edit.flags = flags.includes(flag) ? flags.filter((each) => each !== flag) : [...flags, flag];
        },
        async confirmReviewer(select, field, from, none) {
            const to = select.value === '' ? none : select.options[select.selectedIndex].text;
            if (to === from) {
                return;
            }
            if (await window.KudosConfirmChange({ field, from, to })) {
                $wire.changeReviewer(select.value === '' ? null : parseInt(select.value, 10));
            } else {
                select.value = select.dataset.current || '';
            }
        },
        async confirmChoice(select, field, from, method) {
            const to = select.options[select.selectedIndex].text;
            if (to === from) {
                return;
            }
            if (await window.KudosConfirmChange({ field, from, to })) {
                $wire[method](select.value === '' ? null : select.value);
            } else {
                select.value = select.dataset.current || '';
            }
        },
        async confirmTaskPoints(select, taskId, field, from) {
            const to = select.options[select.selectedIndex].text;
            if (to === from) {
                return;
            }
            if (await window.KudosConfirmChange({ field, from, to })) {
                $wire.changeTaskPoints(taskId, select.value === '' ? null : select.value);
            } else {
                select.value = select.dataset.current || '';
            }
        },
        async confirmDueTarget(input, field, from, none) {
            if (input.value === '' || input.value === input.dataset.current) {
                input.value = input.dataset.current || '';
                return;
            }
            const to = new Date(input.value + 'T00:00:00').toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric' });
            if (await window.KudosConfirmChange({ field, from, to })) {
                $wire.changeDueTarget(input.value);
            } else {
                input.value = input.dataset.current || '';
            }
        },
        async uploadFiles(input) {
            this.uploadError = '';
            for (const file of Array.from(input.files || [])) {
                this.uploading = file.name;
                const session = await $wire.prepareUpload(file.name, file.type || 'application/octet-stream', file.size);
                if (! session || ! session.url) {
                    this.uploadError = $wire.noticeText || '';
                    $wire.dismissNotice();
                    break;
                }
                try {
                    const driveFileId = await new Promise((resolve, reject) => {
                        const request = new XMLHttpRequest();
                        request.open('PUT', session.url);
                        request.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
                        request.upload.onprogress = (event) => { this.progress = event.lengthComputable ? Math.round(event.loaded / event.total * 100) : 0; };
                        request.onload = () => {
                            try {
                                const id = JSON.parse(request.responseText).id;
                                request.status < 300 && id ? resolve(id) : reject(new Error('upload'));
                            } catch (error) {
                                reject(error);
                            }
                        };
                        request.onerror = () => reject(new Error('upload'));
                        request.send(file);
                    });
                    await $wire.finishUpload(driveFileId);
                } catch (error) {
                    this.uploadError = @js(__('The file could not be sent to Google Drive. Try again in a moment.'));
                    break;
                }
            }
            this.uploading = '';
            this.progress = 0;
            input.value = '';
        },
        async confirmSave(labels, phaseNames, flagLabels) {
            for (const key of Object.keys(labels)) {
                const from = this.text(key, $wire.details[key], phaseNames, flagLabels);
                const to = this.text(key, $wire.edit[key], phaseNames, flagLabels);
                if (from !== to && ! await window.KudosConfirmChange({ field: labels[key], from, to })) {
                    return;
                }
            }
            $wire.saveDetails();
        }
    }"
    @open-deliverable.window="open = true; menu = false"
    @keydown.window.escape="if (open && ! $event.defaultPrevented && (window.KudosModalStack ? window.KudosModalStack.isTop('deliverable-panel') : true)) close()"
    x-show="open"
    x-cloak
    class="fixed inset-0 flex justify-end"
    style="z-index: 300;"
    data-deliverable-panel
>
    <div class="fixed inset-0 bg-stone-900/30" @click="close()"></div>

    <aside class="fixed inset-y-0 right-0 flex w-full max-w-xl flex-col border-l border-line bg-surface shadow-panel" role="dialog" aria-modal="true" aria-label="{{ __('Deliverable') }}">
        @if($deliverable === null)
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <span class="text-body text-ink-3">{{ __('Loading…') }}</span>
                <button type="button" @click="close()" class="ui-btn ui-btn-ghost ui-btn-icon" aria-label="{{ __('Close') }}">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>
        @else
            @php
                $block = $deliverable->openBlock;
                $hold = $deliverable->openHold;
                $isClosed = $deliverable->status->isClosed();
                $company = \App\Support\TitleCase::from($workOrder?->displayName() ?: $deliverable->company_name);
                $location = \App\Support\TitleCase::from($workOrder?->clientLocation?->name);
                $workOrderUrl = $workOrder !== null && \Illuminate\Support\Facades\Route::has('work-orders.show') ? route('work-orders.show', $workOrder) : null;
            @endphp

            {{-- Header --}}
            <div class="shrink-0 border-b border-line px-5 py-4">
                <div class="flex items-center gap-2">
                    @if($workOrder?->number)
                        @if($workOrderUrl)
                            <a href="{{ $workOrderUrl }}" wire:navigate class="inline-flex"><x-wo-badge :number="$workOrder->number" /></a>
                            <a href="{{ $workOrderUrl }}" wire:navigate class="inline-flex items-center gap-0.5 whitespace-nowrap text-meta text-ink-3 hover:text-ink">
                                <x-lucide-arrow-up-right class="h-3.5 w-3.5" />
                                <span>{{ __('Open work order') }}</span>
                            </a>
                        @else
                            <x-wo-badge :number="$workOrder->number" />
                        @endif
                    @endif
                    <span class="flex-1"></span>
                    @if($can['block'] && ! $isClosed && $block === null)
                        <button type="button" wire:click="openSheet('block')" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Block') }}</button>
                    @endif
                    @if($primary)
                        <button type="button" wire:click="runPrimary" wire:loading.attr="disabled" wire:target="runPrimary" class="ui-btn ui-btn-primary ui-btn-sm" data-primary-action="{{ $primary['key'] }}">{{ $primary['label'] }}</button>
                    @endif
                    <div class="relative">
                        <button type="button" @click="menu = ! menu" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('More actions') }}" :aria-expanded="menu">
                            <x-lucide-ellipsis class="h-4 w-4" />
                        </button>
                        <div x-show="menu" x-cloak @click.outside="menu = false" @click="menu = false" class="absolute right-0 top-full z-10 mt-1 w-52 rounded-card border border-line bg-surface py-1 text-body shadow-menu">
                            @if($can['plan'] && ! $isClosed)
                                <button type="button" wire:click="openSheet('plan')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Plan for a day') }}</button>
                            @endif
                            @if($can['resolveBlock'] && $block !== null)
                                <button type="button" wire:click="openSheet('resolve')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Resolve block') }}</button>
                            @endif
                            @if($can['block'] && ! $isClosed && $block !== null)
                                <button type="button" wire:click="openSheet('block')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Block') }}</button>
                            @endif
                            @if($can['putOnHold'] && ! $isClosed && $hold === null)
                                <button type="button" wire:click="openSheet('hold')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Put on hold') }}</button>
                            @endif
                            @if($can['resume'] && $hold !== null)
                                <button type="button" wire:click="resume" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Resume') }}</button>
                            @endif
                            @if($can['requestChangesByReviewer'] && $deliverable->status === \App\Domain\Delivery\Enums\DeliverableStatus::INTERNAL_REVIEW)
                                <button type="button" wire:click="openSheet('reviewerChanges')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Send back to design') }}</button>
                            @endif
                            @if($can['requestProductionSample'] && $deliverable->status === \App\Domain\Delivery\Enums\DeliverableStatus::CLIENT_REVIEW)
                                <button type="button" wire:click="openSheet('sample')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Request production sample') }}</button>
                            @endif
                            @if($can['close'] && ! $isClosed)
                                <button type="button" wire:click="openSheet('close')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Close') }}</button>
                            @endif
                            @if($can['reopen'] && $isClosed)
                                <button type="button" wire:click="openSheet('reopen')" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Reopen') }}</button>
                            @endif
                            @if($can['duplicate'])
                                <button type="button" wire:click="duplicate" class="block w-full px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ __('Duplicate') }}</button>
                            @endif
                            @if($can['trash'])
                                <button type="button" wire:click="openSheet('trash')" class="block w-full px-3 py-1.5 text-left text-danger hover:bg-danger-soft">{{ __('Move to trash') }}</button>
                            @endif
                        </div>
                    </div>
                    <button type="button" @click="close()" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm" aria-label="{{ __('Close') }}">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>

                <h2 class="mt-3 text-section font-semibold text-ink text-balance">{{ $deliverable->task_name }}</h2>
                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="text-body text-ink-2">{{ $company }}</span>
                    <x-orders.location :location="$location" />
                    @if($deliverable->phase)
                        <span class="text-meta text-ink-3">{{ $deliverable->phase->name }}</span>
                    @endif
                </div>

                {{-- Status bar grouped by area --}}
                <div class="mt-3 flex items-end gap-3" aria-label="{{ __('Status') }}">
                    @foreach($stepper as $group)
                        <div class="min-w-0" style="flex: {{ count($group['steps']) }} 1 0%">
                            <div class="flex gap-1">
                                @foreach($group['steps'] as $step)
                                    <span title="{{ $step['label'] }}" @class([
                                        'h-1.5 flex-1 rounded-full',
                                        'bg-ink' => $step['state'] === 'current',
                                        'bg-ink-3' => $step['state'] === 'past',
                                        'bg-line' => $step['state'] === 'future',
                                    ])></span>
                                @endforeach
                            </div>
                            <div class="mt-1 truncate text-[11px] text-ink-3">{{ $group['area'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <x-ui.tag>{{ $deliverable->status->label() }}</x-ui.tag>
                    @foreach($deliverable->flag_codes ?? [] as $flag)
                        <x-ui.tag :tone="$flag === \App\Domain\Delivery\Enums\DeliverableFlag::URGENT ? 'danger' : 'neutral'">{{ $flag->label() }}</x-ui.tag>
                    @endforeach
                    @if($isClosed && $deliverable->close_reason)
                        <x-ui.tag tone="ok">{{ $deliverable->close_reason->label() }}</x-ui.tag>
                    @endif
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @if($noticeText)
                    <div class="mx-5 mt-4 flex items-start justify-between gap-3 rounded-card px-3 py-2 text-body {{ $noticeTone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-ok-soft text-ok' }}" role="status" data-notice="{{ $noticeTone }}">
                        <span>{{ $noticeText }}</span>
                        <button type="button" wire:click="dismissNotice" class="shrink-0" aria-label="{{ __('Dismiss') }}"><x-lucide-x class="h-4 w-4" /></button>
                    </div>
                @endif

                @if($block)
                    <div class="mx-5 mt-4 rounded-card bg-warn-soft px-3 py-2 text-body text-warn" data-banner="blocked">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{ __('Blocked: :reason', ['reason' => $block->reason->label()]) }}</span>
                            @if($can['resolveBlock'])
                                <button type="button" wire:click="openSheet('resolve')" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Resolve') }}</button>
                            @endif
                        </div>
                        @if($block->note)
                            <p class="mt-0.5">{{ $block->note }}</p>
                        @endif
                        <p class="mt-0.5 text-meta">{{ __('Owner: :name', ['name' => $block->owner?->name ?? __('Customer service queue')]) }} · {{ $block->raised_at?->diffForHumans() }}</p>
                    </div>
                @endif

                @if($hold)
                    <div class="mx-5 mt-4 rounded-card bg-info-soft px-3 py-2 text-body text-info" data-banner="hold">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{ __('On hold: :reason', ['reason' => $hold->reason->label()]) }}</span>
                            @if($can['resume'])
                                <button type="button" wire:click="resume" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Resume') }}</button>
                            @endif
                        </div>
                        @if($hold->note)
                            <p class="mt-0.5">{{ $hold->note }}</p>
                        @endif
                        <p class="mt-0.5 text-meta">{{ __('Since :date', ['date' => $hold->started_at?->diffForHumans()]) }}</p>
                    </div>
                @endif

                @if($deliverable->isSample() && $deliverable->sampleOf)
                    <div class="mx-5 mt-4 flex items-center justify-between gap-3 rounded-card border border-line px-3 py-2 text-body" data-banner="sample-of">
                        <span class="min-w-0 truncate">
                            <x-lucide-package class="mr-1 inline h-4 w-4 text-ink-3" aria-hidden="true" />
                            {{ __('Production sample of :name', ['name' => $deliverable->sampleOf->task_name]) }}
                            <span class="text-meta text-ink-3">· {{ $deliverable->sampleOf->status->label() }}</span>
                        </span>
                        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0" @click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->sampleOf->id }} })">{{ __('Open') }}</button>
                    </div>
                @endif

                @foreach($deliverable->samples()->latest('id')->get() as $sample)
                    <div class="mx-5 mt-4 flex items-center justify-between gap-3 rounded-card border border-line px-3 py-2 text-body" data-banner="sample" wire:key="sample-{{ $sample->id }}">
                        <span class="min-w-0 truncate">
                            <x-lucide-package class="mr-1 inline h-4 w-4 text-ink-3" aria-hidden="true" />
                            {{ __('Production sample') }}
                            <span class="text-meta text-ink-3">· {{ $sample->status->label() }}</span>
                        </span>
                        <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0" @click="$dispatch('open-deliverable', { deliverableId: {{ $sample->id }} })">{{ __('Open') }}</button>
                    </div>
                @endforeach

                @if($sheet)
                    @include('livewire.deliverables.partials.sheet')
                @endif

                {{-- Fields --}}
                @include('livewire.deliverables.partials.fields')

                {{-- Tabs --}}
                <div class="mt-5 border-b border-line px-5">
                    <div class="flex gap-4" role="tablist">
                        @foreach(['tasks' => __('Tasks'), 'approval' => __('Approval'), 'comments' => __('Comments & files'), 'timeline' => __('Timeline')] as $key => $label)
                            <button type="button" role="tab" wire:click="showTab('{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" @class([
                                '-mb-px border-b-2 pb-2 text-body font-medium',
                                'border-ink text-ink' => $tab === $key,
                                'border-transparent text-ink-3 hover:text-ink' => $tab !== $key,
                            ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="px-5 py-4" wire:key="tab-{{ $tab }}">
                    @includeWhen($tab === 'tasks', 'livewire.deliverables.partials.tasks')
                    @includeWhen($tab === 'approval', 'livewire.deliverables.partials.approval')
                    @includeWhen($tab === 'comments', 'livewire.deliverables.partials.comments')
                    @includeWhen($tab === 'timeline', 'livewire.deliverables.partials.timeline')
                </div>
            </div>
        @endif
    </aside>
</div>
