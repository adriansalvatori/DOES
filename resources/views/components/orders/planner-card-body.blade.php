{{--
    Contents of a planner card (used by the by-day and by-designer variants).
    WO tag, company + location, task text, assignee and health only; everything else lives in the "⋯" menu.
    Alpine scope comes from the planner (selectedTaskIds, toggleTaskSelection).
--}}
@props([
    'task',
    'designer' => null,
    'days' => [],
    'nextWeek' => null,
    'showMeta' => false,
])

@php
    $order = $task->order;
    $isNote = ! $order;
    $done = $task->isDone();
    $urgent = $task->isOrderUrgent() || $task->isUrgent();
    $blocked = $order?->isBlocked() ?? false;
    $strike = $done ? 'ui-done' : '';
@endphp

<div class="flex items-start gap-2 min-w-0 flex-1">
    <button
        @click.stop="$wire.toggleSubtaskComplete({{ $task->id }})"
        type="button"
        class="w-4 h-4 mt-0.5 rounded-control border transition flex items-center justify-center shrink-0 cursor-pointer {{ $done ? 'bg-ok border-ok text-white' : 'border-line-strong hover:border-ink bg-surface text-transparent' }}"
        title="{{ $done ? __('Subtask completed') : __('Mark subtask as completed') }}"
        aria-label="{{ $done ? __('Subtask completed') : __('Mark subtask as completed') }}">
        <x-lucide-check class="w-3 h-3 stroke-[3]" />
    </button>

    <button
        type="button"
        @click.stop="toggleTaskSelection({{ $task->id }})"
        :class="selectedTaskIds.includes({{ $task->id }}) ? 'opacity-100 text-white bg-ink border-ink' : 'opacity-0 group-hover:opacity-60 text-ink-3 border-line-strong hover:opacity-100 hover:border-ink'"
        class="w-4 h-4 mt-0.5 rounded-control border transition flex items-center justify-center shrink-0 cursor-pointer"
        title="{{ __('Select subtask to move in bulk') }}"
        aria-label="{{ __('Select subtask to move in bulk') }}">
        <x-lucide-check x-show="selectedTaskIds.includes({{ $task->id }})" class="w-3 h-3 stroke-[3]" />
    </button>

    <div class="min-w-0 flex-1 space-y-0.5">
        @if($order)
            <div class="flex items-center gap-1.5 min-w-0">
                <x-wo-badge :number="$order->wo_number" />
                <span class="text-body font-medium text-ink truncate min-w-0 {{ $strike }}" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
                <x-orders.location :location="$order->location_text" class="shrink-0 max-w-[45%]" />
            </div>
            <p class="text-body text-ink-2 truncate {{ $strike }}" title="{{ $task->title }}">{{ $task->title }}</p>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 min-w-0">
                @if($order->task_name)
                    <span class="text-meta text-ink-3 truncate max-w-full" title="{{ $order->task_name }}">{{ $order->task_name }}</span>
                @endif
                @if($showMeta)
                    <span class="text-meta text-ink-3">{{ $task->category->shortReturnLabel() }}@if($task->is_work_task === false) · {{ __('Admin') }}@endif</span>
                @endif
                @if($order->isArchived())
                    <x-ui.tag>{{ __('Archived') }}</x-ui.tag>
                @endif
                @if($urgent && ! $done)
                    <x-ui.tag tone="danger" icon="zap">{{ __('Urgent') }}</x-ui.tag>
                @endif
                @if($blocked && ! $done)
                    <x-ui.tag tone="warn" icon="octagon-alert">{{ __('Blocked') }}</x-ui.tag>
                @endif
                @if($order->current_due_date)
                    @php
                        $overSla = ! $done && ! $task->isFollowUp() && ! $order->isSlaExempt() && $task->scheduled_date && $task->scheduled_date->gt($order->current_due_date);
                    @endphp
                    @if($overSla && ! $order->isOverdue())
                        <x-ui.tag tone="danger" icon="triangle-alert">{{ __('SLA exceeded') }}</x-ui.tag>
                    @endif
                    <x-orders.due :date="$order->current_due_date" :done="$done" />
                @endif
            </div>
        @else
            <div class="flex items-center gap-1.5 min-w-0">
                <x-lucide-sticky-note class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                <span class="text-body font-medium text-ink truncate {{ $strike }}" title="{{ $task->title }}">{{ $task->title }}</span>
                @if($urgent && ! $done)
                    <x-ui.tag tone="danger" icon="zap">{{ __('Urgent') }}</x-ui.tag>
                @endif
            </div>
            <div class="pt-0.5">
                <flux:button size="xs" variant="subtle" icon="plus" @click.stop="$dispatch('open-link-note-modal', { taskId: {{ $task->id }}, noteTitle: {{ \Illuminate\Support\Js::from($task->title) }} })">{{ __('Add to order') }}</flux:button>
            </div>
        @endif
    </div>
</div>

<div class="flex items-center gap-1 shrink-0">
    @if($designer)
        <x-ui.person :person="$designer" size="sm" />
    @endif
    <x-orders.planner-task-menu :task="$task" :designer-id="$designer?->id" />
</div>
