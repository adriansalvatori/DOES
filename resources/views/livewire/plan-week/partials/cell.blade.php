{{--
    One person's day (or their Next week). A drop target for cards and backlog work when the viewer may edit the row;
    a line shows where a dragged card will land. "+ Add task" adds a task in place.
--}}
@php
    $person = $row['assignee'];
    $cell = $row['cells'][$column];
    $editable = $this->canEditRow($person);
    $key = ($person?->id ?? 'none').':'.$column;
    $isNext = $column === \App\Application\Delivery\Data\PlanWeek::NEXT_WEEK;
    $isToday = $column === $today;
    $isPast = ! $isNext && $column < $today;
    $limit = $row['limit'];
    $count = $cell['tasks']->count() + $cell['deliverables']->count();
@endphp
<div wire:key="cell-{{ $key }}" data-cell="{{ $key }}"
     @class([
         'group/cell flex min-h-24 flex-col gap-1 rounded-control border p-1 transition-colors',
         'border-ink/25 bg-surface' => $isToday,
         'border-line bg-canvas' => $isPast,
         'border-dashed border-line-strong bg-canvas' => $isNext,
         'border-line bg-surface' => ! $isToday && ! $isPast && ! $isNext,
     ])
     @if($editable)
         x-on:dragover="over($event, '{{ $key }}')"
         x-on:dragleave="if (! $el.contains($event.relatedTarget)) { overCell = null }"
         x-on:drop="drop($event, {{ $person->id }}, '{{ $column }}')"
         x-bind:class="overCell === '{{ $key }}' && 'ring-2 ring-ink/30 bg-sunken!'"
     @endif>
    <div class="flex items-center justify-between px-0.5 text-[11px] tabular-nums text-ink-3">
        <span>{{ $count > 0 ? trans_choice('{1} 1 card|[2,*] :count cards', $count, ['count' => $count]).' · '.trans_choice('{0} 0 pts|{1} 1 pt|[2,*] :count pts', $cell['points'], ['count' => $cell['points']]) : '' }}</span>
        @if($limit > 0 && $cell['used'] > 0)
            <span @class(['text-danger font-semibold' => $cell['used'] > $limit, 'text-warn' => $cell['used'] === $limit]) title="{{ __('Work in progress against the limit') }}">{{ $cell['used'] }}/{{ $limit }}</span>
        @endif
    </div>

    @php
        $openTasks = $cell['tasks']->reject(fn ($task): bool => $task->isDone());
        $doneTasks = $cell['tasks']->filter(fn ($task): bool => $task->isDone());
    @endphp
    @foreach($openTasks as $task)
        <div x-show="overCell === '{{ $key }}' && dropBefore === {{ $task->id }}" x-cloak class="h-0.5 shrink-0 rounded-full bg-ink"></div>
        @include('livewire.plan-week.partials.card', ['task' => $task, 'editable' => $editable, 'term' => $term, 'carried' => $carried, 'today' => $today])
    @endforeach
    <div x-show="overCell === '{{ $key }}' && dropBefore === null && dragging && dragging.kind === 'tasks'" x-cloak class="h-0.5 shrink-0 rounded-full bg-ink"></div>

    @if($doneTasks->isNotEmpty())
        {{-- Finished work stays on its day, folded so the open work reads first. --}}
        <div x-data="{ showDone: false }" class="space-y-1">
            <button type="button" data-no-box class="flex w-full items-center gap-1 rounded px-1 py-0.5 text-[11px] text-ink-3 hover:bg-sunken hover:text-ink" x-on:click="showDone = ! showDone" x-bind:aria-expanded="showDone">
                <x-lucide-check class="h-3 w-3 text-ok" aria-hidden="true" />
                {{ trans_choice('{1} 1 done|[2,*] :count done', $doneTasks->count(), ['count' => $doneTasks->count()]) }}
                <x-lucide-chevron-down class="ml-auto h-3 w-3 transition-transform" x-bind:class="showDone && 'rotate-180'" aria-hidden="true" />
            </button>
            <div x-show="showDone" x-cloak class="space-y-1">
                @foreach($doneTasks as $task)
                    @include('livewire.plan-week.partials.card', ['task' => $task, 'editable' => $editable, 'term' => $term, 'carried' => $carried, 'today' => $today])
                @endforeach
            </div>
        </div>
    @endif

    @foreach($cell['deliverables'] as $deliverable)
        @include('livewire.plan-week.partials.deliverable-card', ['deliverable' => $deliverable, 'draggable' => $this->canEdit, 'inBacklog' => false, 'term' => $term, 'cellKey' => $key, 'person' => $person, 'column' => $column])
    @endforeach

    @if($editable)
        <div data-quick="{{ $key }}" x-show="quick && quick.cell === '{{ $key }}'" x-cloak data-no-box
             class="space-y-1 rounded-control border border-ink/40 bg-surface p-1.5"
             x-on:click.outside="if (quick && Date.now() - quick.opened > 250) { closeQuick() }"
             x-on:keydown.escape.stop="closeQuick()">
            <template x-if="quick && quick.cell === '{{ $key }}' && ! quick.deliverableId">
                <div class="space-y-0.5">
                    <input type="text" class="ui-input h-7 text-meta" placeholder="{{ __('WO, client or piece…') }}" x-model="quick.query"
                           x-on:input.debounce.250ms="searchDeliverables()"
                           x-on:keydown.down.prevent="moveActive(1)" x-on:keydown.up.prevent="moveActive(-1)"
                           x-on:keydown.enter.prevent="pickDeliverable(quick.results[quick.active])">
                    <template x-for="(result, index) in quick.results" x-bind:key="result.id">
                        <button type="button" class="block w-full rounded px-1.5 py-1 text-left"
                                x-bind:class="index === quick.active ? 'bg-sunken' : 'hover:bg-sunken'"
                                x-on:click="pickDeliverable(result)">
                            <span class="block truncate text-[11px] font-semibold text-ink" x-text="result.title"></span>
                            <span class="block truncate text-[11px] text-ink-2" x-text="result.piece"></span>
                        </button>
                    </template>
                    <p x-show="quick.query.length >= 2 && quick.results.length === 0" class="px-1 text-[11px] text-ink-3">{{ __('Type a WO number, client or piece.') }}</p>
                </div>
            </template>
            <template x-if="quick && quick.cell === '{{ $key }}' && quick.deliverableId">
                <div class="space-y-1">
                    <p class="flex items-center gap-1 text-[11px] text-ink-2">
                        <span class="min-w-0 flex-1 truncate" x-text="quick.deliverableLabel"></span>
                        <button type="button" class="shrink-0 rounded p-0.5 text-ink-3 hover:bg-sunken hover:text-ink" x-on:click="quick.deliverableId = null; quick.deliverableLabel = ''" aria-label="{{ __('Change') }}"><x-lucide-x class="h-3 w-3" /></button>
                    </p>
                    <input type="text" list="plan-week-templates" class="ui-input h-7 text-meta" placeholder="{{ __('Task, e.g. First Proposal') }}" x-model="quick.title" x-on:keydown.enter.prevent="submitQuick()">
                    <p class="text-[10px] text-ink-3">{{ __('Enter adds it · Esc closes') }}</p>
                </div>
            </template>
        </div>
        <button type="button" x-show="! (quick && quick.cell === '{{ $key }}')" data-no-box
                class="mt-auto flex items-center gap-1 rounded px-1 py-0.5 text-[11px] text-ink-3 opacity-0 hover:bg-sunken hover:text-ink focus:opacity-100 group-hover/cell:opacity-100"
                x-on:click="openQuick('{{ $key }}', {{ $person->id }}, '{{ $column }}')">
            <x-lucide-plus class="h-3 w-3" aria-hidden="true" /> {{ __('Add task') }}
        </button>
    @endif
</div>
