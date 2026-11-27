@php
    $plan = $this->plan;
    $rows = $this->rows;
    $columns = $plan->columns();
    $today = now()->toDateString();
    $term = mb_strtolower(trim($search));
    $byPeople = $layout === 'people';
    $columnTotals = collect($columns)->mapWithKeys(fn (string $column): array => [$column => [
        'open' => collect($rows)->sum(fn (array $row): int => $row['cells'][$column]['open']),
        'total' => collect($rows)->sum(fn (array $row): int => $row['cells'][$column]['tasks']->count() + $row['cells'][$column]['deliverables']->count()),
    ]]);
    $peopleOptions = collect($this->people)->map(fn ($person) => ['id' => $person->id, 'name' => $person->name])->values();
@endphp
<div class="h-full flex flex-col gap-3 min-h-0 select-none"
     x-data="planWeek"
     x-on:dragend.window="end()"
     x-on:keydown.window="keydown($event)"
     x-on:plan-week-undoable.window="showUndo($event.detail.message)">
    <x-ui.page-header :title="__('Plan week')" :subtitle="$weekStartDate->translatedFormat('j M').' – '.$weekStartDate->addDays(4)->translatedFormat('j M')">
        <x-slot:actions>
            @if(count($this->alerts) > 0)
                <div class="relative" x-data="{ open: false }">
                    <button type="button" class="ui-btn ui-btn-secondary" x-on:click="open = ! open" x-on:click.outside="open = false">
                        <x-lucide-alarm-clock class="w-4 h-4 text-danger" aria-hidden="true" />
                        {{ trans_choice('{1} 1 promise at risk|[2,*] :count promises at risk', count($this->alerts), ['count' => count($this->alerts)]) }}
                    </button>
                    <div x-show="open" x-cloak class="absolute right-0 top-full z-40 mt-1 w-96 max-h-96 overflow-y-auto rounded-card border border-line bg-surface p-1 shadow-menu">
                        @foreach($this->alerts as $alert)
                            <button type="button" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left text-meta hover:bg-sunken"
                                    x-on:click="open = false; $dispatch('open-deliverable', { deliverableId: {{ $alert['deliverableId'] }} })">
                                <x-ui.tag :tone="$alert['overdue'] ? 'danger' : 'warn'">{{ \Carbon\CarbonImmutable::parse($alert['promised'])->translatedFormat('j M') }}</x-ui.tag>
                                <span class="truncate text-ink">{{ $alert['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <button type="button" wire:click="previousWeek" class="ui-btn ui-btn-secondary ui-btn-icon" aria-label="{{ __('Previous week') }}" title="{{ __('Previous week') }}">
                <x-lucide-chevron-left class="w-4 h-4" />
            </button>
            <button type="button" wire:click="thisWeek" class="ui-btn ui-btn-secondary" @disabled($isCurrentWeek)>{{ __('This week') }}</button>
            <button type="button" wire:click="nextWeek" class="ui-btn ui-btn-secondary ui-btn-icon" aria-label="{{ __('Next week') }}" title="{{ __('Next week') }}">
                <x-lucide-chevron-right class="w-4 h-4" />
            </button>
            <label class="relative ui-btn ui-btn-secondary ui-btn-icon" title="{{ __('Go to date') }}">
                <x-lucide-calendar-days class="w-4 h-4" aria-hidden="true" />
                <input type="date" class="absolute inset-0 cursor-pointer opacity-0" aria-label="{{ __('Go to date') }}" value="{{ $weekStartDate->toDateString() }}"
                       x-on:click="$el.showPicker && $el.showPicker()" x-on:change="$wire.goToDate($event.target.value)">
            </label>
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-1 rounded-control bg-sunken p-0.5" role="tablist" aria-label="{{ __('Designer') }}">
                <button type="button" role="tab" wire:click="$set('designerFilter', '')" aria-selected="{{ $designerFilter === '' ? 'true' : 'false' }}"
                        @class(['ui-btn ui-btn-sm', 'ui-btn-secondary' => $designerFilter === '', 'ui-btn-ghost' => $designerFilter !== ''])>{{ __('Everyone') }}</button>
                @foreach($this->people as $person)
                    <button type="button" role="tab" wire:click="$set('designerFilter', '{{ $person->id }}')" aria-selected="{{ $designerFilter === (string) $person->id ? 'true' : 'false' }}"
                            @class(['ui-btn ui-btn-sm gap-1.5', 'ui-btn-secondary' => $designerFilter === (string) $person->id, 'ui-btn-ghost' => $designerFilter !== (string) $person->id])>
                        <x-ui.person :person="$person" size="sm" />
                        {{ \Illuminate\Support\Str::of($person->name)->before(' ') }}
                    </button>
                @endforeach
            </div>
            @if($this->canEdit)
                <button type="button" class="ui-btn ui-btn-sm" x-on:click="toggleTray()" x-bind:class="trayOpen ? 'ui-btn-secondary' : 'ui-btn-ghost'" x-bind:aria-pressed="trayOpen">
                    <x-lucide-inbox class="w-4 h-4" aria-hidden="true" /> {{ __('To plan') }} <span class="tabular-nums text-ink-3">{{ $this->backlog->count() }}</span>
                </button>
                <div class="relative w-56">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search WO, client or piece') }}" class="ui-input pl-8">
                </div>
            @endif
            <select wire:model.live="sort" class="ui-select w-auto" aria-label="{{ __('Order') }}">
                <option value="manual">{{ __('Manual order') }}</option>
                <option value="priority">{{ __('By priority') }}</option>
            </select>
            <div class="inline-flex items-center gap-0.5 rounded-control bg-sunken p-0.5" role="group" aria-label="{{ __('Layout') }}">
                <button type="button" wire:click="$set('layout', 'days')" @class(['ui-btn ui-btn-sm ui-btn-icon', 'ui-btn-secondary' => ! $byPeople, 'ui-btn-ghost' => $byPeople]) title="{{ __('Days across') }}" aria-pressed="{{ $byPeople ? 'false' : 'true' }}"><x-lucide-rows-3 class="w-4 h-4" /></button>
                <button type="button" wire:click="$set('layout', 'people')" @class(['ui-btn ui-btn-sm ui-btn-icon', 'ui-btn-secondary' => $byPeople, 'ui-btn-ghost' => ! $byPeople]) title="{{ __('Designers across') }}" aria-pressed="{{ $byPeople ? 'true' : 'false' }}"><x-lucide-columns-3 class="w-4 h-4" /></button>
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="flex-1 min-h-0 grid gap-3" @if($this->canEdit) x-bind:class="trayOpen ? 'grid-cols-[14rem_minmax(0,1fr)]' : 'grid-cols-1'" @endif>
        @if($this->canEdit)
            <aside x-show="trayOpen" x-cloak class="ui-card min-h-0 overflow-y-auto p-1.5 space-y-1"
                   x-bind:class="dragging ? 'ring-2 ring-line-strong' : ''"
                   x-on:dragover.prevent x-on:drop="dropOnBacklog($event)"
                   aria-label="{{ __('Work to plan') }}">
                <div class="flex items-start justify-between gap-1 px-1 pt-0.5">
                    <div>
                        <p class="ui-section-title">{{ __('To plan') }} · {{ $this->backlog->count() }}</p>
                        <p class="text-[11px] text-ink-3">{{ __('Drag onto a day. Sorted by promised date.') }}</p>
                    </div>
                    <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" x-on:click="toggleTray()" aria-label="{{ __('Hide') }}" title="{{ __('Hide') }}"><x-lucide-panel-left-close class="w-4 h-4" /></button>
                </div>
                @forelse($this->backlog as $deliverable)
                    @include('livewire.plan-week.partials.deliverable-card', ['deliverable' => $deliverable, 'draggable' => true, 'inBacklog' => true, 'term' => $term])
                @empty
                    <p class="px-1 py-6 text-center text-body text-ink-3">{{ __('Nothing ready to plan.') }}</p>
                @endforelse

                @if($this->blockedBacklog->isNotEmpty())
                    <p class="ui-section-title px-1 pt-3">{{ __('Blocked · can\'t be planned') }} · {{ $this->blockedBacklog->count() }}</p>
                    @foreach($this->blockedBacklog as $deliverable)
                        @include('livewire.plan-week.partials.deliverable-card', ['deliverable' => $deliverable, 'draggable' => false, 'inBacklog' => true, 'term' => $term])
                    @endforeach
                @endif
            </aside>
        @endif

        <section x-ref="grid" class="relative min-h-0 overflow-auto custom-vertical-scrollbar" x-on:mousedown="boxStart($event)">
            @if(count($rows) === 0)
                <p class="py-10 text-center text-body text-ink-3">{{ __('No designers to plan.') }}</p>
            @elseif(! $byPeople)
                {{-- Days across: one row per person. --}}
                <div class="grid gap-1.5 pb-16" style="grid-template-columns: 7.5rem repeat({{ count($columns) - 1 }}, minmax(8.5rem, 1fr)) minmax(7.5rem, 0.85fr); min-width: 57rem;">
                    <span class="sticky top-0 z-10 bg-canvas"></span>
                    @foreach($columns as $column)
                        @include('livewire.plan-week.partials.column-head', ['column' => $column, 'plan' => $plan, 'totals' => $columnTotals[$column], 'today' => $today])
                    @endforeach
                    @foreach($rows as $row)
                        @include('livewire.plan-week.partials.row-head', ['row' => $row])
                        @foreach($columns as $column)
                            @include('livewire.plan-week.partials.cell', ['row' => $row, 'column' => $column, 'today' => $today, 'term' => $term, 'carried' => $plan->carriedTaskIds])
                        @endforeach
                    @endforeach
                </div>
            @else
                {{-- Designers across: one column per person, days stacked. --}}
                <div class="grid gap-1.5 pb-16" style="grid-template-columns: 6.5rem repeat({{ count($rows) }}, minmax(15rem, 1fr)); min-width: {{ 6.5 + count($rows) * 15 }}rem;">
                    <span class="sticky top-0 z-10 bg-canvas"></span>
                    @foreach($rows as $row)
                        <div class="sticky top-0 z-10 bg-canvas pb-1">@include('livewire.plan-week.partials.row-head', ['row' => $row])</div>
                    @endforeach
                    @foreach($columns as $column)
                        <div class="sticky left-0 z-[5] bg-canvas">@include('livewire.plan-week.partials.column-head', ['column' => $column, 'plan' => $plan, 'totals' => $columnTotals[$column], 'today' => $today, 'stacked' => true])</div>
                        @foreach($rows as $row)
                            @include('livewire.plan-week.partials.cell', ['row' => $row, 'column' => $column, 'today' => $today, 'term' => $term, 'carried' => $plan->carriedTaskIds])
                        @endforeach
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <datalist id="plan-week-templates">
        @foreach($this->templateNames as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>

    {{-- Box selection --}}
    <div x-show="box" x-cloak class="pointer-events-none fixed z-50 rounded border border-ink/40 bg-ink/5" x-bind:style="boxStyle"></div>

    {{-- Bulk bar --}}
    <div x-show="selected.length > 0" x-cloak x-transition.opacity
         class="fixed bottom-5 left-1/2 z-50 flex -translate-x-1/2 flex-wrap items-center gap-1 rounded-card border border-line bg-surface px-2 py-1.5 shadow-panel" data-no-box>
        <span class="px-1.5 text-meta font-medium text-ink" x-text="@js(__(':count selected')).replace(':count', selected.length)"></span>
        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-on:click="bulkMove('today')">{{ __('Today') }}</button>
        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-on:click="bulkMove('tomorrow')">{{ __('Tomorrow') }}</button>
        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-on:click="bulkMove('next_week')">{{ __('Next week') }}</button>
        @if($this->canEdit)
            <select class="ui-select h-7 w-auto text-meta" aria-label="{{ __('Hand to') }}" x-on:change="bulkAssign($event.target.value); $event.target.value = ''">
                <option value="">{{ __('Hand to…') }}</option>
                @foreach($peopleOptions as $person)
                    <option value="{{ $person['id'] }}">{{ $person['name'] }}</option>
                @endforeach
            </select>
        @endif
        <button type="button" class="ui-btn ui-btn-primary ui-btn-sm" x-on:click="bulkComplete()"><x-lucide-check class="w-3.5 h-3.5" /> {{ __('Done') }}</button>
        <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" x-on:click="clearSelection()" aria-label="{{ __('Clear selection') }}" title="Esc"><x-lucide-x class="w-4 h-4" /></button>
    </div>

    {{-- Undo toast --}}
    <div x-show="undoMessage" x-cloak x-transition.opacity
         class="fixed bottom-5 left-5 z-50 flex items-center gap-3 rounded-lg border border-stone-800 bg-stone-900 px-3.5 py-2 text-xs font-medium text-white shadow-panel" data-no-box>
        <span x-text="undoMessage"></span>
        <button type="button" class="font-semibold underline" x-on:click="undo()">{{ __('Undo') }}</button>
        <span class="text-white/50">⌘Z</span>
    </div>
</div>
