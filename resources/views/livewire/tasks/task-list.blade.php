<div class="h-full flex flex-col gap-4 min-h-0">
    <x-ui.page-header :title="__('Tareas vinculadas')" :count="$tasks->count()">
        <x-slot:toolbar>
            <div class="relative w-64">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar tarea o empresa...') }}" class="ui-input pl-8">
            </div>
            <select wire:model.live="statusFilter" class="ui-select w-44" aria-label="{{ __('Estado') }}">
                <option value="todo">{{ __('Pendientes') }}</option>
                <option value="done">{{ __('Completadas') }}</option>
                <option value="all">{{ __('Todas las tareas') }}</option>
            </select>
            <select wire:model.live="typeFilter" class="ui-select w-44" aria-label="{{ __('Tipo') }}">
                <option value="all">{{ __('Todos los tipos') }}</option>
                @foreach($taskTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </x-slot:toolbar>
    </x-ui.page-header>

    @if (session()->has('message'))
        <div class="flex shrink-0 items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <x-lucide-check-circle-2 class="h-4 w-4 shrink-0 text-ok" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="ui-card flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="min-h-0 flex-1 overflow-auto custom-vertical-scrollbar">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="w-10"><span class="sr-only">{{ __('Estado') }}</span></th>
                        <th>{{ __('Tarea') }}</th>
                        <th>{{ __('Orden') }}</th>
                        <th>{{ __('Asignado') }}</th>
                        <th>{{ __('Fecha límite') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php $done = $task->isDone(); @endphp
                        <tr wire:key="task-{{ $task->id }}" class="cursor-pointer" wire:click="$dispatch('open-order-detail', { orderId: {{ $task->order_id }} })">
                            <td x-on:click.stop="">
                                <button type="button" wire:click="toggleTaskStatus({{ $task->id }})" aria-pressed="{{ $done ? 'true' : 'false' }}"
                                        title="{{ $done ? __('Reabrir tarea') : __('Marcar como completada') }}" aria-label="{{ $done ? __('Reabrir tarea') : __('Marcar como completada') }}"
                                        class="flex h-5 w-5 items-center justify-center rounded-full border {{ $done ? 'border-ink bg-ink text-white' : 'border-line-strong text-transparent hover:text-ink-3' }}">
                                    <x-lucide-check class="h-3 w-3" />
                                </button>
                            </td>
                            <td class="font-medium {{ $done ? 'ui-done' : '' }}">{{ __($task->title) }}</td>
                            <td class="text-ink-2">{{ $task->order?->company_name }}</td>
                            <td>
                                @if($task->assignee)
                                    <x-ui.person :person="$task->assignee" size="sm" show-name />
                                @else
                                    <x-ui.person :person="null" size="sm" show-name />
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                @if($task->due_date)
                                    <x-orders.due :date="$task->due_date" :done="$done" />
                                @else
                                    <span class="text-ink-3">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-0"><x-ui.empty icon="check-square" :title="__('No se encontraron tareas vinculadas con los filtros seleccionados.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
