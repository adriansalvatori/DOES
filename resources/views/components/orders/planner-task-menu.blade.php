{{--
    "⋯" actions for a planner card. Keyboard and click alternative to dragging:
    "Mover a…" calls the same server method as the drop handler (reorderSubtasks).
    Usage: <x-orders.planner-task-menu :task="$stask" :days="$weekDaysOnly" :next-week="$nextWeekItem" :designer-id="$designer->id" />
--}}
@props([
    'task',
    'days' => [],
    'nextWeek' => null,
    'designerId' => null,
])

@php
    $currentDate = $task->scheduled_date?->toDateString();
    $designerArg = $designerId ? (int) $designerId : 'null';
    $hasOrder = (bool) $task->order;
@endphp

<div @click.stop draggable="false" class="shrink-0 opacity-60 group-hover:opacity-100 focus-within:opacity-100 transition" data-planner-task-menu>
    <flux:dropdown position="bottom" align="end">
        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :tooltip="__('Acciones')" aria-label="{{ __('Acciones') }}" />
        <flux:menu>
            <flux:menu.submenu :heading="__('Mover a...')" icon="arrows-right-left">
                @foreach($days as $moveDay)
                    @if($moveDay['date_string'] !== $currentDate)
                        <flux:menu.item wire:click="reorderSubtasks([{{ $task->id }}], '{{ $moveDay['date_string'] }}', {{ $designerArg }})">{{ ucfirst($moveDay['date']->translatedFormat('l j M')) }}</flux:menu.item>
                    @endif
                @endforeach
                @if($nextWeek && ($nextWeek['date_string'] ?? null) !== $currentDate)
                    <flux:menu.item wire:click="reorderSubtasks([{{ $task->id }}], '{{ $nextWeek['date_string'] }}', {{ $designerArg }})">{{ __('Próxima Semana') }}</flux:menu.item>
                @endif
            </flux:menu.submenu>

            @if($hasOrder)
                <flux:menu.submenu :heading="__('Al completar enviar a...')" icon="tag">
                    @foreach(\App\Enums\SubtaskCategory::cases() as $categoryCase)
                        <flux:menu.item :icon="$task->category === $categoryCase ? 'check' : null" wire:click="updateTaskCategory({{ $task->id }}, '{{ $categoryCase->value }}')">{{ $categoryCase->label() }}</flux:menu.item>
                    @endforeach
                </flux:menu.submenu>
                <flux:menu.submenu :heading="__('Tipo de Subtarea')" icon="wrench">
                    <flux:menu.item :icon="$task->is_work_task !== false ? 'check' : null" wire:click="updateTaskType({{ $task->id }}, true)">{{ __('Trabajo') }}</flux:menu.item>
                    <flux:menu.item :icon="$task->is_work_task === false ? 'check' : null" wire:click="updateTaskType({{ $task->id }}, false)">{{ __('Gestión') }}</flux:menu.item>
                </flux:menu.submenu>
            @endif

            <flux:menu.separator />
            <flux:menu.item variant="danger" icon="trash" wire:click="deleteSubtask({{ $task->id }})">{{ __('Eliminar subtarea') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
