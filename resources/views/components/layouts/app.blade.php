<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-canvas text-zinc-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>

    <!-- PWA & Favicon / App Icon -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="app-name" content="{{ config('app.name') }}">
    <meta name="theme-color" content="#fbfbfa">

    <x-favicon-inverter />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">

    <x-js-i18n />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            --planner-header-h: 148px;
        }
        /* Anti-layout shift: Pre-set widths before Alpine hydrates */
        aside.app-sidebar {
            width: 16rem;
        }
        html.sidebar-collapsed aside.app-sidebar {
            width: 4rem !important;
        }
        div.app-main-container {
            padding-left: 16rem;
        }
        html.sidebar-collapsed div.app-main-container {
            padding-left: 4rem !important;
        }
        /* Prevent 200ms transition flash while page is booting */
        body.is-booting aside.app-sidebar,
        body.is-booting div.app-main-container {
            transition: none !important;
        }
        @keyframes appFadeIn {
            from { opacity: 0.5; transform: translateY(1px); }
            to { opacity: 1; transform: translateY(0); }
        }
        main.app-main-content {
            animation: appFadeIn 0.12s ease-out;
        }
        @media (prefers-reduced-motion: reduce) {
            main.app-main-content { animation: none; }
        }
        {!! app(\App\Services\ColorCodingService::class)->generateCssVariables() !!}
    </style>
    <script>
        (function() {
            try {
                if (localStorage.getItem('sidebar_open') === 'false') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
@php
    $authUser = auth()->user();
    $isDesigner = (bool) $authUser?->isDesigner();
    $isSales = (bool) $authUser?->isSales();
    $isAdmin = (bool) $authUser?->isAdmin();
    $isCoordinator = (bool) $authUser?->isCoordinator();

    // Navigation is defined once in NavigationService (sidebar, settings hub and rail, ⌘K palette).
    $navigation = app(\App\Services\NavigationService::class);
    $navGroups = $navigation->primaryGroups($authUser);
    $bottomItems = $navigation->bottomItems($authUser);
    $settingsGroups = $navigation->settingsGroups($authUser);
    $onSettingsPage = request()->is('settings/*');

    $navLinkClass = fn (bool $active): string => 'w-full h-8 px-2.5 rounded-control flex items-center gap-2.5 transition text-body '
        . ($active ? 'bg-[#ebebe7] text-ink font-medium' : 'text-ink-2 hover:bg-[#efefeb] hover:text-ink');
    $navIconClass = fn (bool $active): string => 'w-4 h-4 shrink-0 ' . ($active ? 'text-ink' : 'text-ink-3');
    $settingsActive = request()->is('settings', 'settings/*');
@endphp
<body
    x-data="{
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false'
    }"
    x-init="
        $watch('sidebarOpen', val => {
            localStorage.setItem('sidebar_open', val);
            document.documentElement.classList.toggle('sidebar-collapsed', !val);
        });
        document.documentElement.classList.toggle('sidebar-collapsed', !sidebarOpen);
        requestAnimationFrame(() => {
            document.body.classList.remove('is-booting');
        });
    "
    class="is-booting h-full bg-canvas text-zinc-800 flex antialiased selection:bg-stone-200">

    <!-- Left Sidebar Navigation (Collapsible) -->
    <aside
        id="app-sidebar"
        :class="sidebarOpen ? 'w-64' : 'w-16'"
        class="app-sidebar fixed inset-y-0 left-0 bg-[#f7f7f5] border-r border-line flex flex-col justify-between z-40 select-none transition-all duration-200 ease-in-out">

        <!-- Brand -->
        <div class="p-3 pb-0 shrink-0">
            <div :class="sidebarOpen ? 'justify-start' : 'justify-center'" class="flex items-center pb-3 border-b border-line">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 min-w-0" title="{{ __('Go to Dashboard') }}">
                    <div class="w-7 h-7 rounded-lg bg-gold flex items-center justify-center shrink-0">
                        <img src="{{ asset('images/kudos-hand-white.svg') }}" alt="{{ config('app.name') }}" class="w-4.5 h-4.5 object-contain">
                    </div>
                    <span x-show="sidebarOpen" x-transition.opacity class="font-semibold text-row text-ink tracking-tight truncate">{{ config('app.name') }}</span>
                </a>
            </div>
        </div>

        <!-- Primary Navigation -->
        <div class="flex-1 flex flex-col min-h-0 overflow-y-auto overflow-x-hidden p-3 pt-2 custom-vertical-scrollbar">
            <nav aria-label="{{ __('Main navigation') }}">
                @foreach($navGroups as $group)
                    @if(! $loop->first)
                        <div class="my-2 border-t border-line"></div>
                    @endif
                    <div class="space-y-0.5">
                        @foreach($group as $item)
                            @php $isActive = request()->is($item['pattern']); @endphp
                            <a href="{{ $item['href'] }}" wire:navigate title="{{ $item['title'] }}" class="{{ $navLinkClass($isActive) }}" @if($isActive) aria-current="page" @endif>
                                {{ svg('lucide-' . $item['icon'], $navIconClass($isActive)) }}
                                <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </div>

        <!-- Bottom Navigation: Settings hub, Backlog, Sync -->
        <div class="shrink-0 p-3 pt-2 pb-2 space-y-0.5 border-t border-line">
            <a href="{{ route('settings.index') }}" wire:navigate title="{{ __('Settings') }}" class="{{ $navLinkClass($settingsActive) }}" @if($settingsActive) aria-current="page" @endif>
                <x-lucide-settings class="{{ $navIconClass($settingsActive) }}" />
                <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Settings') }}</span>
            </a>

            @foreach($bottomItems as $item)
                @php $isActive = request()->is($item['pattern']); @endphp
                <a href="{{ $item['href'] }}" wire:navigate title="{{ $item['title'] }}" class="{{ $navLinkClass($isActive) }}" @if($isActive) aria-current="page" @endif>
                    {{ svg('lucide-' . $item['icon'], $navIconClass($isActive)) }}
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- Account: the single place for profile, language and sign-out -->
        @auth
            <div
                x-data="{ userMenuOpen: false }"
                class="relative px-3 py-2 border-t border-line bg-[#f7f7f5] flex items-center justify-between gap-2 shrink-0">

                <a
                    x-show="sidebarOpen"
                    href="{{ route('settings.profile') }}"
                    wire:navigate
                    class="flex items-center gap-2 min-w-0 group hover:opacity-80 transition"
                    title="{{ $authUser->name }}">
                    @if($authUser->avatar_url)
                        <div x-data="{ imgError: false }" class="shrink-0">
                            <img src="{{ $authUser->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $authUser->name }}" class="w-7 h-7 rounded-full object-cover border border-stone-200" />
                            <div x-show="imgError" x-cloak class="w-7 h-7 rounded-full bg-stone-900 text-white font-bold text-[11px] flex items-center justify-center">
                                {{ $authUser->initials }}
                            </div>
                        </div>
                    @else
                        <div class="w-7 h-7 rounded-full bg-stone-900 text-white font-bold text-[11px] flex items-center justify-center shrink-0">
                            {{ $authUser->initials }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-zinc-900 truncate leading-tight">{{ $authUser->name }}</p>
                        <span class="text-meta text-zinc-500 block truncate leading-tight">{{ $authUser->role?->label() ?? __('Designer') }}</span>
                    </div>
                </a>

                <!-- Collapsed avatar button -->
                <button
                    x-show="!sidebarOpen"
                    @click="userMenuOpen = !userMenuOpen"
                    type="button"
                    :aria-expanded="userMenuOpen.toString()"
                    class="w-7 h-7 mx-auto rounded-full overflow-hidden flex items-center justify-center shrink-0 hover:ring-2 hover:ring-stone-400 transition cursor-pointer {{ $authUser->avatar_url ? 'border border-stone-200' : 'bg-stone-900 text-white font-bold text-[11px]' }}"
                    title="{{ $authUser->name }}">
                    @if($authUser->avatar_url)
                        <div x-data="{ imgError: false }" class="w-full h-full flex items-center justify-center">
                            <img src="{{ $authUser->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $authUser->name }}" class="w-full h-full object-cover" />
                            <span x-show="imgError" x-cloak class="font-bold text-[11px] text-white bg-stone-900 w-full h-full flex items-center justify-center">{{ $authUser->initials }}</span>
                        </div>
                    @else
                        {{ $authUser->initials }}
                    @endif
                </button>

                <!-- Account popover (collapsed sidebar) -->
                <div
                    x-show="userMenuOpen && !sidebarOpen"
                    x-cloak
                    @click.outside="userMenuOpen = false"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute left-14 bottom-2 z-50 bg-white shadow-menu border border-line rounded-card p-2 min-w-[200px] space-y-1 text-xs"
                    style="display: none;">
                    <div class="px-2 py-1.5 border-b border-stone-100">
                        <p class="font-semibold text-zinc-900 truncate">{{ $authUser->name }}</p>
                        <span class="text-meta text-zinc-500 block truncate">{{ $authUser->role?->label() ?? __('Designer') }}</span>
                    </div>
                    <a href="{{ route('settings.profile') }}" wire:navigate class="w-full px-2 py-1.5 rounded-md font-medium flex items-center gap-2 text-zinc-700 hover:bg-stone-50 transition">
                        <x-lucide-user class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('My profile') }}</span>
                    </a>
                    <a href="/settings/language" wire:navigate class="w-full px-2 py-1.5 rounded-md font-medium flex items-center gap-2 text-zinc-700 hover:bg-stone-50 transition">
                        <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('Language') }}</span>
                    </a>
                    <div class="border-t border-stone-100 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-2 py-1.5 rounded-md font-medium flex items-center gap-2 text-red-600 hover:bg-red-50 transition cursor-pointer text-left">
                            <x-lucide-log-out class="w-3.5 h-3.5" />
                            <span>{{ __('Log out') }}</span>
                        </button>
                    </form>
                </div>

                <!-- Sign out (open sidebar) -->
                <form method="POST" action="{{ route('logout') }}" x-show="sidebarOpen">
                    @csrf
                    <button type="submit" class="p-1 rounded text-zinc-400 hover:text-red-600 hover:bg-stone-200/60 transition cursor-pointer" title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}">
                        <x-lucide-log-out class="w-4 h-4" />
                    </button>
                </form>
            </div>
        @endauth

    </aside>

    <!-- Main Content Container (Dynamic Padding for Collapsible Sidebar) -->
    <div
        id="app-main-container"
        :class="sidebarOpen ? 'pl-64' : 'pl-16'"
        class="app-main-container flex-1 h-screen max-h-screen flex flex-col w-full bg-canvas transition-all duration-200 ease-in-out overflow-hidden">

        <!-- Demo Environment Notification Banner -->
        @if(app(\App\Services\DemoEnvironmentService::class)->isDemo())
            <div class="bg-amber-500/10 border-b border-amber-500/25 px-6 py-1.5 flex items-center justify-between text-xs text-amber-900 shrink-0 select-none">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 font-semibold bg-amber-500/20 text-amber-900 px-2 py-0.5 rounded text-meta">
                        <x-lucide-database class="w-3 h-3 text-amber-700" />
                        {{ __('Demo mode') }}
                    </span>
                    <span class="text-xs text-amber-800">
                        {{ __('Isolated database (demo.database.sqlite) — Disconnected from Trello') }}
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-amber-900 hover:text-amber-950 underline cursor-pointer">
                        {{ __('Exit demo') }}
                    </button>
                </form>
            </div>
        @endif

        <!-- Top Bar: where you are (from NavigationService, never the <title>), then search, new order and notifications -->
        @php
            $breadcrumb = $navigation->breadcrumb($authUser);
            $fallbackTitle = trim(preg_replace('/\s*[-–|·]\s*' . preg_quote(config('app.name'), '/') . '\s*$/u', '', __($title ?? '')));
        @endphp
        <header class="h-12 border-b border-line bg-canvas px-4 flex items-center gap-3 sticky top-0 z-50 shrink-0">
            <button type="button" @click="sidebarOpen = !sidebarOpen" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" title="{{ __('Show or hide menu') }}" aria-label="{{ __('Show or hide menu') }}">
                <x-lucide-panel-left />
            </button>
            <span class="h-4 w-px bg-line" aria-hidden="true"></span>
            <nav class="flex items-center gap-1.5 min-w-0 text-body" aria-label="{{ __('Location') }}">
                @forelse($breadcrumb as $crumb)
                    @if(! $loop->last)
                        <a href="{{ $crumb['href'] }}" wire:navigate class="text-ink-3 hover:text-ink transition truncate">{{ $crumb['label'] }}</a>
                        <x-lucide-chevron-right class="w-3.5 h-3.5 text-ink-3 shrink-0" aria-hidden="true" />
                    @else
                        <span class="font-medium text-ink truncate" aria-current="page">{{ $crumb['label'] }}</span>
                    @endif
                @empty
                    <span class="font-medium text-ink truncate">{{ $fallbackTitle !== '' ? $fallbackTitle : config('app.name') }}</span>
                @endforelse
            </nav>

            <div class="ml-auto flex items-center gap-2 shrink-0">
                <livewire:orders.header-search />
                <livewire:notifications.notification-center />
            </div>
        </header>

        <!-- Main Slot -->
        @if($onSettingsPage)
            <!-- Settings pages: sub-navigation rail + page -->
            <main class="app-main-content flex-1 w-full flex min-h-0 overflow-hidden">
                <nav class="hidden md:flex w-52 shrink-0 flex-col gap-4 border-r border-line px-3 py-4 overflow-y-auto custom-vertical-scrollbar" aria-label="{{ __('Settings') }}">
                    @foreach($settingsGroups as $group)
                        <div class="space-y-0.5">
                            <p class="px-2 pb-1 text-meta text-ink-3">{{ $group['label'] }}</p>
                            @foreach($group['items'] as $item)
                                @php $isActive = request()->is($item['pattern']); @endphp
                                <a href="{{ $item['href'] }}" wire:navigate title="{{ $item['title'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-control text-body transition {{ $isActive ? 'bg-sunken text-ink font-medium' : 'text-ink-2 hover:bg-sunken hover:text-ink' }}" @if($isActive) aria-current="page" @endif>
                                    {{ svg('lucide-' . $item['icon'], 'w-4 h-4 shrink-0 ' . ($isActive ? 'text-ink' : 'text-ink-3')) }}
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
                <div class="flex-1 min-w-0 {{ !empty($noPadding) ? 'p-0' : 'px-6 py-4' }} flex flex-col min-h-0 overflow-y-auto custom-vertical-scrollbar">
                    {{ $slot }}
                </div>
            </main>
        @else
            <main class="app-main-content flex-1 w-full {{ !empty($noPadding) ? 'p-0' : 'px-6 py-4' }} flex flex-col min-h-0 overflow-y-auto custom-vertical-scrollbar">
                {{ $slot }}
            </main>
        @endif
    </div>

    <!-- Global Order Detail Side Flyout Drawer Component -->
    <livewire:orders.order-detail-modal />

    <!-- Global Client Detail Side Flyout Drawer Component -->
    <livewire:clients.client-flyout-panel />

    <!-- Global Contact Detail Side Flyout Drawer Component -->
    <livewire:contacts.contact-flyout-panel />

    <!-- Global Create Order Modal Component -->
    <livewire:orders.create-order-modal />

    <!-- Global Unsaved Changes Warning Modal -->
    <x-dirty-confirm-modal />

    <!-- Global confirmation for record-data changes (old value next to new) -->
    <x-ui.confirm-change-modal />

    <!--
        Global toast. Dispatch with: $this->dispatch('toast', message: '...', type: 'error')
        type: success (default) | error | info. Errors stay on screen longer.
    -->
    <div
        x-data="{
            show: false,
            message: '',
            type: 'success',
            timeout: null,
            notify(detail) {
                const payload = (typeof detail === 'string') ? { message: detail } : (detail || {});
                this.message = payload.message ?? '';
                this.type = ['success', 'error', 'info'].includes(payload.type) ? payload.type : 'success';
                this.show = true;
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => { this.show = false; }, this.type === 'error' ? 6000 : 2500);
            }
        }"
        @toast.window="notify($event.detail)"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        style="display: none;"
        :role="type === 'error' ? 'alert' : 'status'"
        aria-live="polite"
        class="fixed bottom-5 right-5 z-[9999] max-w-sm bg-stone-900 text-white text-xs font-medium px-3.5 py-2.5 rounded-lg shadow-panel border border-stone-800 flex items-start gap-2 pointer-events-none"
    >
        <x-lucide-check-circle-2 x-show="type === 'success'" class="w-4 h-4 text-emerald-400 shrink-0" />
        <x-lucide-alert-circle x-show="type === 'error'" x-cloak class="w-4 h-4 text-red-400 shrink-0" />
        <x-lucide-info x-show="type === 'info'" x-cloak class="w-4 h-4 text-sky-300 shrink-0" />
        <span x-text="message"></span>
    </div>

    @livewireScripts
    @fluxScripts
</body>
</html>
