<div
    x-data="{
        open: @js($isOpen),
        initialForm: null,
        results: { company: false, contact: false },
        form() {
            return JSON.stringify({
                wo: ($wire.woNumber || '').trim(),
                contact: ($wire.contactQuery || '').trim(),
                company: ($wire.companyQuery || '').trim(),
                location: ($wire.locationName || '').trim(),
                description: ($wire.description || '').trim(),
                deliverables: ($wire.deliverables || []).length,
                pending: ($wire.newName || '').trim(),
                phase: ($wire.phaseName || '').trim(),
                method: $wire.fulfillmentMethod || '',
                flags: Array.from($wire.flags || []).sort(),
                assignee: $wire.assigneeId || '',
                trello: ($wire.trelloLink || '').trim()
            });
        },
        isDirty() {
            return this.open && this.initialForm !== null && this.form() !== this.initialForm;
        },
        init() {
            this.$watch('$wire.isOpen', (value) => {
                this.open = Boolean(value);
                if (this.open) {
                    this.$nextTick(() => { this.initialForm = this.form(); });
                    window.KudosModalStack?.register('create-work-order', this.$el, () => this.requestClose());
                    window.KudosModalStack?.bringToFront('create-work-order');
                } else {
                    this.initialForm = null;
                    window.KudosModalStack?.unregister('create-work-order');
                }
            });
            this.$cleanup(() => window.KudosModalStack?.unregister('create-work-order'));
        },
        requestClose() {
            if (! this.isDirty() || ! window.KudosDirtyGuard) {
                this.open = false;
                $wire.closeModal();
                return;
            }
            if (window.KudosDirtyGuard.isConfirmModalOpen) {
                return;
            }
            window.KudosDirtyGuard.openConfirmModal({
                title: @js(__('Create this work order?')),
                description: @js(__('You have entered data for a new work order.')),
                cancelText: @js(__('Keep editing')),
                discardText: @js(__('Discard')),
                saveText: @js(__('Create work order')),
                onCancel: () => {},
                onDiscard: () => { this.open = false; $wire.closeModal(); },
                onSave: () => { $wire.save(); }
            });
        }
    }"
    @open-create-work-order.window="open = true"
    @keydown.window.escape="if (open && (window.KudosModalStack ? window.KudosModalStack.isTop('create-work-order') : true)) requestClose()"
    x-show="open"
    x-cloak
    class="fixed inset-0 flex items-center justify-center overflow-y-auto bg-stone-900/40 p-4"
    style="z-index: 300;"
    @click.self="requestClose()"
    data-create-work-order
>
    <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-panel border border-line bg-surface shadow-panel" role="dialog" aria-modal="true" aria-label="{{ __('Create work order') }}">
        <div class="flex items-start justify-between gap-3 border-b border-line px-6 py-4">
            <div class="min-w-0">
                <h2 class="text-section font-semibold text-ink">{{ __('Create work order') }}</h2>
                <p class="text-meta text-ink-3">{{ __('The work order and its deliverables start in Intake.') }}</p>
            </div>
            <button type="button" @click="requestClose()" class="ui-btn ui-btn-ghost ui-btn-icon shrink-0" aria-label="{{ __('Close') }}">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>

        @if($isOpen)
            <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    @if($submitError)
                        <div class="rounded-card bg-danger-soft px-3 py-2 text-body text-danger" role="alert" data-submit-error>{{ $submitError }}</div>
                    @endif

                    {{-- WO number and contact --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="cw-number">{{ __('WO number (optional)') }}</label>
                            <div class="flex items-center gap-2">
                                <div class="flex min-w-0 flex-1">
                                    <span class="inline-flex h-8 items-center rounded-l-control border border-r-0 border-line bg-sunken px-2.5 text-meta font-medium text-ink-2">{{ __('WO') }}</span>
                                    <input id="cw-number" type="text" wire:model="woNumber" placeholder="16350" class="ui-input min-w-0 rounded-l-none font-mono">
                                </div>
                                <button type="button" wire:click="generateWoNumber" wire:loading.attr="disabled" wire:target="generateWoNumber" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0" title="{{ __('Create next WO number automatically') }}">
                                    <x-lucide-loader-2 class="h-4 w-4 animate-spin" wire:loading wire:target="generateWoNumber" />
                                    <span>{{ __('Next WO') }}</span>
                                </button>
                            </div>
                            @error('woNumber') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="relative">
                            <label class="ui-label" for="cw-contact">{{ __('Contact') }}</label>
                            <input id="cw-contact" type="text" wire:model.live.debounce.250ms="contactQuery" @focus="results.contact = true" @click.outside="results.contact = false" autocomplete="off" placeholder="{{ __('Search contacts…') }}" class="ui-input">
                            @php($contactResults = $this->contactResults)
                            @if($contactResults->isNotEmpty())
                                <div x-show="results.contact" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-card border border-line bg-surface py-1 text-body shadow-menu" data-contact-results>
                                    @foreach($contactResults as $contact)
                                        <button type="button" wire:key="contact-{{ $contact->id }}" wire:click="selectContact({{ $contact->id }})" @click="results.contact = false" class="block w-full truncate px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ \App\Support\TitleCase::from($contact->name) }}</button>
                                    @endforeach
                                </div>
                            @endif
                            @if(trim($contactQuery) !== '' && $contactId === null && $contactResults->isEmpty())
                                <span class="mt-1 block text-meta text-ink-3">{{ __('A new contact will be created.') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Company and location --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="relative">
                            <label class="ui-label" for="cw-company">{{ __('Company') }} <span class="text-danger">*</span></label>
                            <input id="cw-company" type="text" wire:model.live.debounce.250ms="companyQuery" @focus="results.company = true" @click.outside="results.company = false" autocomplete="off" placeholder="{{ __('Search companies…') }}" class="ui-input">
                            @php($companyResults = $this->companyResults)
                            @if($companyResults->isNotEmpty())
                                <div x-show="results.company" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-card border border-line bg-surface py-1 text-body shadow-menu" data-company-results>
                                    @foreach($companyResults as $company)
                                        <button type="button" wire:key="company-{{ $company->id }}" wire:click="selectCompany({{ $company->id }})" @click="results.company = false" class="block w-full truncate px-3 py-1.5 text-left text-ink hover:bg-sunken">{{ \App\Support\TitleCase::from($company->name) }}</button>
                                    @endforeach
                                </div>
                            @endif
                            @if(trim($companyQuery) !== '' && $clientId === null && $companyResults->isEmpty())
                                <span class="mt-1 block text-meta text-ink-3">{{ __('A new company will be created.') }}</span>
                            @endif
                            @error('companyQuery') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="cw-location">{{ __('Location') }}</label>
                            <input id="cw-location" type="text" wire:model="locationName" list="cw-locations" autocomplete="off" placeholder="{{ __('Main location') }}" class="ui-input">
                            <datalist id="cw-locations">
                                @foreach($this->clientLocations as $clientLocation)
                                    <option value="{{ $clientLocation }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="ui-label" for="cw-description">{{ __('Description') }} <span class="text-danger">*</span></label>
                        <input id="cw-description" type="text" wire:model="description" placeholder="{{ __('e.g. Acrylic outdoor menu & lettering') }}" class="ui-input">
                        @error('description') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                    </div>

                    {{-- Deliverables --}}
                    <div>
                        <span class="ui-label">{{ __('Deliverables (size · material · quantity)') }} <span class="font-normal text-ink-3">{{ __('Leave empty to create one deliverable from the description.') }}</span></span>
                        @if($deliverables !== [])
                            <ul class="mb-2 divide-y divide-line rounded-card border border-line" data-deliverable-rows>
                                @foreach($deliverables as $index => $row)
                                    <li wire:key="row-{{ $index }}" class="flex items-center gap-3 px-3 py-2 text-body">
                                        <span class="min-w-0 flex-1 truncate text-ink">{{ $row['name'] }}</span>
                                        <span class="shrink-0 text-meta text-ink-3">{{ collect([$row['size'], $row['material'], $row['quantity']])->filter(fn ($part) => filled($part))->implode(' · ') }}</span>
                                        <button type="button" wire:click="removeDeliverableRow({{ $index }})" class="shrink-0 text-ink-3 hover:text-danger" aria-label="{{ __('Remove deliverable') }}"><x-lucide-x class="h-4 w-4" /></button>
                                    </li>
                                @endforeach
                            </ul>
                            @error('deliverables.*.name') <span class="mb-2 block text-meta text-danger">{{ $message }}</span> @enderror
                        @endif
                        <div class="grid grid-cols-6 gap-2">
                            <input type="text" wire:model="newName" wire:keydown.enter.prevent="addDeliverableRow" placeholder="{{ __('Deliverable name') }}" aria-label="{{ __('Deliverable name') }}" class="ui-input col-span-6 sm:col-span-2">
                            <input type="text" wire:model="newSize" wire:keydown.enter.prevent="addDeliverableRow" placeholder="{{ __('Size') }}" aria-label="{{ __('Size') }}" class="ui-input col-span-2 sm:col-span-1">
                            <input type="text" wire:model="newMaterial" wire:keydown.enter.prevent="addDeliverableRow" placeholder="{{ __('Material') }}" aria-label="{{ __('Material') }}" class="ui-input col-span-2 sm:col-span-1">
                            <input type="number" min="1" wire:model="newQuantity" wire:keydown.enter.prevent="addDeliverableRow" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" class="ui-input col-span-2 sm:col-span-1">
                            <button type="button" wire:click="addDeliverableRow" class="ui-btn ui-btn-secondary col-span-6 sm:col-span-1"><x-lucide-plus class="h-4 w-4" /><span>{{ __('Add') }}</span></button>
                        </div>
                        @error('newName') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        @error('newQuantity') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                    </div>

                    {{-- Phase and fulfillment --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="cw-phase">{{ __('Phase (optional)') }}</label>
                            <input id="cw-phase" type="text" wire:model="phaseName" placeholder="{{ __('No phases') }}" class="ui-input">
                        </div>
                        <div>
                            <label class="ui-label" for="cw-method">{{ __('Fulfillment method') }}</label>
                            <select id="cw-method" wire:model="fulfillmentMethod" class="ui-select">
                                <option value="">{{ __('Not decided yet') }}</option>
                                @foreach($fulfillmentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            @error('fulfillmentMethod') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Flags and designer --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <span class="ui-label">{{ __('Flags') }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($flagOptions as $flag)
                                    <button type="button" wire:key="flag-{{ $flag->value }}" wire:click="toggleFlag('{{ $flag->value }}')" class="ui-chip" aria-pressed="{{ in_array($flag->value, $flags, true) ? 'true' : 'false' }}">{{ $flag->label() }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <span class="ui-label">{{ __('Designer') }}</span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach($designers as $entry)
                                    <button type="button" wire:key="designer-{{ $entry['user']->id }}" wire:click="selectAssignee('{{ $entry['user']->id }}')" class="ui-chip" aria-pressed="{{ $assigneeId === (string) $entry['user']->id ? 'true' : 'false' }}" title="{{ __(':count in progress', ['count' => $entry['load']]) }}">
                                        <x-ui.person :person="$entry['user']" size="sm" />
                                        <span>{{ $entry['user']->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @if($mostAvailable)
                                <p class="mt-1 text-meta text-ink-3" data-most-available>{{ __('Most available: :name (:count in progress)', ['name' => $mostAvailable['user']->name, 'count' => $mostAvailable['load']]) }}</p>
                            @endif
                            @error('assigneeId') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Promised date and Trello link --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="cw-promised">{{ __('Promised date') }}</label>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="setPromisedPreset('today')" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">{{ __('Today') }}</button>
                                <button type="button" wire:click="setPromisedPreset('tomorrow')" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">{{ __('Tomorrow') }}</button>
                                <input id="cw-promised" type="date" wire:model="promisedDate" class="ui-input min-w-0">
                            </div>
                            @error('promisedDate') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="cw-trello">{{ __('Trello card link (reference)') }}</label>
                            <input id="cw-trello" type="text" wire:model="trelloLink" placeholder="trello.com/c/…" class="ui-input">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line px-6 py-3">
                    <button type="button" @click="requestClose()" class="ui-btn ui-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="ui-btn ui-btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <x-lucide-loader-2 class="h-4 w-4 animate-spin" wire:loading wire:target="save" />
                        <span>{{ __('Create work order') }}</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
