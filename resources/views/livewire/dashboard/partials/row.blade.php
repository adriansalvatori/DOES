{{--
    One row pattern for every Hoy list.
    Columns: due/health (fixed width), WO + company + location over the task, stage meter, next-step action, person.
    Expected: $order, $rowKey. Optional: $tags (list of [tone, label, icon]), $line2, $person, $toggle ([wire, done, title]),
    $action ([label, wire]), $meta (quiet text), $showDue (bool), $extra (trello url).
--}}
@php
    $tags = $tags ?? [];
    $toggle = $toggle ?? null;
    $action = $action ?? null;
    $done = (bool) ($toggle['done'] ?? false);
    $person = $person ?? $order->primary_designer;
@endphp
<div wire:key="{{ $rowKey }}"
     role="button" tabindex="0"
     wire:click="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
     wire:keydown.enter="$dispatch('open-order-detail', { orderId: {{ $order->id }} })"
     class="flex items-center gap-4 px-4 py-2.5 cursor-pointer hover:bg-sunken focus-visible:bg-sunken outline-none min-w-0">
    @if($toggle)
        <button type="button" @click.stop wire:click="{{ $toggle['wire'] }}" title="{{ $toggle['title'] }}" aria-label="{{ $toggle['title'] }}"
                class="w-5 h-5 rounded-full border grid place-items-center shrink-0 cursor-pointer transition {{ $done ? 'bg-ok border-ok text-white' : 'border-line-strong bg-surface text-transparent hover:text-ink-3' }}">
            <x-lucide-check class="w-3 h-3" stroke-width="3" />
        </button>
    @endif
    <div class="hidden sm:flex flex-col items-start gap-1 w-32 shrink-0 min-w-0">
        @foreach($tags as $tag)
            <x-ui.tag :tone="$tag['tone']" :icon="$tag['icon'] ?? null" class="max-w-full"><span class="truncate">{{ $tag['label'] }}</span></x-ui.tag>
        @endforeach
        @if(! count($tags) && ! empty($showDue) && ! $done)
            <x-orders.due :date="$order->current_due_date" :done="false" />
        @endif
    </div>
    <div class="min-w-0 flex-1">
        @if(count($tags))
            <div class="flex sm:hidden flex-wrap gap-1 mb-1">
                @foreach($tags as $tag)
                    <x-ui.tag :tone="$tag['tone']" :icon="$tag['icon'] ?? null">{{ $tag['label'] }}</x-ui.tag>
                @endforeach
            </div>
        @endif
        <div class="flex items-center gap-2 min-w-0">
            <x-wo-badge :number="$order->wo_number" />
            <span class="text-row font-medium text-ink truncate {{ $done ? 'ui-done' : '' }}" title="{{ $order->company_name }}">{{ $order->company_name }}</span>
            <x-orders.location :location="$order->location_text" />
        </div>
        <p class="text-body text-ink-2 truncate mt-0.5 {{ $done ? 'ui-done' : '' }}" title="{{ $line2 ?? $order->task_name }}">{{ $line2 ?? $order->task_name }}</p>
    </div>
    @if(! empty($meta))
        <span class="hidden md:inline text-meta text-ink-3 whitespace-nowrap shrink-0">{{ $meta }}</span>
    @endif
    <div class="hidden lg:flex items-center w-36 shrink-0 min-w-0">
        @if($order->core_status)
            <x-orders.stage :status="$order->core_status" :show-label="false" />
            <span class="text-body text-ink-2 truncate ml-2" title="{{ $order->core_status->label() }}">{{ $order->core_status->phaseLabel() }}</span>
        @endif
    </div>
    <div class="flex items-center justify-end gap-3 shrink-0 w-32">
        @if(! empty($extra))
            <a href="{{ $extra }}" target="_blank" rel="noopener" @click.stop class="text-meta text-ink-3 hover:text-ink underline">Trello</a>
        @endif
        @if($action)
            <flux:button size="sm" variant="{{ $action['variant'] ?? 'filled' }}" x-on:click.stop="" wire:click="{{ $action['wire'] }}">{{ $action['label'] }}</flux:button>
        @endif
    </div>
    <x-ui.person :person="$person" size="md" class="shrink-0" />
</div>
