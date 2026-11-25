<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 w-full max-w-4xl pb-10">
    <x-ui.page-header :title="__('Behavior guide')" :subtitle="__('Kudos docs')">
        <x-slot:actions>
            <div class="relative w-full sm:w-72">
                <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    wire:model.live.debounce.200ms="search"
                    type="text"
                    placeholder="{{ __('Search rules or behaviors…') }}"
                    class="ui-input pl-9 pr-3" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex items-center gap-1 overflow-x-auto border-b border-line shrink-0 text-body text-ink-3">
        <button
            wire:click="selectCategory('all')"
            class="px-3 py-2 border-b-2 cursor-pointer shrink-0 transition {{ $activeCategory === 'all' ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
            {{ __('All guides') }}
        </button>

        @foreach($allCategories as $catKey => $cat)
            <button
                wire:click="selectCategory('{{ $catKey }}')"
                class="px-3 py-2 border-b-2 cursor-pointer shrink-0 transition {{ $activeCategory === $catKey ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
                {{ $cat['title'] }}
            </button>
        @endforeach
    </div>

    <div class="bg-surface border border-line rounded-card p-5 md:p-7">
        @if(empty($categories))
            <x-ui.empty icon="search-x" :title="__('No explanations match your search.')">
                <flux:button wire:click="$set('search', '')" variant="subtle" size="sm">{{ __('Clear search') }}</flux:button>
            </x-ui.empty>
        @else
            <div class="space-y-10 divide-y divide-line">
                @foreach($categories as $catKey => $cat)
                    @if($activeCategory === 'all' || $activeCategory === $catKey)
                        <div class="{{ $loop->first ? '' : 'pt-8' }} space-y-5">
                            <div class="flex items-start justify-between gap-3 border-b border-line pb-3">
                                <div>
                                    <h2 class="text-section font-semibold text-ink">{{ $cat['title'] }}</h2>
                                    <p class="text-body text-ink-3">{{ $cat['description'] }}</p>
                                </div>
                                <x-ui.tag tone="neutral">{{ $cat['badge'] }}</x-ui.tag>
                            </div>

                            <div class="space-y-7">
                                @foreach($cat['articles'] as $article)
                                    <div class="space-y-3">
                                        <div>
                                            <h3 class="text-row font-semibold text-ink">{{ $article['title'] }}</h3>
                                            @if(isset($article['summary']))
                                                <p class="text-body text-ink-2 mt-1">{{ $article['summary'] }}</p>
                                            @endif
                                        </div>

                                        @if(isset($article['steps']))
                                            <div class="relative border-l border-line-strong pl-5 ml-1 space-y-4">
                                                @foreach($article['steps'] as $idx => $step)
                                                    <div class="relative space-y-0.5">
                                                        <span class="absolute -left-[25px] top-1.5 w-2 h-2 rounded-full bg-ink-3"></span>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-body font-medium text-ink">{{ $step['name'] }}</span>
                                                            <x-ui.tag tone="neutral">{{ $step['badge'] }}</x-ui.tag>
                                                        </div>
                                                        <p class="text-body text-ink-2">{{ $step['desc'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(isset($article['points']))
                                            <ul class="space-y-1.5 list-disc pl-5 marker:text-ink-3">
                                                @foreach($article['points'] as $point)
                                                    <li class="text-body text-ink-2">{{ $point }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if(isset($article['examples']))
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                @foreach($article['examples'] as $ex)
                                                    <div class="flex items-start gap-3 p-3 rounded-control border border-line">
                                                        <x-ui.tag tone="neutral" class="shrink-0">{{ $ex['badge'] }}</x-ui.tag>
                                                        <p class="text-body text-ink-2">{{ $ex['desc'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(isset($article['tip']))
                                            <div class="p-3 rounded-control bg-sunken text-body text-ink-2 flex items-start gap-2.5">
                                                <x-lucide-info class="w-4 h-4 text-ink-3 shrink-0 mt-0.5" />
                                                <div>
                                                    <strong class="font-semibold text-ink">{{ __('Operational note') }}:</strong>
                                                    <span class="ml-1">{{ $article['tip'] }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
