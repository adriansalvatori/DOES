@php
    $tasks = $this->tasks;
    $next = $deliverable->status->next();
@endphp

@if($tasks->isEmpty())
    <p class="text-body text-ink-3">{{ __('No tasks yet.') }}</p>
@else
    <ul class="divide-y divide-line rounded-card border border-line" data-task-list>
        @foreach($tasks as $task)
            @php($done = $task->isDone())
            <li wire:key="task-{{ $task->id }}" class="flex items-center gap-3 px-3 py-2 {{ $done ? 'ui-done' : '' }}">
                <button type="button" wire:click="toggleTask({{ $task->id }})" @disabled(! $can['manageTasks']) role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? __('Reopen task') : __('Complete task') }}" @class([
                    'grid h-4.5 w-4.5 shrink-0 place-items-center rounded border',
                    'border-ink bg-ink text-white' => $done,
                    'border-line-strong bg-surface hover:border-ink' => ! $done,
                ])>
                    @if($done)<x-lucide-check class="h-3 w-3" stroke-width="3" />@endif
                </button>
                <span class="min-w-0 flex-1 truncate text-body text-ink">{{ $task->title_key ? __($task->title_key, $task->title_params ?? []) : $task->title }}</span>
                @if($task->isGate() && $task->advances_to)
                    <span class="shrink-0 text-meta text-ink-3">{{ __('→ :status', ['status' => $task->advances_to->label()]) }}</span>
                @endif
                @if($task->due_date)
                    <span class="shrink-0 text-meta text-ink-3">{{ $task->due_date->translatedFormat('j M') }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@if($can['manageTasks'])
    <form wire:submit="addTask" class="mt-3 space-y-2">
        <div class="flex items-center gap-2">
            <input type="text" wire:model="newTaskTitle" placeholder="{{ __('Add a task…') }}" aria-label="{{ __('Task title') }}" class="ui-input">
            <button type="submit" class="ui-btn ui-btn-secondary shrink-0">{{ __('Add task') }}</button>
        </div>
        @error('newTaskTitle') <span class="block text-meta text-danger">{{ $message }}</span> @enderror
        @if($next !== null && ! $isClosed)
            <label class="flex items-center gap-2 text-meta text-ink-2">
                <input type="checkbox" wire:model="newTaskIsGate" class="rounded border-line">
                <span>{{ __('Required before moving to :status', ['status' => $next->label()]) }}</span>
            </label>
        @endif
    </form>
@endif
