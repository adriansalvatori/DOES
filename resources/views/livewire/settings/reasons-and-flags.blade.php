<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-4xl">
    <x-ui.page-header :title="__('Reasons & flags')" :subtitle="__('Names shown for block, hold and close reasons and for flags, in English and Spanish.')" />

    <div class="flex items-center gap-1 border-b border-line shrink-0 text-body text-ink-3 overflow-x-auto">
        @foreach($kinds as $value => $name)
            <button type="button" wire:click="$set('kind', '{{ $value }}')" wire:key="tab-{{ $value }}"
                class="px-3 py-2 border-b-2 cursor-pointer transition whitespace-nowrap {{ $kind === $value ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
                {{ $name }}
            </button>
        @endforeach
    </div>

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="bg-surface border border-line rounded-card overflow-hidden">
        <div class="px-4 py-3 border-b border-line">
            <p class="text-meta text-ink-3">{{ __('The codes are built into the workflow. You can rename them and switch them off, but not add or delete them.') }}</p>
        </div>

        <table class="w-full text-left text-body text-ink-2">
            <thead class="border-b border-line text-meta font-medium text-ink-3">
                <tr>
                    <th class="py-2.5 px-4">{{ __('Code') }}</th>
                    <th class="py-2.5 px-4">{{ __('English') }}</th>
                    <th class="py-2.5 px-4">{{ __('Spanish') }}</th>
                    <th class="py-2.5 px-4">{{ __('Status') }}</th>
                    <th class="py-2.5 px-4 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($labels as $label)
                    <tr wire:key="label-{{ $label->id }}" class="hover:bg-sunken transition-colors">
                        <td class="py-3 px-4 font-mono text-meta text-ink-3">{{ $label->code }}</td>
                        @if($editingId === $label->id)
                            <td class="py-2 px-4">
                                <input wire:model="label_en" type="text" class="ui-input" aria-label="{{ __('English label') }}" />
                                @error('label_en') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="py-2 px-4">
                                <input wire:model="label_es" type="text" class="ui-input" aria-label="{{ __('Spanish label') }}" />
                                @error('label_es') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="py-3 px-4"></td>
                            <td class="py-2 px-4 text-right whitespace-nowrap">
                                <flux:button wire:click="cancelEdit" type="button" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                                <flux:button wire:click="save" type="button" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                            </td>
                        @else
                            <td class="py-3 px-4 {{ $label->active ? 'text-ink' : 'text-ink-3' }}">{{ $label->label_en }}</td>
                            <td class="py-3 px-4 {{ $label->active ? 'text-ink' : 'text-ink-3' }}">{{ $label->label_es }}</td>
                            <td class="py-3 px-4">
                                <button wire:click="toggleActive({{ $label->id }})" type="button" class="cursor-pointer">
                                    <x-ui.tag :tone="$label->active ? 'ok' : 'neutral'">{{ $label->active ? __('Active') : __('Inactive') }}</x-ui.tag>
                                </button>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button wire:click="edit({{ $label->id }})" type="button"
                                    class="p-1.5 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer" title="{{ __('Edit') }}">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </button>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
