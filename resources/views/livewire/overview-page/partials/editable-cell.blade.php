{{--
    Inline cell of an Overview line. Click to edit, Enter or leaving the cell saves, Escape cancels.
    Variables: $field, $value (raw), $text (what to show), $kind (text|date|note), $method, $confirmLabel (old vs new before saving), $isWorkOrder.
--}}
@php
    $kind ??= 'text';
    $confirmLabel ??= null;
    $isWorkOrder ??= false;
    $placeholder ??= '—';
@endphp
<div class="relative min-w-0"
     x-data="{
        editing: false,
        original: @js((string) $value),
        value: @js((string) $value),
        saving: false,
        async commit() {
            if (this.saving) { return; }
            const next = String(this.value ?? '').trim();
            if (next === this.original) { this.editing = false; return; }
            this.saving = true;
            @if($confirmLabel)
            const ok = await KudosConfirmChange({ field: @js($confirmLabel), from: this.original || '—', to: next || '—' });
            if (! ok) { this.value = this.original; this.editing = false; this.saving = false; return; }
            @endif
            const result = await $wire.{{ $method }}(@js($field), next);
            if (result && result.ok) { this.original = this.value = result.value == null ? '' : String(result.value); } else { this.value = this.original; }
            this.editing = false;
            this.saving = false;
        },
     }">
    <button type="button" x-show="! editing" x-on:click="editing = true; $nextTick(() => $refs.input.focus())"
        class="block w-full min-w-0 rounded-control px-1 py-0.5 text-left text-body hover:bg-sunken {{ $kind === 'note' ? 'line-clamp-2 whitespace-normal' : 'truncate' }}"
        title="{{ $text ?: __('Click to edit') }}">
        @if($isWorkOrder && filled($text))
            <x-wo-badge :number="$text" />
        @elseif(filled($text))
            <span class="text-ink">{{ $text }}</span>
        @else
            <span class="text-ink-3">{{ $placeholder }}</span>
        @endif
    </button>
    @if($kind === 'note')
        <textarea x-ref="input" x-show="editing" x-cloak style="display: none;" x-model="value" rows="2" class="ui-input w-48"
            x-on:blur="commit()" x-on:keydown.escape.prevent="value = original; editing = false"
            x-on:keydown.enter.prevent="$event.target.blur()"></textarea>
    @else
        <input x-ref="input" x-show="editing" x-cloak style="display: none;" x-model="value" type="{{ $kind === 'date' ? 'date' : 'text' }}" class="ui-input w-full min-w-28"
            x-on:blur="commit()" x-on:keydown.escape.prevent="value = original; editing = false"
            x-on:keydown.enter.prevent="$event.target.blur()">
    @endif
</div>
