{{--
    A task card: tick to finish, click to open its deliverable, ⌘/Shift-click to select, drag to move.
    Done tasks stay on their day, dimmed (never struck through). Color shows health only.
--}}
@php
    $deliverable = $task->deliverable;
    $workOrder = $deliverable->workOrder;
    $done = $task->isDone();
    $client = \App\Support\TitleCase::from($workOrder?->displayName() ?: $deliverable->company_name);
    $location = \App\Support\TitleCase::from($workOrder?->clientLocation?->name);
    $health = \App\Application\Delivery\Data\PromiseHealth::for($workOrder?->promised_date, ! $done && ! $deliverable->status?->isClosed(), now());
    $isCarried = in_array($task->id, $carried, true);
    $isLate = ! $done && ! $isCarried && $task->scheduled_date !== null && $task->scheduled_date->toDateString() < $today;
    $haystack = mb_strtolower(implode(' ', [$workOrder?->number, $client, $deliverable->task_name, $task->title]));
    $dim = $term !== '' && ! str_contains($haystack, $term);
@endphp
<div wire:key="task-{{ $task->id }}-{{ $done ? 'd' : 'o' }}" data-task-id="{{ $task->id }}"
     @if($editable) data-editable draggable="true" x-on:dragstart="startTasks($event, {{ $task->id }})" @endif
     x-on:click="cardClick($event, {{ $task->id }}, {{ $deliverable->id }})"
     x-on:keydown.enter="cardClick($event, {{ $task->id }}, {{ $deliverable->id }})"
     x-bind:class="isSelected({{ $task->id }}) && 'ring-2 ring-ink border-ink!'"
     role="button" tabindex="0"
     @class([
         'group/card relative rounded-control border px-1.5 py-1 text-left transition-shadow',
         'border-line bg-surface hover:border-line-strong hover:shadow-xs' => ! $done,
         'border-line bg-canvas' => $done,
         'border-l-2 border-l-danger' => $isLate || $isCarried,
         'cursor-grab active:cursor-grabbing' => $editable,
         'cursor-pointer' => ! $editable,
         'opacity-35' => $dim,
     ])>
    <div class="flex items-start gap-1.5">
        @if($editable)
            <input type="checkbox" class="mt-0.5 shrink-0 cursor-pointer rounded border-line-strong" aria-label="{{ __('Done') }}" @checked($done)
                   x-on:click.stop x-on:change="$wire.toggleTask({{ $task->id }})">
        @elseif($done)
            <x-lucide-check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ok" aria-label="{{ __('Done') }}" />
        @endif
        <div class="min-w-0 flex-1">
            <p @class(['truncate text-meta font-semibold leading-4', 'text-ink' => ! $done, 'ui-done' => $done]) title="{{ $task->title }}">{{ $task->title }}</p>
            <div @class(['mt-0.5 flex min-w-0 items-center gap-1', 'opacity-60' => $done])>
                <x-wo-badge :number="$workOrder?->number ?: $deliverable->wo_number" class="px-1! py-0! text-[10px]!" />
                <span class="truncate text-[11px] font-medium text-ink-2" title="{{ $client }}">{{ $client }}</span>
            </div>
            @if(! $done)
                <x-orders.location :location="$location" class="max-w-full truncate text-[11px]!" />
                <p class="truncate text-[11px] text-ink-3" title="{{ $deliverable->task_name }}">{{ $deliverable->task_name }}</p>
                @php
                    $hasTags = $task->points || $deliverable->priority?->rank() !== 3 || $deliverable->flag_codes || $health === \App\Application\Delivery\Data\PromiseHealth::OVERDUE || $health === \App\Application\Delivery\Data\PromiseHealth::DUE_SOON || $deliverable->openBlock || $deliverable->openHold || $isCarried;
                @endphp
                @if($hasTags)
                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                        @if($task->points)
                            <span class="rounded bg-sunken px-1 text-[10px] font-semibold tabular-nums text-ink-2" title="{{ __('Story points') }}">{{ $task->points }}</span>
                        @endif
                        <x-board.priority :priority="$deliverable->priority" class="[&_svg]:h-3.5 [&_svg]:w-3.5" />
                        @if($deliverable->hasFlag(\App\Domain\Delivery\Enums\DeliverableFlag::URGENT))
                            <x-ui.tag tone="danger">{{ __('Urgent') }}</x-ui.tag>
                        @endif
                        @if($health === \App\Application\Delivery\Data\PromiseHealth::OVERDUE || $health === \App\Application\Delivery\Data\PromiseHealth::DUE_SOON)
                            <x-ui.tag :tone="$health === \App\Application\Delivery\Data\PromiseHealth::OVERDUE ? 'danger' : 'warn'" title="{{ __('Promised date') }}">{{ $workOrder->promised_date->translatedFormat('j M') }}</x-ui.tag>
                        @endif
                        @if($deliverable->openBlock)
                            <x-ui.tag tone="warn">{{ __('Blocked') }}</x-ui.tag>
                        @endif
                        @if($deliverable->openHold)
                            <x-ui.tag tone="info">{{ __('On hold') }}</x-ui.tag>
                        @endif
                        @if($isCarried)
                            <x-ui.tag title="{{ __('Not finished on :date', ['date' => $task->scheduled_date->translatedFormat('j M')]) }}">{{ __('Carry-over') }}</x-ui.tag>
                        @endif
                    </div>
                @endif
            @endif
        </div>
        @if($editable)
            <button type="button" class="absolute right-0.5 top-0.5 hidden rounded p-0.5 text-ink-3 hover:bg-sunken hover:text-ink group-hover/card:block"
                    x-on:click.stop="$wire.unscheduleTask({{ $task->id }})" aria-label="{{ __('Remove from the plan') }}" title="{{ __('Remove from the plan') }}">
                <x-lucide-x class="h-3 w-3" />
            </button>
        @endif
    </div>
</div>
