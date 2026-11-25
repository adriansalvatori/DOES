<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-4xl">
    <x-ui.page-header :title="__('Board & WIP')" :subtitle="__('Limits for the board and the rule each column enforces before a card can leave it.')" />

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="bg-surface border border-line rounded-card p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
                <label for="defaultWipLimit" class="block text-meta text-ink-2">{{ __('Default WIP limit') }}</label>
                <input id="defaultWipLimit" wire:model="defaultWipLimit" type="number" min="1" class="ui-input" />
                <p class="text-meta text-ink-3">{{ __('Cards one designer can have in design at once. Each person can have their own in Users & roles.') }}</p>
                @error('defaultWipLimit') <p class="text-danger text-meta">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1">
                <label for="defaultColumnCap" class="block text-meta text-ink-2">{{ __('Cards per column') }}</label>
                <input id="defaultColumnCap" wire:model="defaultColumnCap" type="number" min="5" class="ui-input" />
                <p class="text-meta text-ink-3">{{ __('How many cards a board column shows before "show more". The real total is always shown.') }}</p>
                @error('defaultColumnCap') <p class="text-danger text-meta">{{ $message }}</p> @enderror
            </div>
        </div>

        @foreach($areas as $area)
            <div wire:key="area-{{ $area->value }}" class="border border-line rounded-card overflow-hidden">
                <p class="px-4 py-2 bg-sunken text-meta font-semibold text-ink-2">{{ $area->label() }}</p>
                <table class="w-full text-left text-body text-ink-2">
                    <thead class="text-meta font-medium text-ink-3 border-b border-line">
                        <tr>
                            <th class="py-2 px-4">{{ __('Column') }}</th>
                            <th class="py-2 px-4">{{ __('Exit rule') }}</th>
                            <th class="py-2 px-4 w-32">{{ __('Column cap') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($columns[$area->value] as $status)
                            <tr wire:key="status-{{ $status->value }}">
                                <td class="py-2.5 px-4 font-medium text-ink whitespace-nowrap">{{ $status->label() }}</td>
                                <td class="py-2.5 px-4">{{ $rules[$status->value] }}</td>
                                <td class="py-2 px-4">
                                    <input wire:model="columnCaps.{{ $status->value }}" type="number" min="5"
                                        placeholder="{{ $defaultColumnCap }}" class="ui-input" aria-label="{{ __('Column cap') }}: {{ $status->label() }}" />
                                    @error('columnCaps.'.$status->value) <p class="text-danger text-meta">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        <p class="text-meta text-ink-3">{{ __('A blocked or on-hold card cannot move forward in any column. Leave a column cap empty to use the default.') }}</p>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
