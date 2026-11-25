{{--
    Searchable filter dropdown for list pages. Looks like a .ui-select, opens a searchable menu.
    Usage: <x-lists.combo-filter model="companyFilter" :value="$companyFilter" :all-label="__('All companies')" :options="['value' => 'Label']" :search-placeholder="__('Search…')" />
--}}
@props([
    'model',
    'value' => 'all',
    'allLabel',
    'options' => [],
    'searchPlaceholder' => null,
    'width' => 'w-48',
])

@php
    $current = (string) $value === 'all' ? $allLabel : ($options[$value] ?? $allLabel);
    $items = collect($options)->map(fn ($label, $val) => ['value' => (string) $val, 'label' => (string) $label])->values();
@endphp

<div class="relative {{ $width }}"
     x-data="{ open: false, q: '', items: @js($items), pick(val) { $wire.set(@js($model), val); this.open = false; this.q = ''; } }"
     x-dropdown-nav>
    <button type="button" @click="open = !open" @click.outside="open = false" aria-haspopup="listbox" :aria-expanded="open"
            class="ui-select flex items-center text-left">
        <span class="truncate">{{ $current }}</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms style="display: none;"
         class="absolute left-0 top-full z-50 mt-1 w-64 overflow-hidden rounded-card border border-line bg-surface shadow-menu">
        <div class="border-b border-line p-1.5">
            <input type="text" x-model="q" x-ref="q" x-effect="open && $nextTick(() => $refs.q.focus())" placeholder="{{ $searchPlaceholder ?? __('Buscar…') }}" class="ui-input">
        </div>
        <div class="max-h-60 overflow-y-auto p-1" role="listbox">
            <button type="button" @click="pick('all')" class="flex w-full items-center justify-between rounded-control px-2 py-1.5 text-left text-body text-ink hover:bg-sunken">
                <span>{{ $allLabel }}</span>
                @if((string) $value === 'all')<x-lucide-check class="h-4 w-4 text-ink-2" />@endif
            </button>
            <template x-for="item in items.filter(i => !q || i.label.toLowerCase().includes(q.toLowerCase()))" :key="item.value">
                <button type="button" @click="pick(item.value)" class="flex w-full items-center justify-between rounded-control px-2 py-1.5 text-left text-body text-ink hover:bg-sunken">
                    <span class="truncate" x-text="item.label"></span>
                    <template x-if="item.value === @js((string) $value)"><x-lucide-check class="h-4 w-4 shrink-0 text-ink-2" /></template>
                </button>
            </template>
        </div>
    </div>
</div>
