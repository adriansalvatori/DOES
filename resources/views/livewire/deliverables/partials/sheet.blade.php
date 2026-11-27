<div class="mx-5 mt-4 rounded-card border border-line bg-canvas p-3" data-sheet="{{ $sheet }}">
    @switch($sheet)
        @case('block')
            <p class="ui-section-title">{{ __('Block this deliverable') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('It stays where it is and cannot move forward until the block is resolved. Customer service gets it.') }}</p>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="ui-label" for="dp-reason">{{ __('Reason') }}</label>
                    <select id="dp-reason" wire:model="sheetReason" class="ui-select">
                        <option value="">{{ __('Choose a reason') }}</option>
                        @foreach($blockReasons as $reason)
                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                    @error('sheetReason') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                    <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
                    @error('sheetNote') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
            </div>
            @php($submit = 'submitBlock')
            @php($submitLabel = __('Block'))
            @break

        @case('hold')
            <p class="ui-section-title">{{ __('Put on hold') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('It keeps its status but drops out of the daily lists until it is resumed.') }}</p>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="ui-label" for="dp-reason">{{ __('Reason') }}</label>
                    <select id="dp-reason" wire:model="sheetReason" class="ui-select">
                        <option value="">{{ __('Choose a reason') }}</option>
                        @foreach($holdReasons as $reason)
                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                    @error('sheetReason') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                    <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
                </div>
            </div>
            @php($submit = 'submitHold')
            @php($submitLabel = __('Put on hold'))
            @break

        @case('resolve')
            <p class="ui-section-title">{{ __('Resolve block') }}</p>
            <div class="mt-3">
                <label class="ui-label" for="dp-note">{{ __('What fixed it?') }}</label>
                <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
            </div>
            @php($submit = 'submitResolve')
            @php($submitLabel = __('Resolve'))
            @break

        @case('close')
            <p class="ui-section-title">{{ __('Close this deliverable') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('Open blocks and holds close with it.') }}</p>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="ui-label" for="dp-reason">{{ __('Reason') }}</label>
                    <select id="dp-reason" wire:model="sheetReason" class="ui-select">
                        <option value="">{{ __('Choose a reason') }}</option>
                        @foreach($closeReasons as $reason)
                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                    @error('sheetReason') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                    <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
                </div>
            </div>
            @php($submit = 'submitClose')
            @php($submitLabel = __('Close'))
            @break

        @case('reopen')
            <p class="ui-section-title">{{ __('Reopen this deliverable') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('It goes back to the status it had before it was closed.') }}</p>
            <div class="mt-3">
                <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
            </div>
            @php($submit = 'submitReopen')
            @php($submitLabel = __('Reopen'))
            @break

        @case('sample')
            <p class="ui-section-title">{{ __('Request a production sample') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('A sample card starts in Production files and goes through production and delivery on its own. This deliverable stays in Client review, and its client follow-ups pause until every sample is delivered or cancelled.') }}</p>
            <div class="mt-3">
                <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input" placeholder="{{ __('Size, material or anything the sample needs') }}"></textarea>
                @error('sheetNote') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
            </div>
            @php($submit = 'submitSample')
            @php($submitLabel = __('Request sample'))
            @break

        @case('trash')
            <p class="ui-section-title">{{ __('Move this deliverable to the trash?') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('Its tasks and alerts go with it. A manager can restore it from the trash.') }}</p>
            @php($submit = 'submitTrash')
            @php($submitLabel = __('Move to trash'))
            @break

        @case('plan')
            <p class="ui-section-title">{{ __('Plan for a day') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="ui-label" for="dp-assignee">{{ __('Assignee') }}</label>
                    <select id="dp-assignee" wire:model="planAssigneeId" class="ui-select">
                        <option value="">{{ __('Choose a person') }}</option>
                        @foreach($this->assignableUsers as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error('planAssigneeId') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ui-label" for="dp-day">{{ __('Day') }}</label>
                    <input id="dp-day" type="date" wire:model="planDay" class="ui-input">
                    @error('planDay') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
            </div>
            @if(\App\Domain\Delivery\Support\Roles::isManager(auth()->user()))
                <label class="mt-2 flex items-center gap-2 text-body text-ink-2">
                    <input type="checkbox" wire:model="planOverride" class="rounded border-line">
                    <span>{{ __('Plan even if the person is at their work limit') }}</span>
                </label>
            @endif
            @php($submit = 'submitPlan')
            @php($submitLabel = __('Plan'))
            @break

        @case('receipt')
            <p class="ui-section-title">{{ __('Log receipt') }}</p>
            <p class="mt-0.5 text-meta text-ink-3">{{ __('Logging how it was handed over completes the deliverable.') }}</p>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="ui-label" for="dp-method">{{ __('Fulfillment method') }}</label>
                    <select id="dp-method" wire:model="receiptMethod" class="ui-select">
                        <option value="">{{ __('Choose a method') }}</option>
                        @foreach($fulfillmentMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                    @error('receiptMethod') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ui-label" for="dp-note">{{ __('Note') }}</label>
                    <textarea id="dp-note" rows="2" wire:model="sheetNote" class="ui-input"></textarea>
                </div>
            </div>
            @php($submit = 'submitReceipt')
            @php($submitLabel = __('Log receipt'))
            @break

        @case('reviewerChanges')
            <p class="ui-section-title">{{ __('Send back to design') }}</p>
            <div class="mt-3">
                <label class="ui-label" for="dp-note">{{ __('What needs to change?') }}</label>
                <textarea id="dp-note" rows="3" wire:model="sheetNote" class="ui-input"></textarea>
                @error('sheetNote') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
            </div>
            @php($submit = 'submitReviewerChanges')
            @php($submitLabel = __('Send back'))
            @break

        @case('clientChanges')
            <p class="ui-section-title">{{ __('The client asked for changes') }}</p>
            <div class="mt-3">
                <label class="ui-label" for="dp-note">{{ __('What did the client ask for?') }}</label>
                <textarea id="dp-note" rows="3" wire:model="sheetNote" class="ui-input"></textarea>
                @error('sheetNote') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
            </div>
            @php($submit = 'submitClientChanges')
            @php($submitLabel = __('Record client changes'))
            @break
    @endswitch

    <div class="mt-3 flex items-center gap-2">
        <button type="button" wire:click="{{ $submit }}" wire:loading.attr="disabled" wire:target="{{ $submit }}" @class(['ui-btn ui-btn-sm', 'ui-btn-danger' => $sheet === 'trash', 'ui-btn-primary' => $sheet !== 'trash'])>{{ $submitLabel }}</button>
        <button type="button" wire:click="closeSheet" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Cancel') }}</button>
    </div>
</div>
