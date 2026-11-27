@php
    $planned = $deliverable->planned_for;
    $plannedText = $planned === null ? null : ($planned->isToday() ? __('Today') : ($planned->isTomorrow() ? __('Tomorrow') : $planned->translatedFormat('j M Y')));
    $due = $deliverable->due_target;
    $dueTone = $due === null || $isClosed ? 'neutral' : ($due->isPast() && ! $due->isToday() ? 'danger' : ($due->isToday() ? 'warn' : 'neutral'));
    $priority = $deliverable->priority ?? \App\Domain\Delivery\Enums\Priority::MEDIUM;
    $specs = collect([$deliverable->size, $deliverable->material, $deliverable->quantity])->filter(fn ($part) => filled($part))->implode(' · ');
@endphp

<section class="mt-4 px-5" aria-label="{{ __('Details') }}">
    @if(! $editing)
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
            <div>
                <dt class="text-meta text-ink-3">{{ __('Assignee') }}</dt>
                <dd class="mt-0.5"><x-ui.person :person="$deliverable->assignee" size="sm" show-name /></dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Reviewer') }}</dt>
                <dd class="mt-0.5">
                    @if($can['changeReviewer'])
                        <select class="ui-select" aria-label="{{ __('Reviewer') }}" data-field="reviewer" data-current="{{ $deliverable->reviewer_id }}"
                            @change="confirmReviewer($event.target, @js(__('Reviewer')), @js($deliverable->reviewer?->name ?? __('(empty)')), @js(__('(empty)')))">
                            <option value="" @selected($deliverable->reviewer_id === null)>{{ __('(empty)') }}</option>
                            @foreach($this->reviewerOptions as $option)
                                <option value="{{ $option->id }}" @selected($deliverable->reviewer_id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-ui.person :person="$deliverable->reviewer" size="sm" show-name />
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Planned for') }}</dt>
                <dd class="mt-0.5 text-body text-ink">{{ $plannedText ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Due target') }}</dt>
                <dd class="mt-0.5 text-body text-ink">
                    @if($can['changeDueTarget'] && ! $isClosed)
                        <input type="date" class="ui-input" aria-label="{{ __('Due target') }}" data-field="due-target" value="{{ $due?->toDateString() }}" data-current="{{ $due?->toDateString() }}"
                            @change="confirmDueTarget($event.target, @js(__('Due target')), @js($due?->translatedFormat('j M Y') ?? __('(empty)')), @js(__('(empty)')))">
                    @elseif($due)
                        <x-ui.tag :tone="$dueTone">{{ $due->translatedFormat('j M Y') }}</x-ui.tag>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Priority') }}</dt>
                <dd class="mt-0.5">
                    @if($can['updateDetails'] && ! $isClosed)
                        <select class="ui-select" aria-label="{{ __('Priority') }}" data-field="priority" data-current="{{ $priority->value }}"
                            @change="confirmChoice($event.target, @js(__('Priority')), @js($priority->label()), 'changePriority')">
                            @foreach(\App\Domain\Delivery\Enums\Priority::descending() as $option)
                                <option value="{{ $option->value }}" @selected($priority === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-board.priority :priority="$priority" show-label />
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Points') }}</dt>
                <dd class="mt-0.5 text-body text-ink" data-field="points">
                    @if($this->tasks->isEmpty() && $can['updateDetails'] && ! $isClosed)
                        <select class="ui-select" aria-label="{{ __('Points') }}" data-current="{{ $deliverable->points }}"
                            @change="confirmChoice($event.target, @js(__('Points')), @js($deliverable->points !== null ? (string) $deliverable->points : __('(empty)')), 'changePoints')">
                            <option value="" @selected($deliverable->points === null)>{{ __('(empty)') }}</option>
                            @foreach(\App\Domain\Delivery\Support\PointsEstimator::SCALE as $option)
                                <option value="{{ $option }}" @selected($deliverable->points === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="font-semibold tabular-nums">{{ $deliverable->points ?? '—' }}</span>
                        @if($this->tasks->isNotEmpty())
                            <span class="text-meta text-ink-3">{{ __('sum of its tasks') }}</span>
                        @endif
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Size · material · quantity') }}</dt>
                <dd class="mt-0.5 text-body text-ink">{{ $specs !== '' ? $specs : '—' }}</dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Files') }}</dt>
                <dd class="mt-0.5 text-body text-ink">
                    @if($deliverable->files_url)
                        <a href="{{ $deliverable->files_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 underline underline-offset-2">{{ __('Open files') }} <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-meta text-ink-3">{{ __('Phase') }}</dt>
                <dd class="mt-0.5 text-body text-ink">{{ $deliverable->phase?->name ?? '—' }}</dd>
            </div>
            <div class="col-span-1 sm:col-span-2">
                <dt class="text-meta text-ink-3">{{ __('Trello card (reference)') }}</dt>
                <dd class="mt-0.5 truncate text-body text-ink">
                    @if($deliverable->trello_card_url)
                        <a href="{{ $deliverable->trello_card_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 underline underline-offset-2">{{ __('Open Trello card') }} <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>
        @if($can['updateDetails'])
            <button type="button" wire:click="startEditing" class="ui-btn ui-btn-ghost ui-btn-sm mt-3 -ml-2.5">
                <x-lucide-pencil class="h-3.5 w-3.5" />
                <span>{{ __('Edit details') }}</span>
            </button>
        @endif
    @else
        <form @submit.prevent="confirmSave(@js($fieldLabels), @js($phaseNames), @js($flagLabels))" class="space-y-3" data-edit-form>
            <div>
                <label class="ui-label" for="dp-name">{{ __('Name') }}</label>
                <input id="dp-name" type="text" wire:model="edit.name" class="ui-input">
                @error('edit.name') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="ui-label" for="dp-size">{{ __('Size') }}</label>
                    <input id="dp-size" type="text" wire:model="edit.size" class="ui-input">
                </div>
                <div>
                    <label class="ui-label" for="dp-material">{{ __('Material') }}</label>
                    <input id="dp-material" type="text" wire:model="edit.material" class="ui-input">
                </div>
                <div>
                    <label class="ui-label" for="dp-quantity">{{ __('Quantity') }}</label>
                    <input id="dp-quantity" type="number" min="1" wire:model="edit.quantity" class="ui-input">
                    @error('edit.quantity') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="ui-label" for="dp-files">{{ __('Files link') }}</label>
                    <input id="dp-files" type="text" wire:model="edit.files_url" class="ui-input" placeholder="https://">
                </div>
                <div>
                    <label class="ui-label" for="dp-trello">{{ __('Trello card (reference)') }}</label>
                    <input id="dp-trello" type="text" wire:model="edit.trello_card_url" class="ui-input" placeholder="trello.com/c/…">
                </div>
            </div>
            <div>
                <label class="ui-label" for="dp-phase">{{ __('Phase') }}</label>
                <select id="dp-phase" wire:model="edit.phase_id" class="ui-select">
                    <option value="">{{ __('No phase') }}</option>
                    @foreach($phases as $phase)
                        <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                    @endforeach
                </select>
                @error('edit.phase_id') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
            </div>
            <div>
                <span class="ui-label">{{ __('Flags') }}</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($flags as $flag)
                        <button type="button" class="ui-chip" :aria-pressed="Array.from($wire.edit.flags || []).includes('{{ $flag->value }}')" @click="toggleFlag('{{ $flag->value }}')">{{ $flag->label() }}</button>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm" wire:loading.attr="disabled" wire:target="saveDetails">{{ __('Save changes') }}</button>
                <button type="button" wire:click="cancelEditing" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif
</section>
