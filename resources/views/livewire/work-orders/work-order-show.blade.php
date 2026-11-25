@php
    $page = $this->page;
    $workOrder = $page->workOrder;
    $clientName = \App\Support\TitleCase::from($workOrder->client?->name);
    $locationName = \App\Support\TitleCase::from($workOrder->clientLocation?->name);
    $contactName = \App\Support\TitleCase::from($workOrder->contact?->name);
    $isPhased = $workOrder->phases->isNotEmpty();
    $reviewer = $this->reviewerName;
@endphp
<div class="h-full flex flex-col gap-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pb-6"
     x-data="{
        dialog: null,
        approve: { source: 'client', measurements: true, estimate: true, note: '' },
        receive: { method: '{{ $workOrder->fulfillment_method?->value ?? 'delivery' }}', date: '{{ now()->toDateString() }}', note: '' },
        piece: { name: '', size: '', material: '', quantity: '', phaseId: '' },
        phase: { name: '', date: '' },
        open(kind) { this.dialog = kind; },
        close() { this.dialog = null; },
        editing: false,
        saving: false,
        original: {{ \Illuminate\Support\Js::from([
            'number' => $workOrder->number,
            'estimate_invoice_number' => $workOrder->estimate_invoice_number,
            'promised_date' => $workOrder->promised_date?->toDateString(),
            'fulfillment_method' => $workOrder->fulfillment_method?->value,
            'client_id' => $workOrder->client_id,
            'client_location_id' => $workOrder->client_location_id,
            'contact_id' => $workOrder->contact_id,
            'client_name' => $clientName,
            'location_name' => $locationName,
            'contact_name' => $contactName,
        ]) }},
        form: {},
        options: { locations: [], contacts: [] },
        clientSearch: '',
        clientResults: [],
        methodLabels: {{ \Illuminate\Support\Js::from(collect(\App\Domain\Delivery\Enums\FulfillmentMethod::cases())->mapWithKeys(fn ($method) => [$method->value => $method->label()])->all()) }},
        async startEdit() {
            this.form = { ...this.original };
            this.clientSearch = this.original.client_name ?? '';
            this.clientResults = [];
            this.options = await $wire.clientOptions(this.original.client_id);
            this.editing = true;
        },
        async searchClients() {
            this.clientResults = this.clientSearch.trim().length < 2 ? [] : await $wire.searchClients(this.clientSearch);
        },
        async pickClient(client) {
            this.form.client_id = client.id;
            this.form.client_name = client.name;
            this.form.client_location_id = null;
            this.form.contact_id = null;
            this.clientSearch = client.name;
            this.clientResults = [];
            this.options = await $wire.clientOptions(client.id);
        },
        optionName(list, id) { return (list.find(item => String(item.id) === String(id)) ?? {}).name ?? ''; },
        async save() {
            const fields = [
                { key: 'number', label: @js(__('WO number')), old: this.original.number, now: this.form.number },
                { key: 'client_id', label: @js(__('Company')), old: this.original.client_name, now: this.form.client_name },
                { key: 'client_location_id', label: @js(__('Location')), old: this.original.location_name, now: this.optionName(this.options.locations, this.form.client_location_id) },
                { key: 'contact_id', label: @js(__('Contact')), old: this.original.contact_name, now: this.optionName(this.options.contacts, this.form.contact_id) },
                { key: 'estimate_invoice_number', label: @js(__('Estimate / invoice')), old: this.original.estimate_invoice_number, now: this.form.estimate_invoice_number },
                { key: 'promised_date', label: @js(__('Promised date')), old: this.original.promised_date, now: this.form.promised_date },
                { key: 'fulfillment_method', label: @js(__('Fulfillment method')), old: this.methodLabels[this.original.fulfillment_method] ?? '', now: this.methodLabels[this.form.fulfillment_method] ?? '' },
            ];
            const changes = {};
            const confirmations = [];
            for (const field of fields) {
                if (String(this.form[field.key] ?? '') !== String(this.original[field.key] ?? '')) {
                    changes[field.key] = this.form[field.key] ?? null;
                    confirmations.push({ field: field.label, from: field.old ?? '', to: field.now ?? '' });
                }
            }
            if (confirmations.length === 0) { this.editing = false; return; }
            for (const confirmation of confirmations) {
                if (! await KudosConfirmChange(confirmation)) { return; }
            }
            this.saving = true;
            await $wire.saveDetails(changes);
            this.saving = false;
            this.editing = false;
        },
     }">
    <x-ui.page-header :title="$clientName ?: __('No client')" :subtitle="__('Work order')">
        <x-slot:actions>
            <a href="{{ route('work-orders.index') }}" wire:navigate class="ui-btn ui-btn-ghost">{{ __('All work orders') }}</a>
            @if($this->canEdit)
                <button type="button" class="ui-btn ui-btn-secondary" x-on:click="startEdit()" x-show="! editing">{{ __('Edit details') }}</button>
            @endif
            <button type="button" class="ui-btn ui-btn-secondary" x-on:click="open('phase')">{{ __('Add phase') }}</button>
            <button type="button" class="ui-btn ui-btn-secondary" x-on:click="open('piece')">{{ __('Add deliverable') }}</button>
            @if($this->canLogReceipt)
                <button type="button" class="ui-btn ui-btn-secondary" x-on:click="open('receive')" @disabled($this->waitingForReceiptCount === 0)>{{ __('Confirm all received') }}</button>
            @endif
            <button type="button" class="ui-btn ui-btn-primary" x-on:click="open('approve')" @disabled($this->waitingForClientCount === 0)>{{ __('Approve all') }}</button>
        </x-slot:actions>
        <x-slot:toolbar>
            <x-wo-badge :number="$workOrder->number" />
            <x-orders.location :location="$locationName" />
            <x-work-orders.promise :date="$workOrder->promised_date" :health="$page->health" />
        </x-slot:toolbar>
    </x-ui.page-header>

    {{-- Record data: read view and manager edit form. --}}
    <section class="ui-card p-4">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-show="! editing">
            <div><dt class="ui-label">{{ __('Contact') }}</dt><dd class="text-body text-ink">{{ $contactName ?: '—' }}</dd></div>
            <div><dt class="ui-label">{{ __('Estimate / invoice') }}</dt><dd class="text-body text-ink">{{ $workOrder->estimate_invoice_number ?: '—' }}</dd></div>
            <div><dt class="ui-label">{{ __('Fulfillment method') }}</dt><dd class="text-body text-ink">{{ $workOrder->fulfillment_method?->label() ?? '—' }}</dd></div>
            <div>
                <dt class="ui-label">{{ __('Promised date') }}</dt>
                <dd class="text-body text-ink">
                    @if($isPhased)
                        <ul class="space-y-0.5">
                            @foreach($workOrder->phases as $phase)
                                <li class="tabular-nums">{{ $phase->name }} · {{ $phase->promised_date?->translatedFormat('j M') ?? '—' }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{ $workOrder->promised_date?->translatedFormat('j M Y') ?? '—' }}
                    @endif
                </dd>
            </div>
            @if($this->portalUrl)
                <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-2">
                    <dt class="ui-label !mb-0">{{ __('Client portal') }}</dt>
                    <dd class="flex items-center gap-2">
                        <a href="{{ $this->portalUrl }}" target="_blank" rel="noopener" class="text-body text-ink underline truncate">{{ __('Open client portal') }}</a>
                        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($this->portalUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">{{ __('Copy link') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </button>
                    </dd>
                </div>
            @endif
        </dl>

        <form x-show="editing" x-cloak x-on:submit.prevent="save()" class="space-y-4" style="display: none;">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block"><span class="ui-label">{{ __('WO number') }}</span><input type="text" x-model="form.number" class="ui-input"></label>
                <div class="relative">
                    <label class="block"><span class="ui-label">{{ __('Company') }}</span>
                        <input type="text" x-model="clientSearch" x-on:input.debounce.300ms="searchClients()" class="ui-input" autocomplete="off" placeholder="{{ __('Search companies...') }}"></label>
                    <ul x-show="clientResults.length" x-cloak class="absolute z-20 mt-1 w-full rounded-control border border-line bg-surface shadow-panel">
                        <template x-for="client in clientResults" :key="client.id">
                            <li><button type="button" class="block w-full px-3 py-1.5 text-left text-body hover:bg-sunken" x-text="client.name" x-on:click="pickClient(client)"></button></li>
                        </template>
                    </ul>
                </div>
                <label class="block"><span class="ui-label">{{ __('Location') }}</span>
                    <select x-model="form.client_location_id" class="ui-select">
                        <option value="">{{ __('No location') }}</option>
                        <template x-for="location in options.locations" :key="location.id"><option :value="location.id" x-text="location.name" :selected="String(location.id) === String(form.client_location_id)"></option></template>
                    </select></label>
                <label class="block"><span class="ui-label">{{ __('Contact') }}</span>
                    <select x-model="form.contact_id" class="ui-select">
                        <option value="">{{ __('No contact') }}</option>
                        <template x-for="contact in options.contacts" :key="contact.id"><option :value="contact.id" x-text="contact.name" :selected="String(contact.id) === String(form.contact_id)"></option></template>
                    </select></label>
                <label class="block"><span class="ui-label">{{ __('Estimate / invoice') }}</span><input type="text" x-model="form.estimate_invoice_number" class="ui-input"></label>
                <label class="block"><span class="ui-label">{{ __('Fulfillment method') }}</span>
                    <select x-model="form.fulfillment_method" class="ui-select">
                        <option value="">{{ __('Not set') }}</option>
                        @foreach(\App\Domain\Delivery\Enums\FulfillmentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select></label>
                <label class="block"><span class="ui-label">{{ __('Promised date') }}</span><input type="date" x-model="form.promised_date" class="ui-input"></label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn-ghost" x-on:click="editing = false">{{ __('Cancel') }}</button>
                <button type="submit" class="ui-btn ui-btn-primary" x-bind:disabled="saving">{{ __('Save changes') }}</button>
            </div>
        </form>
    </section>

    {{-- Roll-up meters. --}}
    <section class="grid gap-3 sm:grid-cols-2">
        @foreach([['label' => __('Approved by client'), 'value' => $page->approvedCount, 'bar' => 'bg-ink'], ['label' => __('Received'), 'value' => $page->receivedCount, 'bar' => 'bg-ok']] as $meter)
            @php
                $percent = $page->deliverablesCount > 0 ? round($meter['value'] / $page->deliverablesCount * 100) : 0;
            @endphp
            <div class="ui-card p-4">
                <div class="flex items-center justify-between text-body">
                    <span class="ui-label !mb-0">{{ $meter['label'] }}</span>
                    <span class="tabular-nums text-ink">{{ $meter['value'] }} / {{ $page->deliverablesCount }}</span>
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-sunken" role="progressbar" aria-valuenow="{{ $meter['value'] }}" aria-valuemin="0" aria-valuemax="{{ $page->deliverablesCount }}" aria-label="{{ $meter['label'] }}">
                    <div class="h-1.5 rounded-full {{ $meter['bar'] }}" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Alerts. --}}
    @if($page->alerts->isNotEmpty())
        <section class="ui-card divide-y divide-line" aria-label="{{ __('Alerts') }}">
            @foreach($page->alerts as $alert)
                @php
                    $overdue = $alert->due_on !== null && $alert->due_on->lt(now()->startOfDay());
                @endphp
                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="alert-{{ $alert->id }}">
                    <x-ui.tag :tone="$overdue ? 'danger' : 'warn'" icon="bell">{{ $alert->kind->label() }}</x-ui.tag>
                    @if($alert->due_on)<span class="text-meta text-ink-3 tabular-nums">{{ $alert->due_on->translatedFormat('j M') }}</span>@endif
                    <span class="flex-1"></span>
                    <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm" wire:click="logFollowUp({{ $alert->id }})" wire:loading.attr="disabled">{{ __('Log follow-up') }}</button>
                </div>
            @endforeach
        </section>
    @endif

    {{-- One section per phase (or one implicit batch). --}}
    @foreach($page->groups as $group)
        @php
            $phase = $group->phase;
            $readiness = $group->readiness;
            $canSend = $phase !== null || ! $isPhased;
            $shown = $this->hidesBlocked ? $group->deliverables->reject(fn ($each) => $each->openBlock !== null) : $group->deliverables;
            $leftBehind = $this->hidesBlocked ? $readiness->leftBehind->reject(fn ($each) => $each->openBlock !== null) : $readiness->leftBehind;
        @endphp
        <section class="ui-card" wire:key="group-{{ $phase?->id ?? 'batch' }}">
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
                <h2 class="text-row font-semibold text-ink">
                    @if($phase !== null){{ $phase->name }}@elseif($isPhased){{ __('Outside any phase') }}@else{{ __('Deliverables') }}@endif
                </h2>
                @if($phase?->promised_date)
                    <x-ui.tag class="tabular-nums">{{ __('Due :date', ['date' => $phase->promised_date->translatedFormat('j M')]) }}</x-ui.tag>
                @endif
                <span class="flex-1"></span>
                @if($canSend)
                    @unless($readiness->ready)
                        <span class="text-meta text-ink-3">
                            @if($readiness->total === 0){{ __('Nothing in internal review') }}@else{{ $reviewer }} {{ $readiness->approved }}/{{ $readiness->total }}@endif
                        </span>
                    @endunless
                    <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm" wire:click="sendToClient({{ $phase?->id ?? 'null' }})" wire:loading.attr="disabled" @disabled(! $readiness->ready)>{{ __('Send to client') }}</button>
                @endif
            </div>
            @if($canSend && $readiness->ready && $leftBehind->isNotEmpty())
                <p class="border-b border-line bg-sunken px-4 py-2 text-meta text-ink-2">
                    {{ __('Staying behind:') }} {{ $leftBehind->pluck('task_name')->implode(', ') }}
                </p>
            @elseif($canSend && ! $readiness->ready && $leftBehind->isNotEmpty())
                <p class="border-b border-line bg-sunken px-4 py-2 text-meta text-ink-2">
                    {{ __('Not ready yet:') }} {{ $leftBehind->pluck('task_name')->implode(', ') }}
                </p>
            @endif
            @if($shown->isEmpty())
                <p class="px-4 py-4 text-body text-ink-3">{{ __('No deliverables yet.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach($shown as $deliverable)
                        @php
                            $status = $deliverable->status;
                            $statusTone = match (true) {
                                $status === \App\Domain\Delivery\Enums\DeliverableStatus::DONE => 'ok',
                                $status === \App\Domain\Delivery\Enums\DeliverableStatus::CLIENT_REVIEW => 'info',
                                default => 'neutral',
                            };
                        @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 cursor-pointer hover:bg-sunken" wire:key="deliverable-{{ $deliverable->id }}"
                            role="button" tabindex="0"
                            x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })"
                            x-on:keydown.enter="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })">
                            <span class="min-w-0 flex-1 basis-56 truncate text-body font-medium text-ink">{{ $deliverable->task_name }}</span>
                            <x-work-orders.stepper :status="$status" />
                            <span class="flex flex-wrap items-center gap-1.5 min-w-40">
                                <x-ui.tag :tone="$statusTone">{{ $status?->label() }}</x-ui.tag>
                                @if($deliverable->openBlock)<x-ui.tag tone="warn" icon="octagon-alert">{{ $deliverable->openBlock->reason?->label() ?? __('Blocked') }}</x-ui.tag>@endif
                                @if($deliverable->openHold)<x-ui.tag tone="warn" icon="pause">{{ __('On hold') }}</x-ui.tag>@endif
                                @if(\App\Application\Delivery\Data\BatchReadiness::hasReviewerApproval($deliverable))<x-ui.tag tone="ok" icon="check">{{ $reviewer }}</x-ui.tag>@endif
                                @foreach($deliverable->flag_codes ?? [] as $flag)
                                    <x-ui.tag :tone="$flag === \App\Domain\Delivery\Enums\DeliverableFlag::URGENT ? 'danger' : 'neutral'">{{ $flag->label() }}</x-ui.tag>
                                @endforeach
                            </span>
                            <x-ui.person :person="$deliverable->assignee" size="sm" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach

    {{-- One shared dialog for every action. --}}
    <div x-show="dialog" x-cloak style="display: none;" x-on:keydown.escape.window="close()" class="fixed inset-0 z-[500] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md space-y-3 rounded-panel border border-line bg-surface p-5 shadow-panel" x-on:click.outside="close()">
            <template x-if="dialog === 'approve'">
                <div class="space-y-3">
                    <h3 class="text-row font-semibold text-ink">{{ __('Approve all') }}</h3>
                    <p class="text-meta text-ink-3">{{ trans_choice(':count deliverable is waiting for the client.|:count deliverables are waiting for the client.', $this->waitingForClientCount, ['count' => $this->waitingForClientCount]) }}</p>
                    <label class="block"><span class="ui-label">{{ __('Approved by') }}</span>
                        <select x-model="approve.source" class="ui-select">
                            <option value="client">{{ \App\Domain\Delivery\Enums\ApprovalSource::CLIENT->label() }}</option>
                            <option value="manager">{{ \App\Domain\Delivery\Enums\ApprovalSource::MANAGER->label() }}</option>
                        </select></label>
                    <label class="flex items-center gap-2 text-body"><input type="checkbox" x-model="approve.measurements"> {{ __('Measurements confirmed') }}</label>
                    <label class="flex items-center gap-2 text-body"><input type="checkbox" x-model="approve.estimate"> {{ __('Estimate approved') }}</label>
                    <textarea x-model="approve.note" rows="2" class="ui-input" placeholder="{{ __('Note (optional)') }}"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="ui-btn ui-btn-ghost" x-on:click="close()">{{ __('Cancel') }}</button>
                        <button type="button" class="ui-btn ui-btn-primary" x-on:click="$wire.approveAll(approve.source, approve.measurements, approve.estimate, approve.note).then(() => close())">{{ __('Approve all') }}</button>
                    </div>
                </div>
            </template>
            @if($this->canLogReceipt)
            <template x-if="dialog === 'receive'">
                <div class="space-y-3">
                    <h3 class="text-row font-semibold text-ink">{{ __('Confirm all received') }}</h3>
                    <p class="text-meta text-ink-3">{{ trans_choice(':count deliverable is waiting to be received.|:count deliverables are waiting to be received.', $this->waitingForReceiptCount, ['count' => $this->waitingForReceiptCount]) }}</p>
                    <label class="block"><span class="ui-label">{{ __('Fulfillment method') }}</span>
                        <select x-model="receive.method" class="ui-select">
                            @foreach(\App\Domain\Delivery\Enums\FulfillmentMethod::cases() as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select></label>
                    <label class="block"><span class="ui-label">{{ __('Received on') }}</span><input type="date" x-model="receive.date" class="ui-input"></label>
                    <textarea x-model="receive.note" rows="2" class="ui-input" placeholder="{{ __('Note (optional)') }}"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="ui-btn ui-btn-ghost" x-on:click="close()">{{ __('Cancel') }}</button>
                        <button type="button" class="ui-btn ui-btn-primary" x-on:click="$wire.confirmAllReceived(receive.method, receive.date, receive.note).then(() => close())">{{ __('Confirm all received') }}</button>
                    </div>
                </div>
            </template>
            @endif
            <template x-if="dialog === 'piece'">
                <div class="space-y-3">
                    <h3 class="text-row font-semibold text-ink">{{ __('Add deliverable') }}</h3>
                    <label class="block"><span class="ui-label">{{ __('Name') }}</span><input type="text" x-model="piece.name" class="ui-input"></label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="block"><span class="ui-label">{{ __('Size') }}</span><input type="text" x-model="piece.size" class="ui-input"></label>
                        <label class="block"><span class="ui-label">{{ __('Material') }}</span><input type="text" x-model="piece.material" class="ui-input"></label>
                        <label class="block"><span class="ui-label">{{ __('Quantity') }}</span><input type="number" min="1" x-model="piece.quantity" class="ui-input"></label>
                    </div>
                    @if($isPhased)
                        <label class="block"><span class="ui-label">{{ __('Phase') }}</span>
                            <select x-model="piece.phaseId" class="ui-select">
                                <option value="">{{ __('No phase') }}</option>
                                @foreach($workOrder->phases as $phase)<option value="{{ $phase->id }}">{{ $phase->name }}</option>@endforeach
                            </select></label>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" class="ui-btn ui-btn-ghost" x-on:click="close()">{{ __('Cancel') }}</button>
                        <button type="button" class="ui-btn ui-btn-primary" x-bind:disabled="piece.name.trim() === ''"
                            x-on:click="$wire.addDeliverable(piece.name, piece.size, piece.material, piece.quantity ? Number(piece.quantity) : null, piece.phaseId ? Number(piece.phaseId) : null).then(() => { piece = { name: '', size: '', material: '', quantity: '', phaseId: '' }; close(); })">{{ __('Add deliverable') }}</button>
                    </div>
                </div>
            </template>
            <template x-if="dialog === 'phase'">
                <div class="space-y-3">
                    <h3 class="text-row font-semibold text-ink">{{ __('Add phase') }}</h3>
                    <label class="block"><span class="ui-label">{{ __('Name') }}</span><input type="text" x-model="phase.name" class="ui-input" placeholder="{{ __('e.g. Phase 2 · Catering') }}"></label>
                    <label class="block"><span class="ui-label">{{ __('Promised date') }}</span><input type="date" x-model="phase.date" class="ui-input"></label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="ui-btn ui-btn-ghost" x-on:click="close()">{{ __('Cancel') }}</button>
                        <button type="button" class="ui-btn ui-btn-primary" x-bind:disabled="phase.name.trim() === ''"
                            x-on:click="$wire.addPhase(phase.name, phase.date).then(() => { phase = { name: '', date: '' }; close(); })">{{ __('Add phase') }}</button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
