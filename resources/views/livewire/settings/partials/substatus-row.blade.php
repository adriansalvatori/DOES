@php $isGlobalRow = $isGlobalRow ?? false; @endphp
<div
    wire:key="sub-{{ $sub->id }}"
    data-id="{{ $sub->id }}"
    draggable="true"
    @dragstart="
            $event.dataTransfer.setData('text/plain', '{{ $sub->id }}');
            $event.dataTransfer.effectAllowed = 'move';
            draggingId = {{ $sub->id }};
        "
        @dragend="
            draggingId = null;
            dragOverId = null;
        "
        @dragover.prevent="
            $event.dataTransfer.dropEffect = 'move';
            dragOverId = {{ $sub->id }};
        "
        @dragleave="
            if (dragOverId === {{ $sub->id }}) dragOverId = null;
        "
        @drop.prevent="
            let srcId = parseInt($event.dataTransfer.getData('text/plain'), 10);
            let targetId = {{ $sub->id }};
            if (srcId && srcId !== targetId) {
                let container = $el.closest('[data-reorder-group]');
                let rows = Array.from(container.querySelectorAll('[data-id]'));
                let ids = rows.map(r => parseInt(r.getAttribute('data-id'), 10));
                let fromIndex = ids.indexOf(srcId);
                let toIndex = ids.indexOf(targetId);
                if (fromIndex !== -1 && toIndex !== -1) {
                    ids.splice(fromIndex, 1);
                    ids.splice(toIndex, 0, srcId);
                    $wire.reorderSubstatuses(ids);
                }
            }
            draggingId = null;
            dragOverId = null;
        "
        :class="{
            'opacity-40 bg-sunken': draggingId === {{ $sub->id }},
            'border-t-2 border-ink bg-sunken': dragOverId === {{ $sub->id }} && draggingId !== {{ $sub->id }}
        }"
class="px-4 py-3 flex items-center justify-between hover:bg-sunken transition-colors gap-4">

    <div class="flex items-center gap-3 min-w-0 flex-1">
        <div class="flex items-center gap-1 shrink-0 text-ink-3">
            <span class="cursor-grab active:cursor-grabbing p-1 rounded-control hover:bg-sunken hover:text-ink transition" title="{{ __('Drag to reorder') }}">
                <x-lucide-grip-vertical class="w-4 h-4" />
            </span>
            <div class="flex flex-col -space-y-1">
                <button type="button" wire:click="moveUp({{ $sub->id }})" @disabled($loop->first)
                    class="p-0.5 rounded hover:bg-sunken hover:text-ink disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                    title="{{ __('Move up') }}">
                    <x-lucide-chevron-up class="w-3.5 h-3.5" />
                </button>
                <button type="button" wire:click="moveDown({{ $sub->id }})" @disabled($loop->last)
                    class="p-0.5 rounded hover:bg-sunken hover:text-ink disabled:opacity-20 disabled:hover:bg-transparent cursor-pointer disabled:cursor-not-allowed transition"
                    title="{{ __('Move down') }}">
                    <x-lucide-chevron-down class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <span class="px-2.5 py-0.5 rounded-control text-body font-medium border shrink-0"
            style="background-color: {{ $sub->bg_color }}; color: {{ $sub->text_color }}; border-color: {{ $sub->border_color }};">
            {{ __($sub->name) }}
        </span>

        @if($isGlobalRow)
            <x-ui.tag tone="neutral" icon="globe">{{ __('Global cross-stage') }}</x-ui.tag>
        @elseif($sub->is_default)
            <x-ui.tag tone="neutral" icon="check">{{ __('Default') }}</x-ui.tag>
        @endif

        @if($sub->is_system)
            <x-ui.tag tone="neutral">{{ __('System') }}</x-ui.tag>
        @endif
    </div>

    <div class="hidden sm:flex items-center shrink-0 text-meta text-ink-3">
        {{ $sub->style_type === 'solid' ? __('Solid Color') : __('Light Background') }}
    </div>

    <div class="flex items-center gap-1 shrink-0">
        <flux:button wire:click="openEditModal({{ $sub->id }})" variant="subtle" size="sm" icon="pencil">{{ __('Edit') }}</flux:button>

        @if(!$sub->is_system)
            <button
                wire:click="delete({{ $sub->id }})"
                wire:confirm="{{ __('Are you sure you want to delete the substatus ":name"?', ['name' => $sub->name]) }}"
                class="p-1.5 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer"
                title="{{ __('Delete Substatus') }}">
                <x-lucide-trash-2 class="w-4 h-4" />
            </button>
        @endif
    </div>
</div>
