{{--
    "⋯" trigger for a planner card. Keyboard and click alternative to dragging.
    The menu itself is rendered once by the planner (data-planner-shared-menu); this button only passes the card's context.
    Usage: <x-orders.planner-task-menu :task="$stask" :designer-id="$designer->id" />
--}}
@props([
    'task',
    'designerId' => null,
])

<div
    @click.stop
    draggable="false"
    data-planner-task-menu
    data-task-id="{{ $task->id }}"
    data-designer-id="{{ $designerId }}"
    data-date="{{ $task->scheduled_date?->toDateString() }}"
    data-has-order="{{ $task->order ? '1' : '0' }}"
    data-category="{{ $task->category?->value }}"
    data-work="{{ $task->is_work_task === false ? '0' : '1' }}"
    class="shrink-0 opacity-60 group-hover:opacity-100 focus-within:opacity-100 transition">
    <button type="button" @click.stop="openTaskMenu($event)" class="flex h-6 w-6 items-center justify-center rounded-control text-ink-3 hover:bg-sunken hover:text-ink cursor-pointer" title="{{ __('Actions') }}" aria-label="{{ __('Actions') }}" aria-haspopup="menu">
        <x-lucide-ellipsis class="w-4 h-4" />
    </button>
</div>
