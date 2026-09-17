<div class="h-full flex flex-col space-y-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 max-w-5xl mx-auto">
    <!-- Top Header & Search Bar (Trello Docs Style) -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 bg-blue-600 text-white rounded-xl flex items-center justify-center font-bold shadow-xs shrink-0">
                <x-lucide-book-open class="w-6 h-6 text-white" />
            </div>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-bold text-zinc-900 tracking-tight">{{ __('Guía de Comportamientos') }}</h1>
                    <span class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-semibold">
                        {{ __('Trello & Kudos Docs') }}
                    </span>
                </div>
                <p class="text-xs text-zinc-500 mt-1">{{ __('Aprende cómo fluyen las tarjetas, qué ocurre al programar subtareas y cómo responder a los cambios de estado.') }}</p>
            </div>
        </div>

        <div class="w-full md:w-80 shrink-0">
            <div class="relative">
                <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-3" />
                <input 
                    wire:model.live.debounce.200ms="search" 
                    type="text" 
                    placeholder="{{ __('Buscar regla o comportamiento...') }}" 
                    class="w-full pl-9 pr-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" />
            </div>
        </div>
    </div>

    <!-- Category Pill Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none shrink-0 text-xs">
        <button 
            wire:click="selectCategory('all')" 
            class="px-4 py-2 rounded-xl font-semibold transition cursor-pointer flex items-center gap-2 shrink-0 {{ $activeCategory === 'all' ? 'bg-zinc-900 text-white shadow-2xs' : 'bg-white text-zinc-600 border border-stone-200 hover:bg-stone-50' }}">
            <x-lucide-layers class="w-4 h-4 text-blue-400" />
            <span>{{ __('Todas las Guías') }}</span>
        </button>

        @foreach($allCategories as $catKey => $cat)
            <button 
                wire:click="selectCategory('{{ $catKey }}')" 
                class="px-4 py-2 rounded-xl font-semibold transition cursor-pointer flex items-center gap-2 shrink-0 {{ $activeCategory === $catKey ? 'bg-zinc-900 text-white shadow-2xs' : 'bg-white text-zinc-600 border border-stone-200 hover:bg-stone-50' }}">
                @if($cat['icon'] === 'git-commit')
                    <x-lucide-git-commit class="w-4 h-4 text-indigo-500" />
                @elseif($cat['icon'] === 'list-checks')
                    <x-lucide-list-checks class="w-4 h-4 text-emerald-500" />
                @elseif($cat['icon'] === 'tags')
                    <x-lucide-tags class="w-4 h-4 text-fuchsia-500" />
                @elseif($cat['icon'] === 'refresh-cw')
                    <x-lucide-refresh-cw class="w-4 h-4 text-blue-500" />
                @elseif($cat['icon'] === 'alert-triangle')
                    <x-lucide-alert-triangle class="w-4 h-4 text-amber-500" />
                @elseif($cat['icon'] === 'database')
                    <x-lucide-database class="w-4 h-4 text-rose-500" />
                @else
                    <x-lucide-file-text class="w-4 h-4 text-stone-500" />
                @endif
                <span>{{ $cat['title'] }}</span>
            </button>
        @endforeach
    </div>

    <!-- Main Canvas Document (Clean Open Trello Knowledge Base Style) -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 md:p-10 shadow-2xs">
        @if(empty($categories))
            <div class="py-16 text-center text-zinc-400 space-y-3">
                <x-lucide-search-x class="w-12 h-12 mx-auto text-zinc-300" />
                <p class="text-sm font-medium text-zinc-600">{{ __('No encontramos explicaciones que coincidan con tu búsqueda.') }}</p>
                <button wire:click="$set('search', '')" class="text-xs text-blue-600 font-semibold hover:underline">
                    {{ __('Restablecer filtro de búsqueda') }}
                </button>
            </div>
        @else
            <div class="space-y-12 divide-y divide-stone-100">
                @foreach($categories as $catKey => $cat)
                    @if($activeCategory === 'all' || $activeCategory === $catKey)
                        <div class="{{ $loop->first ? '' : 'pt-10' }} space-y-6">
                            <!-- Category Header Title (Open Trello Style) -->
                            <div class="flex items-center justify-between border-b border-stone-200/80 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-stone-100 text-zinc-800 flex items-center justify-center font-bold">
                                        @if($cat['icon'] === 'git-commit')
                                            <x-lucide-git-commit class="w-4 h-4 text-indigo-600" />
                                        @elseif($cat['icon'] === 'list-checks')
                                            <x-lucide-list-checks class="w-4 h-4 text-emerald-600" />
                                        @elseif($cat['icon'] === 'tags')
                                            <x-lucide-tags class="w-4 h-4 text-fuchsia-600" />
                                        @elseif($cat['icon'] === 'refresh-cw')
                                            <x-lucide-refresh-cw class="w-4 h-4 text-blue-600" />
                                        @elseif($cat['icon'] === 'alert-triangle')
                                            <x-lucide-alert-triangle class="w-4 h-4 text-amber-600" />
                                        @elseif($cat['icon'] === 'database')
                                            <x-lucide-database class="w-4 h-4 text-rose-600" />
                                        @else
                                            <x-lucide-file-text class="w-4 h-4 text-stone-600" />
                                        @endif
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-zinc-900 tracking-tight">{{ $cat['title'] }}</h2>
                                        <p class="text-xs text-zinc-500">{{ $cat['description'] }}</p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-stone-100 text-stone-600 uppercase tracking-wider">
                                    {{ $cat['badge'] }}
                                </span>
                            </div>

                            <!-- Articles within Category -->
                            <div class="space-y-8">
                                @foreach($cat['articles'] as $article)
                                    <div class="space-y-4">
                                        <div>
                                            <h3 class="text-sm font-bold text-zinc-800 tracking-tight flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                {{ $article['title'] }}
                                            </h3>
                                            @if(isset($article['summary']))
                                                <p class="text-xs text-zinc-600 mt-1 pl-4 leading-relaxed">{{ $article['summary'] }}</p>
                                            @endif
                                        </div>

                                        <!-- Trello Lifecycle Flow Timeline (Open Stepper Line) -->
                                        @if(isset($article['steps']))
                                            <div class="pl-4 py-2">
                                                <div class="relative border-l-2 border-stone-200 pl-6 space-y-6">
                                                    @foreach($article['steps'] as $idx => $step)
                                                        <div class="relative group">
                                                            <!-- Timeline Dot -->
                                                            <div class="absolute -left-[31px] top-0.5 w-4 h-4 rounded-full bg-white border-2 border-blue-600 flex items-center justify-center">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                                            </div>
                                                            <div class="space-y-1">
                                                                <div class="flex items-center gap-2.5">
                                                                    <span class="font-bold text-xs text-zinc-900">{{ $step['name'] }}</span>
                                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $step['badge_style'] }}">
                                                                        {{ $step['badge'] }}
                                                                    </span>
                                                                </div>
                                                                <p class="text-xs text-zinc-600 leading-relaxed">{{ $step['desc'] }}</p>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Clean Open Bullet List (No Boxes) -->
                                        @if(isset($article['points']))
                                            <div class="pl-4 space-y-2.5 my-3">
                                                @foreach($article['points'] as $point)
                                                    <div class="flex items-start gap-2.5 text-xs text-zinc-700 leading-relaxed">
                                                        <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                                                        <span>{{ $point }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Substatus Badges Open Grid -->
                                        @if(isset($article['examples']))
                                            <div class="pl-4 grid grid-cols-1 sm:grid-cols-2 gap-3 my-3">
                                                @foreach($article['examples'] as $ex)
                                                    <div class="flex items-start gap-3 p-3 rounded-xl hover:bg-stone-50 transition border border-stone-100">
                                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border shrink-0 {{ $ex['badge_style'] }}">
                                                            {{ $ex['badge'] }}
                                                        </span>
                                                        <p class="text-xs text-zinc-600 leading-relaxed">{{ $ex['desc'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Trello Callout Pro-Tip Banner -->
                                        @if(isset($article['tip']))
                                            <div class="ml-4 p-4 rounded-r-xl bg-blue-50/70 border-l-4 border-blue-500 text-xs text-blue-950 flex items-start gap-3">
                                                <x-lucide-info class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                                <div class="leading-relaxed">
                                                    <strong class="font-bold text-blue-900">{{ __('Nota Operativa') }}:</strong>
                                                    <span class="ml-1 text-blue-800">{{ $article['tip'] }}</span>
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
