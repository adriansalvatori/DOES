{{--
    Reason sheet: one modal for status changes that need a reason (on hold, resume, block, unblock, ...).
    Render it with @if / or pass :show; nothing is output when show is false.

    Props:
      show          bool    Whether the sheet is open (Livewire flag).
      title         string
      description   string|null
      confirm       string  Livewire method called by the confirm button (also: the sheet never submits on Enter in a textarea).
      cancel        string  Livewire method called by Cancelar, the X, Esc and a click on the backdrop.
      confirmLabel  string
      cancelLabel   string|null  defaults to "Cancelar".
      tone          neutral (ink button, default) | danger | ok  (button color follows health only).
      icon          string|null  lucide icon name shown next to the title (neutral color).
      presets       array   [value => label] of quick reasons rendered as buttons that set `presetModel`.
      presetModel   string  Livewire property set by the presets (also used to highlight the selected one).
      presetLabel   string|null  label above the presets.
      selected      mixed   current value of presetModel (to highlight it).
      reasonModel   string|null  wire:model of the reason field. Null hides the field.
      reasonType    textarea (default) | input
      reasonLabel / reasonPlaceholder / reasonRows
      reasonVisible bool    Hide the field conditionally (e.g. only for "Otro motivo"). Default true.
      errorKey      string|null  validation error key to display (defaults to reasonModel).
    Slot: extra content below the reason field (checkbox, comment textarea, ...).
--}}
@props([
    'show' => false,
    'title',
    'description' => null,
    'confirm',
    'cancel',
    'confirmLabel' => null,
    'cancelLabel' => null,
    'tone' => 'neutral',
    'icon' => null,
    'presets' => [],
    'presetModel' => null,
    'presetLabel' => null,
    'selected' => null,
    'reasonModel' => null,
    'reasonType' => 'textarea',
    'reasonLabel' => null,
    'reasonPlaceholder' => null,
    'reasonRows' => 3,
    'reasonVisible' => true,
    'errorKey' => null,
])

@php
    $confirmClasses = match ($tone) {
        'danger' => 'bg-danger text-white hover:opacity-90',
        'ok' => 'bg-ok text-white hover:opacity-90',
        default => 'bg-ink text-white hover:bg-ink-2',
    };
    $errorKey ??= $reasonModel;
    $fieldClasses = 'w-full bg-surface border border-line rounded-control px-2.5 py-1.5 text-body text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong focus:ring-2 focus:ring-gold/40';
@endphp

@if($show)
    <div class="fixed inset-0 z-[100] bg-ink/40 flex items-center justify-center p-4"
         x-data
         x-init="$nextTick(() => { const el = $refs.sheet.querySelector('[data-autofocus]') || $refs.sheet; el.focus(); })"
         @keydown.escape.window.prevent="$wire.{{ $cancel }}()"
         @click.self="$wire.{{ $cancel }}()">
        <div x-ref="sheet" tabindex="-1" role="dialog" aria-modal="true" aria-label="{{ $title }}"
             class="bg-surface border border-line rounded-panel shadow-panel w-full max-w-md p-5 space-y-4 focus:outline-none">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-section font-semibold text-ink flex items-center gap-2">
                        @if($icon)
                            {{ svg('lucide-' . $icon, 'w-4 h-4 text-ink-2 shrink-0', ['aria-hidden' => 'true']) }}
                        @endif
                        <span>{{ $title }}</span>
                    </h3>
                    @if(filled($description))
                        <p class="text-body text-ink-3 mt-0.5">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" wire:click="{{ $cancel }}" aria-label="{{ __('Close') }}"
                        class="p-1 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition shrink-0">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-3 text-body">
                @if(!empty($presets) && $presetModel)
                    <div class="space-y-1.5">
                        @if($presetLabel)
                            <label class="text-meta font-medium text-ink-2 block">{{ $presetLabel }}</label>
                        @endif
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($presets as $value => $label)
                                @php($value = is_int($value) ? $label : $value)
                                @php($isSelected = (string) $selected === (string) $value)
                                <button type="button"
                                        wire:click="$set('{{ $presetModel }}', '{{ addslashes($value) }}')"
                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                        class="px-2.5 py-1.5 rounded-control border text-meta font-medium transition flex items-center gap-1.5 {{ $isSelected ? 'bg-sunken border-ink text-ink' : 'bg-surface border-line text-ink-2 hover:bg-sunken' }}">
                                    <span>{{ $label }}</span>
                                    @if($isSelected)
                                        <x-lucide-check class="w-3.5 h-3.5 shrink-0" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($reasonModel && $reasonVisible)
                    <div class="space-y-1">
                        @if($reasonLabel)
                            <label class="text-meta font-medium text-ink-2 block">{{ $reasonLabel }}</label>
                        @endif
                        @if($reasonType === 'input')
                            <input type="text" data-autofocus wire:model="{{ $reasonModel }}" placeholder="{{ $reasonPlaceholder }}" class="{{ $fieldClasses }}">
                        @else
                            <textarea data-autofocus wire:model="{{ $reasonModel }}" rows="{{ $reasonRows }}" placeholder="{{ $reasonPlaceholder }}"
                                      @keydown.enter.stop
                                      class="{{ $fieldClasses }}"></textarea>
                        @endif
                        @if($errorKey)
                            @error($errorKey)
                                <span class="text-danger text-meta block">{{ $message }}</span>
                            @enderror
                        @endif
                    </div>
                @endif

                {{ $slot }}
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" wire:click="{{ $cancel }}"
                        class="px-3 h-8 rounded-control border border-line bg-surface text-ink-2 text-body font-medium hover:bg-sunken transition">
                    {{ $cancelLabel ?? __('Cancel') }}
                </button>
                <button type="button" wire:click="{{ $confirm }}" wire:loading.attr="disabled" wire:target="{{ $confirm }}"
                        class="px-3.5 h-8 rounded-control text-body font-medium transition disabled:opacity-50 {{ $confirmClasses }}">
                    {{ $confirmLabel ?? __('Confirm') }}
                </button>
            </div>
        </div>
    </div>
@endif
