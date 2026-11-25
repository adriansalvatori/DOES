@php
    $isBlocked = $order->isBlocked() || $order->core_status === \App\Enums\CoreStatus::ENTRANTE;
    $isUrgent = $order->isUrgente();
    $isDone = (bool) $order->done_today;
    $responsible = $order->display_responsible_person;
    $substatusLabel = ($order->substatus && $order->substatus->value !== 'URGENTE')
        ? \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower($order->substatus->label()))
        : null;
    $elementCount = $order->elements->count();
    $readyCount = $elementCount > 0 ? $order->elements->filter(fn ($element) => $element->isReadyForProduction())->count() : 0;
    $activeStage = $elementCount > 0 ? $order->getActiveStage() : null;
    $taskCount = $order->relatedTasks->count();
    $tasksDone = $order->relatedTasks->where('status', 'done')->count();
    $tasksPending = $order->relatedTasks->where('status', 'todo')->count();
@endphp

<div
    wire:key="order-card-{{ $order->id }}"
    x-data="{ showTasks: false }"
    @click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
    @keydown.enter.self="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
    tabindex="0"
    draggable="true"
    @dragstart="event.dataTransfer.setData('text/plain', '{{ $order->id }}')"
    class="kanban-card group relative bg-surface border rounded-card p-3 flex flex-col gap-1.5 cursor-pointer select-none transition hover:border-line-strong hover:shadow-menu active:cursor-grabbing focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold {{ $order->is_missing_from_trello ? 'border-dashed border-line-strong opacity-75' : 'border-line' }}"
>
    <!-- Identity: done check, WO, people, menu -->
    <div class="flex items-center gap-1.5 min-w-0">
        <button
            wire:click="toggleDoneToday({{ $order->id }})"
            @click.stop
            type="button"
            class="w-4 h-4 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $isDone ? 'bg-ok border-ok text-white' : 'border-line-strong hover:border-ok bg-surface text-transparent hover:text-ok/50 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100' }}"
            title="{{ $isDone ? __('Completed (Click to uncheck)') : __('Mark as completed') }}"
            aria-label="{{ $isDone ? __('Completed (Click to uncheck)') : __('Mark as completed') }}"
            aria-pressed="{{ $isDone ? 'true' : 'false' }}">
            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
        </button>

        @if($order->wo_number)
            <x-wo-badge :number="$order->wo_number" />
        @endif

        <div class="ml-auto flex items-center gap-1 shrink-0">
            <div class="flex items-center -space-x-1">
                @forelse($order->assigned_designers as $designer)
                    <x-ui.person :person="$designer" size="sm" class="ring-2 ring-surface rounded-full" />
                @empty
                    <x-ui.person size="sm" />
                @endforelse
            </div>

            <div @click.stop class="opacity-60 group-hover:opacity-100 focus-within:opacity-100 transition">
                <button
                    type="button"
                    class="size-6 rounded-control inline-flex items-center justify-center text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer focus-visible:outline-2 focus-visible:outline-gold"
                    title="{{ __('Actions') }}"
                    aria-label="{{ __('Actions') }}"
                    aria-haspopup="menu"
                    @click.stop="$dispatch('kanban-card-menu', { orderId: {{ $order->id }}, status: {{ \Illuminate\Support\Js::from($order->core_status?->value) }}, trelloUrl: {{ \Illuminate\Support\Js::from($order->trello_url) }}, anchor: $el })">
                    <x-lucide-ellipsis class="w-4 h-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>

    <!-- Client, location, job -->
    <div class="min-w-0">
        <div class="flex items-center gap-2 min-w-0">
            <h4 class="text-row font-medium text-ink truncate min-w-0 {{ $isDone ? 'ui-done' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</h4>
            <x-orders.location :location="$order->location_text" class="shrink-0 max-w-[45%]" />
        </div>
        <p class="text-body truncate {{ $isDone ? 'ui-done' : 'text-ink-2' }}" title="{{ $order->task_name }}">{{ $order->task_name }}</p>
        @if($substatusLabel || $responsible !== 'MAIN')
            <p class="text-meta text-ink-3 truncate mt-0.5">
                {{ $substatusLabel }}@if($substatusLabel && $responsible !== 'MAIN') · @endif
                @if($responsible !== 'MAIN')<span title="{{ __('Account Rep') }}">{{ $responsible }}</span>@endif
            </p>
        @endif
    </div>

    <!-- Health signals: the only colored elements on the card -->
    @if($isUrgent || $order->customer_service_required || ($order->approved && $order->substatus !== \App\Enums\Substatus::PONER_EN_ALTA) || $order->is_missing_from_trello)
        <div class="flex flex-wrap items-center gap-1">
            @if($isUrgent)
                <x-ui.tag tone="danger" icon="zap">{{ __('Urgent') }}</x-ui.tag>
            @endif
            @if($order->customer_service_required)
                <x-ui.tag tone="warn">{{ __('Customer service') }}</x-ui.tag>
            @endif
            @if($order->approved && $order->substatus !== \App\Enums\Substatus::PONER_EN_ALTA)
                <x-ui.tag tone="ok" icon="check-circle-2">{{ __('Approved') }}</x-ui.tag>
            @endif
            @if($order->is_missing_from_trello)
                <x-ui.tag icon="alert-triangle">{{ __('Missing in Trello') }}</x-ui.tag>
            @endif
        </div>
    @endif

    <!-- Due, progress, next step -->
    <div class="flex items-center gap-1.5 pt-1.5 mt-0.5 border-t border-line min-w-0">
        @if($order->core_status === \App\Enums\CoreStatus::ENVIADO_AL_CLIENTE)
            @php
                $sentDate = $order->last_sent_to_client_at ?? $order->last_meaningful_update ?? $order->updated_at ?? now();
                $daysElapsed = $sentDate ? $sentDate->diffInWeekdays(now()) : 0;
                $daysToHold = max(0, 9 - $daysElapsed);
            @endphp
            <x-ui.tag :tone="$daysToHold <= 2 ? 'warn' : 'info'" icon="send" title="{{ __('Sent on :date. Moves to On Hold in :days business days.', ['date' => $sentDate->format('d/m/Y'), 'days' => $daysToHold]) }}">
                {{ __('With client :days d', ['days' => $daysElapsed]) }}
            </x-ui.tag>
        @else
            <x-orders.due :date="$order->current_due_date" :done="$isDone" />
        @endif

        <div class="ml-auto flex items-center gap-2 shrink-0 text-meta text-ink-3 tabular-nums">
            @if($elementCount > 0)
                <span class="inline-flex items-center gap-1 {{ $readyCount === $elementCount ? 'text-ok' : '' }}" title="{{ __('Pieces ready') }}: {{ $readyCount }}/{{ $elementCount }} {{ __('ready for production') }}{{ $activeStage ? ' • ' . $activeStage->name : '' }}">
                    <x-lucide-layers class="w-3.5 h-3.5" aria-hidden="true" />
                    {{ $readyCount }}/{{ $elementCount }}
                </span>
            @endif

            @if($taskCount > 0)
                <button
                    @click.stop="showTasks = !showTasks"
                    type="button"
                    class="inline-flex items-center gap-1 rounded px-1 -mx-1 hover:bg-sunken hover:text-ink transition cursor-pointer {{ $tasksPending > 0 ? 'text-ink-2' : '' }}"
                    :aria-expanded="showTasks.toString()"
                    title="{{ __('View the order\'s subtasks') }}">
                    <x-lucide-list-checks class="w-3.5 h-3.5" aria-hidden="true" />
                    {{ $tasksDone }}/{{ $taskCount }}
                    <x-lucide-chevron-down class="w-3 h-3 transition-transform duration-200" x-bind:class="{ 'rotate-180': showTasks }" aria-hidden="true" />
                </button>
            @endif

            @if($isBlocked)
                <button
                    wire:click="$parent.openUnblockModal({{ $order->id }})"
                    @click.stop
                    type="button"
                    class="inline-flex items-center gap-1 h-6 px-2 rounded-control border border-line-strong bg-surface text-meta font-medium text-ink hover:bg-sunken transition cursor-pointer"
                    title="{{ __('Unblock order') }}">
                    <x-lucide-lock-open class="w-3.5 h-3.5 text-warn" aria-hidden="true" />
                    {{ __('Unblock') }}
                </button>
            @endif
        </div>
    </div>

    <!-- Expandable subtasks -->
    @if($taskCount > 0)
        <div x-show="showTasks" x-collapse @click.stop class="pt-2 border-t border-line space-y-1 min-w-0" style="display: none;">
            <div class="text-meta text-ink-3 flex items-center justify-between">
                <span>{{ __('Subtasks and actions') }}</span>
                <span>{{ $tasksPending }} {{ __('pending') }}</span>
            </div>
            @foreach($order->relatedTasks as $task)
                <div class="flex items-center justify-between gap-1.5 px-1.5 py-1 rounded-control hover:bg-sunken text-body min-w-0">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <button
                            wire:click="$parent.toggleTaskComplete({{ $task->id }})"
                            @click.stop
                            type="button"
                            class="w-3.5 h-3.5 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $task->isDone() ? 'bg-ok border-ok text-white' : 'border-line-strong hover:border-ok bg-surface text-transparent' }}"
                            aria-label="{{ $task->display_title }}"
                            aria-pressed="{{ $task->isDone() ? 'true' : 'false' }}">
                            <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                        </button>
                        <span class="truncate {{ $task->isDone() ? 'ui-done' : 'text-ink' }}" title="{{ $task->display_title }}">{{ $task->display_title }}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="text-meta text-ink-3">{{ $task->type === \App\Enums\RelatedTaskType::SUBTASK ? __('Subtask') : __('Action') }}</span>
                        @if($task->type === \App\Enums\RelatedTaskType::SUBTASK)
                            <button
                                wire:click="$parent.deleteTask({{ $task->id }})"
                                wire:confirm="{{ __('Delete this subtask?') }}"
                                @click.stop
                                type="button"
                                class="p-0.5 rounded text-ink-3 hover:text-danger hover:bg-danger-soft transition"
                                title="{{ __('Delete subtask') }}"
                                aria-label="{{ __('Delete subtask') }}">
                                <x-lucide-x class="w-3 h-3" />
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
