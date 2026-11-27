{{--
    Excel-style cell of an Overview line: always an input, so one click or Tab starts typing.
    Enter or leaving the cell saves, Escape puts the old value back. Record data ($confirmLabel: WO, estimate) shows
    old → new right under the cell first: Enter confirms, Escape cancels.
    Variables: $field, $value (raw), $kind (text|date|note), $method (saveWorkOrderField|saveLineField),
    $confirmLabel, $isWorkOrder, $deliverableId (edit one deliverable), $varies (deliverables disagree), $placeholder.
--}}
@php
    $kind ??= 'text';
    $confirmLabel ??= null;
    $isWorkOrder ??= false;
    $deliverableId ??= null;
    $varies ??= false;
    $placeholder ??= '';
    $placeholderClass ??= null;
    $value = (string) ($value ?? '');
    $arguments = $deliverableId !== null ? ', '.(int) $deliverableId : '';
@endphp
<div class="relative min-w-0" wire:key="cell-{{ $field }}-{{ $deliverableId ?? 'line' }}-{{ md5($value) }}"
     x-data="{
        original: @js($value),
        value: @js($value),
        focused: false,
        confirming: false,
        confirmOpenedAt: 0,
        saving: false,
        error: '',
        input: null,
        init() { this.input = this.$refs.input; },
        openPicker() { try { this.input.showPicker(); } catch (error) {} },
        commit() {
            if (this.saving || this.confirming) { return; }
            const next = String(this.value ?? '').trim();
            if (next === this.original) { this.value = this.original; return; }
            @if($confirmLabel)
            this.confirming = true;
            this.confirmOpenedAt = Date.now();
            this.$nextTick(() => this.$refs.confirm?.focus());
            @else
            this.save(next);
            @endif
        },
        async save(next) {
            this.confirming = false;
            this.saving = true;
            this.error = '';
            const result = await $wire.{{ $method }}(@js($field), next{{ $arguments }});
            if (result && result.ok) {
                this.original = this.value = result.value == null ? '' : String(result.value);
            } else {
                this.value = this.original;
                this.error = (result && result.message) || @js(__('Not saved.'));
                setTimeout(() => this.error = '', 4000);
            }
            this.saving = false;
        },
        cancel() {
            this.confirming = false;
            this.value = this.original;
            this.input?.focus();
        },
     }">
    <input x-ref="input" x-model="value" type="{{ $kind === 'date' ? 'date' : 'text' }}"
        @class(['ui-cell-input', 'pr-4' => $varies, 'text-transparent focus:text-ink' => $isWorkOrder, $placeholderClass])
        x-bind:class="{ 'ring-2 ring-danger/40 border-danger': error, 'opacity-60': saving{{ $kind === 'date' ? ", 'text-transparent': ! value && ! focused" : '' }} }"
        x-bind:title="error || value || @js($confirmLabel ?? '')"
        placeholder="{{ $placeholder }}"
        aria-label="{{ $confirmLabel ?? $field }}"
        x-on:focus="focused = true"
        x-on:blur="focused = false; commit()"
        @if($kind === 'date') x-on:click="openPicker()" @endif
        x-on:keydown.enter.prevent="commit(); if (! confirming) { $el.blur() }"
        x-on:keydown.escape.prevent="value = original; $el.blur()">

    @if($isWorkOrder)
        {{-- The WO is always the black tag; the input underneath takes over while it has focus. --}}
        <span x-show="! focused && value" class="pointer-events-none absolute inset-y-0 left-1 right-1 flex items-center">
            <span class="max-w-full truncate whitespace-nowrap rounded bg-wo px-1.5 py-0.5 text-xs font-bold leading-4 text-white tabular-nums" x-text="value"></span>
        </span>
        <span x-show="! focused && ! value" class="pointer-events-none absolute inset-y-0 left-1 flex items-center text-meta font-medium text-danger">+ {{ __('WO') }}</span>
    @endif

    @if($varies)
        <span class="pointer-events-auto absolute right-1 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-warn"
              title="{{ __('The deliverables have different values. Saving here applies to all of them; open the row to edit one.') }}"></span>
    @endif

    @if($confirmLabel)
        <template x-teleport="body">
            <div x-show="confirming" x-cloak x-anchor.bottom-start.offset.4="input" x-on:click.outside="if (Date.now() - confirmOpenedAt > 300) { cancel() }" x-on:keydown.escape.prevent.stop="cancel()"
                 class="z-[60] flex items-center gap-2 rounded-card border border-line bg-surface px-2.5 py-2 text-meta shadow-menu" role="dialog" aria-label="{{ __('Confirm change') }}">
                <span class="font-medium text-ink-2">{{ $confirmLabel }}</span>
                <span class="text-ink-3" x-text="original || '—'"></span>
                <x-lucide-arrow-right class="h-3.5 w-3.5 text-ink-3" aria-hidden="true" />
                <span class="font-semibold text-ink" x-text="String(value ?? '').trim() || '—'"></span>
                <button type="button" x-ref="confirm" class="ui-btn ui-btn-primary ui-btn-sm" x-on:click="save(String(value ?? '').trim())">{{ __('Save') }} <span class="ui-kbd border-white/30 text-white/80">↵</span></button>
                <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm" x-on:click="cancel()">{{ __('Cancel') }}</button>
            </div>
        </template>
    @endif
</div>
