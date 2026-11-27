@php
    $tasks = $this->tasks;
    $next = $deliverable->status->next();
@endphp

@if($tasks->isEmpty())
    <p class="text-body text-ink-3">{{ __('No tasks yet.') }}</p>
@else
    <div x-data="{ picking: false, picked: [] }">
    @if($can['manageTasks'] && $tasks->count() >= 2)
        <div class="mb-2 flex items-center justify-end gap-2">
            <button type="button" x-show="! picking" @click="picking = true; picked = []" class="ui-btn ui-btn-ghost ui-btn-sm" data-merge-tasks>
                <x-lucide-merge class="h-3.5 w-3.5" aria-hidden="true" /> {{ __('Merge tasks') }}
            </button>
            <template x-if="picking">
                <div class="flex items-center gap-2">
                    <span class="text-meta text-ink-3">{{ __('Pick two or more tasks') }}</span>
                    <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" @click="picking = false; picked = []">{{ __('Cancel') }}</button>
                    <button type="button" class="ui-btn ui-btn-primary ui-btn-sm" :disabled="picked.length < 2" @click="$dispatch('open-merge', { kind: 'task', ids: picked }); picking = false; picked = []">{{ __('Merge selected') }}</button>
                </div>
            </template>
        </div>
    @endif
    <ul class="divide-y divide-line rounded-card border border-line" data-task-list>
        @foreach($tasks as $task)
            @php
                $done = $task->isDone();
                $taskTitle = $task->title_key ? __($task->title_key, $task->title_params ?? []) : $task->title;
            @endphp
            <li wire:key="task-{{ $task->id }}" class="flex items-center gap-3 px-3 py-2 {{ $done ? 'ui-done' : '' }}">
                <input type="checkbox" x-show="picking" x-model.number="picked" value="{{ $task->id }}" class="rounded border-line-strong" aria-label="{{ __('Pick for merge') }}">
                <button type="button" wire:click="toggleTask({{ $task->id }})" @disabled(! $can['manageTasks']) role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? __('Reopen task') : __('Complete task') }}" @class([
                    'grid h-4.5 w-4.5 shrink-0 place-items-center rounded border',
                    'border-ink bg-ink text-white' => $done,
                    'border-line-strong bg-surface hover:border-ink' => ! $done,
                ])>
                    @if($done)<x-lucide-check class="h-3 w-3" stroke-width="3" />@endif
                </button>
                <span class="min-w-0 flex-1 truncate text-body text-ink">{{ $taskTitle }}</span>
                @if($task->isGate() && $task->advances_to)
                    <span class="shrink-0 text-meta text-ink-3">{{ __('→ :status', ['status' => $task->advances_to->label()]) }}</span>
                @endif
                @if($task->due_date)
                    <span class="shrink-0 text-meta text-ink-3">{{ $task->due_date->translatedFormat('j M') }}</span>
                @endif
                @if($can['manageTasks'] && ! $isClosed)
                    <select class="ui-select w-16 shrink-0 tabular-nums" aria-label="{{ __('Points') }}" data-task-points="{{ $task->id }}" data-current="{{ $task->points }}"
                        @change="confirmTaskPoints($event.target, {{ $task->id }}, @js(__('Points').' · '.$taskTitle), @js($task->points !== null ? (string) $task->points : __('(empty)')))">
                        <option value="" @selected($task->points === null)>{{ __('(empty)') }}</option>
                        @foreach(\App\Domain\Delivery\Support\PointsEstimator::TASK_SCALE as $option)
                            <option value="{{ $option }}" @selected($task->points === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @else
                    <x-board.points :points="$task->points" show-zero />
                @endif
            </li>
        @endforeach
    </ul>
    </div>
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
