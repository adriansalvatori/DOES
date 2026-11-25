<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-4xl">
    <x-ui.page-header :title="__('Task templates')" :count="$templates->count()" :subtitle="__('Quick tasks to add to a deliverable. A gate holds the deliverable until it is done.')">
        <x-slot:actions>
            <flux:button wire:click="openCreateModal" type="button" variant="primary" size="sm" icon="plus">{{ __('New template') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="bg-surface border border-line rounded-card overflow-hidden">
        @if($templates->isEmpty())
            <x-ui.empty icon="list-checks" :title="__('No task templates yet.')" />
        @else
            <div class="divide-y divide-line">
                @foreach($templates as $template)
                    <div wire:key="template-{{ $template->id }}" class="px-4 py-3 flex items-center justify-between gap-4 hover:bg-sunken transition-colors">
                        <div class="min-w-0 flex-1 {{ $template->active ? '' : 'opacity-60' }}">
                            <p class="text-row font-medium text-ink truncate">{{ $template->name_en }}</p>
                            <p class="text-meta text-ink-3 truncate">{{ $template->name_es }}</p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if($template->isGate())
                                <x-ui.tag tone="neutral">{{ __('Gate') }}</x-ui.tag>
                                <span class="text-meta text-ink-3 hidden md:inline">{{ __('Advances to :status', ['status' => $template->advances_to?->label()]) }}</span>
                                @if($template->required)
                                    <x-ui.tag tone="neutral">{{ __('Required') }}</x-ui.tag>
                                @endif
                            @else
                                <x-ui.tag tone="neutral">{{ __('Work') }}</x-ui.tag>
                            @endif

                            <button wire:click="toggleActive({{ $template->id }})" type="button" class="cursor-pointer">
                                <x-ui.tag :tone="$template->active ? 'ok' : 'neutral'">{{ $template->active ? __('Active') : __('Inactive') }}</x-ui.tag>
                            </button>

                            <button wire:click="move({{ $template->id }}, 'up')" type="button" @disabled($loop->first)
                                class="p-1.5 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer disabled:opacity-30" title="{{ __('Move up') }}">
                                <x-lucide-arrow-up class="w-4 h-4" />
                            </button>
                            <button wire:click="move({{ $template->id }}, 'down')" type="button" @disabled($loop->last)
                                class="p-1.5 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer disabled:opacity-30" title="{{ __('Move down') }}">
                                <x-lucide-arrow-down class="w-4 h-4" />
                            </button>
                            <button wire:click="openEditModal({{ $template->id }})" type="button"
                                class="p-1.5 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer" title="{{ __('Edit') }}">
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                            <button wire:click="delete({{ $template->id }})" type="button"
                                wire:confirm="{{ __('Delete the template ":name"?', ['name' => $template->name_en]) }}"
                                class="p-1.5 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer" title="{{ __('Delete') }}">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-ink/40 flex items-center justify-center z-50 p-4">
            <div class="bg-surface rounded-panel shadow-panel border border-line w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-section font-semibold text-ink">{{ $editingId ? __('Edit template') : __('New template') }}</h3>
                    <button type="button" wire:click="closeModal" class="text-ink-3 hover:text-ink" aria-label="{{ __('Close') }}">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-3 text-body">
                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('English name') }}</label>
                        <input wire:model="name_en" type="text" class="ui-input" />
                        @error('name_en') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('Spanish name') }}</label>
                        <input wire:model="name_es" type="text" class="ui-input" />
                        @error('name_es') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('Kind') }}</label>
                        <select wire:model.live="kind" class="ui-select">
                            <option value="work">{{ __('Work') }}</option>
                            <option value="gate">{{ __('Gate') }}</option>
                        </select>
                    </div>

                    @if($kind === 'gate')
                        <div class="space-y-1">
                            <label class="block text-meta text-ink-2">{{ __('Advances to') }}</label>
                            <select wire:model="advances_to" class="ui-select">
                                <option value="">-- {{ __('Select status') }} --</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('advances_to') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="required" type="checkbox" class="rounded border-line-strong text-ink focus:ring-gold" />
                            <span class="text-ink-2">{{ __('Required before the deliverable can advance') }}</span>
                        </label>
                    @endif

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input wire:model="active" type="checkbox" class="rounded border-line-strong text-ink focus:ring-gold" />
                        <span class="text-ink-2">{{ __('Active') }}</span>
                    </label>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                        <flux:button type="button" wire:click="closeModal" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
