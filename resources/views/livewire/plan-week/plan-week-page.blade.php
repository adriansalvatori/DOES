<div
    class="h-full flex flex-col gap-4 min-h-0"
    x-data="{
        dragging: null,
        overCell: null,
        start(event, id) {
            this.dragging = id;
            event.dataTransfer.setData('text/plain', String(id));
            event.dataTransfer.effectAllowed = 'move';
        },
        end() {
            this.dragging = null;
            this.overCell = null;
        },
        dropOnCell(assigneeId, date) {
            const id = this.dragging;
            this.end();
            if (id) { $wire.planDeliverable(id, assigneeId, date); }
        },
        dropOnBacklog() {
            const id = this.dragging;
            this.end();
            if (id) { $wire.unplanDeliverable(id); }
        },
    }"
    @dragend.window="end()"
>
    <x-ui.page-header
        :title="__('Plan week')"
        :subtitle="$weekStartDate->translatedFormat('j M').' – '.$weekStartDate->addDays(4)->translatedFormat('j M')"
    >
        <x-slot:actions>
            <button type="button" wire:click="previousWeek" class="ui-btn ui-btn-secondary ui-btn-icon" aria-label="{{ __('Previous week') }}" title="{{ __('Previous week') }}">
                <x-lucide-chevron-left class="w-4 h-4" />
            </button>
            <button type="button" wire:click="thisWeek" class="ui-btn ui-btn-secondary" @disabled($isCurrentWeek)>{{ __('This week') }}</button>
            <button type="button" wire:click="nextWeek" class="ui-btn ui-btn-secondary ui-btn-icon" aria-label="{{ __('Next week') }}" title="{{ __('Next week') }}">
                <x-lucide-chevron-right class="w-4 h-4" />
            </button>
        </x-slot:actions>
        @if($this->canEdit)
            <x-slot:toolbar>
                <div class="relative w-64">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search WO, client or piece') }}" class="ui-input pl-8">
                </div>
                <select wire:model.live="designerFilter" class="ui-select w-auto" aria-label="{{ __('All designers') }}">
                    <option value="">{{ __('All designers') }}</option>
                    @foreach($this->designers as $designer)
                        <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                    @endforeach
                </select>
            </x-slot:toolbar>
        @endif
    </x-ui.page-header>

    @if($notice !== '')
        <div class="shrink-0 flex items-center justify-between gap-3 rounded-control border border-danger bg-danger-soft px-3 py-2 text-body text-danger" role="alert">
            <span>{{ $notice }}</span>
            <button type="button" wire:click="dismissNotice" class="ui-btn ui-btn-ghost ui-btn-icon" aria-label="{{ __('Dismiss') }}"><x-lucide-x class="w-4 h-4" /></button>
        </div>
    @endif

    @if($pendingOverride !== null)
        <div class="shrink-0 flex flex-wrap items-center justify-between gap-3 rounded-control border border-warn bg-warn-soft px-3 py-2 text-body text-ink" role="alertdialog">
            <span>{{ $pendingOverride['message'] }} {{ __('Plan it anyway?') }}</span>
            <span class="flex items-center gap-2">
                <button type="button" wire:click="cancelOverride" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Cancel') }}</button>
                <button type="button" wire:click="confirmOverride" class="ui-btn ui-btn-primary ui-btn-sm">{{ __('Plan anyway') }}</button>
            </span>
        </div>
    @endif

    <div class="flex-1 min-h-0 grid gap-4 {{ $this->canEdit ? 'lg:grid-cols-[18rem_1fr]' : '' }}">
        @if($this->canEdit)
            <aside
                class="ui-card min-h-0 overflow-y-auto p-2 space-y-1.5"
                :class="dragging ? 'ring-1 ring-line-strong' : ''"
                @dragover.prevent
                @drop.prevent="dropOnBacklog()"
                aria-label="{{ __('Ready backlog') }}"
            >
                <p class="ui-section-title px-1">{{ __('Ready') }} · {{ $this->backlog->count() }} · {{ __('by promised date') }}</p>
                @forelse($this->backlog as $deliverable)
                    @include('livewire.plan-week.partials.card', ['deliverable' => $deliverable, 'draggable' => true, 'showPromised' => true])
                @empty
                    <p class="px-1 py-6 text-center text-body text-ink-3">{{ __('Nothing ready to plan.') }}</p>
                @endforelse

                @if($this->blockedBacklog->isNotEmpty())
                    <p class="ui-section-title px-1 pt-3">{{ __('Blocked · can\'t be planned') }} · {{ $this->blockedBacklog->count() }}</p>
                    @foreach($this->blockedBacklog as $deliverable)
                        @include('livewire.plan-week.partials.card', ['deliverable' => $deliverable, 'draggable' => false, 'showPromised' => false, 'blockedLabel' => __('Blocked · can\'t be planned')])
                    @endforeach
                @endif
            </aside>
        @endif

        <section class="min-h-0 overflow-auto">
            <div class="grid min-w-[46rem] gap-1.5" style="grid-template-columns: 8rem repeat(5, minmax(0, 1fr));">
                <span></span>
                @foreach($this->plan->days as $day)
                    <span class="px-1 pb-1 text-meta font-semibold text-ink-2 {{ $day->isToday() ? 'text-ink' : '' }}">{{ $day->translatedFormat('D j') }}</span>
                @endforeach

                @foreach($this->rows as $row)
                    @php($assignee = $row['assignee'])
                    @include('livewire.plan-week.partials.row-head', ['row' => $row])
                    @foreach($this->plan->days as $day)
                        @include('livewire.plan-week.partials.cell', [
                            'assignee' => $assignee,
                            'date' => $day->toDateString(),
                            'cell' => $row['cells'][$day->toDateString()],
                            'limit' => $row['limit'],
                        ])
                    @endforeach
                @endforeach
            </div>
            @if(count($this->rows) === 0)
                <p class="py-10 text-center text-body text-ink-3">{{ __('No designers to plan.') }}</p>
            @endif
        </section>
    </div>
</div>
