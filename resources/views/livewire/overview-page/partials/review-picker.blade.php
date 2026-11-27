{{--
    Who reviewed the estimate (CS or Camila). Once reviewed, the estimate reads as an invoice (INV). One click saves.
    Variables: $reviewStatus (CS|CAMILA|null).
--}}
@php
    $reviewLabel = match ($reviewStatus) {
        'CS' => 'CS',
        'CAMILA' => 'Camila',
        default => null,
    };
@endphp
<div class="shrink-0" wire:key="review-{{ $reviewStatus ?? 'none' }}"
     x-data="{
        open: false,
        anchor: null,
        wire: null,
        init() { this.anchor = this.$refs.button; this.wire = $wire; },
        pick(status) { this.open = false; this.wire.saveLineField('review_status', status); this.anchor.focus(); },
     }">
    <button type="button" x-ref="button" x-on:click="open = ! open"
            @class(['inline-flex h-6 items-center gap-0.5 rounded px-1 text-meta font-semibold', 'bg-ok-soft text-ok' => $reviewLabel, 'text-ink-3 hover:bg-sunken hover:text-ink' => ! $reviewLabel])
            title="{{ $reviewLabel ? __('Reviewed by :name', ['name' => $reviewLabel]) : __('Not reviewed') }}">
        <x-lucide-check class="h-3.5 w-3.5" aria-hidden="true" />
        @if($reviewLabel)<span>{{ $reviewLabel }}</span>@endif
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-anchor.bottom-end.offset.4="anchor" x-on:click.outside="open = false" x-on:keydown.escape.prevent.stop="open = false; anchor.focus()"
             class="z-[60] w-44 rounded-card border border-line bg-surface p-1 shadow-menu" role="menu">
            <p class="px-2 py-1 text-meta text-ink-3">{{ __('Estimate reviewed by') }}</p>
            @foreach(['CS' => 'CS', 'CAMILA' => 'Camila', '' => __('Nobody')] as $value => $label)
                <button type="button" role="menuitem" x-on:click="pick(@js($value))"
                        @class(['flex w-full items-center justify-between rounded-control px-2 py-1 text-left text-meta hover:bg-sunken focus:bg-sunken focus:outline-none', 'font-semibold text-ink' => ($reviewStatus ?? '') === $value, 'text-ink-2' => ($reviewStatus ?? '') !== $value])>
                    {{ $label }}
                    @if(($reviewStatus ?? '') === $value)<x-lucide-check class="h-3.5 w-3.5" aria-hidden="true" />@endif
                </button>
            @endforeach
        </div>
    </template>
</div>
