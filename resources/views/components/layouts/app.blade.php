<!DOCTYPE html>
<html lang="es" class="h-full bg-[#fbfbfa] text-zinc-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? (config('app.name') . ' - Trello Workflow Manager') }}</title>
    
    <!-- PWA & Favicon / App Icon -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
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
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
    
    <style>
        [x-cloak] {
            display: none !important;
        }
        /* Livewire wire:navigate Loading Progress Bar */
        #nprogress {
            pointer-events: none;
        }
        #nprogress .bar {
            background: #eda621 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 4px !important;
            z-index: 999999 !important;
            box-shadow: none !important;
        }
        #nprogress .peg {
            box-shadow: none !important;
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
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #fbfbfa;
            color: #252525;
        }
        code, pre, .font-mono {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
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
<body 
    x-data="{ 
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false',
        configOpen: localStorage.getItem('config_open') !== 'false',
        configPopover: false 
    }"
    x-init="
        $watch('sidebarOpen', val => {
            localStorage.setItem('sidebar_open', val);
            document.documentElement.classList.toggle('sidebar-collapsed', !val);
        });
        $watch('configOpen', val => localStorage.setItem('config_open', val));
        document.documentElement.classList.toggle('sidebar-collapsed', !sidebarOpen);
        requestAnimationFrame(() => {
            document.body.classList.remove('is-booting');
        });
    "
    class="is-booting h-full bg-[#fbfbfa] text-zinc-800 flex antialiased selection:bg-stone-200">

    <!-- Notion Left Sidebar Navigation (Collapsible) -->
    <aside 
        id="app-sidebar"
        :class="sidebarOpen ? 'w-64' : 'w-16'"
        class="app-sidebar fixed inset-y-0 left-0 bg-[#f7f7f5] border-r border-[#e9e9e7] flex flex-col justify-between z-40 select-none transition-all duration-200 ease-in-out">
        
        <!-- Workspace / Brand Header & Collapse Toggle (Pinned Top) -->
        <div class="p-3 pb-0 shrink-0">
            <div :class="sidebarOpen ? 'justify-between' : 'justify-center'" class="flex items-center pb-3 border-b border-[#e9e9e7]">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 min-w-0 group cursor-pointer" title="Ir al Dashboard">
                    <div class="w-7 h-7 rounded-lg bg-[#eda621] flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                        <img src="{{ asset('images/kudos-hand-white.svg') }}" alt="{{ config('app.name') }}" class="w-4.5 h-4.5 object-contain">
                    </div>
                    <div x-show="sidebarOpen" x-transition.opacity class="min-w-0">
                        <h1 class="font-semibold text-xs text-zinc-900 tracking-tight truncate group-hover:text-stone-900">{{ config('app.name') }}</h1>
                        <span class="text-[10px] text-zinc-500 font-normal block truncate">Trello Workflow Layer</span>
                    </div>
                </a>

                <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-[#efefed] text-zinc-400 hover:text-zinc-700 transition shrink-0 hidden" title="Colapsar / Expandir Sidebar">
                    <x-lucide-panel-left-close x-show="sidebarOpen" class="w-4 h-4" />
                    <x-lucide-panel-left-open x-show="!sidebarOpen" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Scrollable Navigation Area -->
        <div class="flex-1 flex flex-col min-h-0 overflow-y-auto overflow-x-hidden p-3 pt-2 custom-vertical-scrollbar">
            <!-- Operational Navigation Links (Notion Sidebar Item Style) -->
            <nav class="space-y-1 text-xs">
                <!-- Control Center -->
                <a 
                    href="/" 
                    wire:navigate
                    title="{{ __('Centro de Control Operativo') }}" 
                    class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('/') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-activity class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Centro de Control') }}</span>
                </a>

                <!-- Analytics -->
                @if(!auth()->user()?->isDesigner())
                    <a 
                        href="/analytics" 
                        wire:navigate
                        title="{{ __('Analytics Dashboard') }}" 
                        class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('analytics*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-bar-chart-3 class="w-4 h-4 text-zinc-500 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Analytics') }}</span>
                    </a>
                @endif

                <!-- (separador) -->
                <div class="my-2 border-t border-[#e9e9e7]"></div>

                <!-- Kanban Board -->
                <a href="/kanban" wire:navigate title="{{ __('Kanban Board') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('kanban*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-kanban class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Kanban Board') }}</span>
                </a>

                <!-- Weekly Planner -->
                <a href="/planner" wire:navigate title="{{ __('Planificador Semanal') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('planner*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Planificador Semanal') }}</span>
                </a>

                <!-- Overview -->
                @if(!auth()->user()?->isDesigner())
                    <a href="/overview" wire:navigate title="{{ __('Overview Operativo') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('overview*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-table-properties class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Overview') }}</span>
                    </a>
                @endif


                <!-- (separador) -->
                <div class="my-2 border-t border-[#e9e9e7]"></div>

                <!-- Clientes -->
                <a href="/clients" wire:navigate title="{{ __('Clientes') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('clients*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-building-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Clientes') }}</span>
                </a>

                <!-- Archivadas -->
                <a href="/archived" wire:navigate title="{{ __('Órdenes Archivadas') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('archived*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-archive class="w-4 h-4 text-slate-600 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Archivadas') }}</span>
                </a>

                <!-- Papelera -->
                @if(!auth()->user()?->isDesigner() && !auth()->user()?->isSales())
                    <a href="/trash" wire:navigate title="{{ __('Papelera de Reciclaje') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('trash*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-trash-2 class="w-4 h-4 text-red-500 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Papelera') }}</span>
                    </a>
                @endif

                <!-- (separador) -->
                <div class="my-2 border-t border-[#e9e9e7]"></div>

                <!-- Catálogo & Precios (Agrupado en sección aparte) -->
                <div class="space-y-1">
                    <span x-show="sidebarOpen" x-transition.opacity class="text-[10px] uppercase font-semibold text-zinc-400 tracking-wider block px-2.5 mb-1.5">{{ __('Catálogo & Precios') }}</span>
                    
                    <!-- Precios & Calculadora -->
                    <a href="/pricing" wire:navigate title="{{ __('Precios & Calculadora') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('pricing*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-badge-percent class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Precios & Calculadora') }}</span>
                    </a>

                    <!-- Recursos de Diseño -->
                    <a href="/design-resources" wire:navigate title="{{ __('Recursos de Diseño') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('design-resources*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-folder-down class="w-4 h-4 text-indigo-600 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Recursos de Diseño') }}</span>
                    </a>
                </div>

                <!-- (separador) -->
                <div class="my-2 border-t border-[#e9e9e7]"></div>

                <!-- Team Designers List -->
                <div id="tour-designer-colors" class="space-y-1">
                    <span x-show="sidebarOpen" x-transition.opacity class="text-[10px] uppercase font-semibold text-zinc-400 tracking-wider block px-2.5 mb-1.5">{{ __('Diseñadores') }}</span>
                    <div class="space-y-1 text-xs text-zinc-600 font-medium">
                        <div class="w-full px-2.5 py-1.5 rounded-md flex items-center gap-2.5 transition hover:bg-[#efefed]/70" title="Euralíz">
                            <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" style="background-color: var(--cc-designer-euraliz-solid, var(--cc-designer_euraliz-solid, #FFA9FF));"></span>
                            </div>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Euralíz</span>
                        </div>
                        <div class="w-full px-2.5 py-1.5 rounded-md flex items-center gap-2.5 transition hover:bg-[#efefed]/70" title="César">
                            <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" style="background-color: var(--cc-designer-cesar-solid, var(--cc-designer_cesar-solid, #00CDDD));"></span>
                            </div>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">César</span>
                        </div>
                        <div class="w-full px-2.5 py-1.5 rounded-md flex items-center gap-2.5 transition hover:bg-[#efefed]/70" title="Adrián">
                            <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" style="background-color: var(--cc-designer-adrian-solid, var(--cc-designer_adrian-solid, #5CE0B0));"></span>
                            </div>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Adrián</span>
                        </div>
                        <div class="w-full px-2.5 py-1.5 rounded-md flex items-center gap-2.5 transition hover:bg-[#efefed]/70" title="{{ __('Diseñador Externo') }}">
                            <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" style="background-color: var(--cc-designer-external-solid, var(--cc-designer_external-solid, #FAD900));"></span>
                            </div>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Externo') }}</span>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Bottom-aligned Navigation Links (Docked above profile) -->
        <div class="shrink-0 p-3 pt-2 pb-1 space-y-1 text-xs border-t border-[#e9e9e7]">
            <!-- Settings Item with Context Dropdown Menu -->
            <div class="relative" @click.outside="configPopover = false">
                <button 
                    @click="sidebarOpen ? (configOpen = !configOpen) : (configPopover = !configPopover)" 
                    title="{{ __('Configuración') }}" 
                    class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center justify-between transition cursor-pointer text-xs {{ request()->is('settings*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}"
                    :class="(configPopover && !sidebarOpen) ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : ''">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-lucide-settings class="w-4 h-4 text-zinc-500 shrink-0" />
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Configuración') }}</span>
                    </div>
                    <x-lucide-chevron-down 
                        x-show="sidebarOpen" 
                        class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" 
                        x-bind:class="configOpen ? 'rotate-180' : ''" />
                </button>

                <!-- Expanded Sub-menu when Sidebar is Open -->
                <div 
                    x-show="configOpen && sidebarOpen" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="mt-1 pl-6 space-y-1 text-xs max-h-[300px] overflow-y-auto custom-vertical-scrollbar"
                    style="display: none;">
                    <a 
                        href="/settings/profile" 
                        wire:navigate
                        title="{{ __('Mi Perfil') }}" 
                        class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/profile*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-user-cog class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                        <span class="truncate">{{ __('Mi Perfil') }}</span>
                    </a>
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/users" 
                            wire:navigate
                            title="{{ __('Gestión de Usuarios') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/users*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-users class="w-3.5 h-3.5 text-purple-600 shrink-0" />
                            <span class="truncate">{{ __('Usuarios y Roles') }}</span>
                        </a>
                        <a 
                            href="/settings/notifications" 
                            wire:navigate
                            title="{{ __('Configuración de Notificaciones') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/notifications*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-bell-ring class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                            <span class="truncate">{{ __('Notificaciones') }}</span>
                        </a>
                    @endif
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/documentation" 
                            wire:navigate
                            title="{{ __('Guía de Comportamientos') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/documentation*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-book-open class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                            <span class="truncate">{{ __('Guía de Comportamientos') }}</span>
                        </a>
                    @endif
                    <a 
                        href="/settings/language" 
                        wire:navigate
                        title="{{ __('Idioma / Language') }}" 
                        class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/language*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                        <span class="truncate">{{ __('Idioma') }}</span>
                    </a>
                    @if(auth()->user()?->isAdmin() || auth()->user()?->isCoordinator())
                        <a 
                            href="/settings/color-coding" 
                            wire:navigate
                            title="{{ __('Personalización de Colores') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/color-coding*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-palette class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Color Coding') }}</span>
                        </a>
                        <a 
                            href="/settings/substatuses" 
                            wire:navigate
                            title="{{ __('Configuración de Subestatus') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/substatuses*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-tags class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Subestatus') }}</span>
                        </a>
                        <a 
                            href="/settings/installation-types" 
                            wire:navigate
                            title="{{ __('Configuración de Tipos de Instalación') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/installation-types*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-truck class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Instalación') }}</span>
                        </a>
                        <a 
                            href="/settings/subtasks" 
                            wire:navigate
                            title="{{ __('Plantillas de Subtareas') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/subtasks*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-list-checks class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Plantillas Subtareas') }}</span>
                        </a>
                    @endif
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/trello-mapping" 
                            wire:navigate
                            title="{{ __('Mapeo de Listas Trello') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/trello-mapping*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-sliders class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Mapeo Listas Trello') }}</span>
                        </a>
                        <a 
                            href="/settings/backups" 
                            wire:navigate
                            title="{{ __('Respaldos de Base de Datos (⌘S)') }}" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center justify-between gap-2 transition {{ request()->is('settings/backups*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-lucide-database class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span class="truncate">{{ __('Respaldos') }}</span>
                            </div>
                            <kbd x-show="sidebarOpen" class="hidden sm:inline-block text-[10px] font-mono text-zinc-400 bg-stone-200/70 border border-stone-300/80 px-1 py-0.2 rounded leading-tight">⌘S</kbd>
                        </a>
                    @endif
                </div>

                <!-- Floating Popover Context Menu when Sidebar is Collapsed -->
                <div 
                    x-show="configPopover && !sidebarOpen" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95 -translate-x-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-x-0"
                    class="absolute left-14 bottom-0 z-50 bg-white shadow-xl border border-stone-200 rounded-xl p-1.5 min-w-[190px] space-y-1 text-xs max-h-[calc(100vh-20px)] overflow-y-auto custom-vertical-scrollbar"
                    style="display: none;">
                    <div class="px-2 py-1 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">{{ __('Configuración') }}</div>
                    <a 
                        href="/settings/profile" 
                        wire:navigate
                        title="{{ __('Mi Perfil') }}" 
                        class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/profile*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                        <x-lucide-user-cog class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                        <span class="truncate">{{ __('Mi Perfil') }}</span>
                    </a>
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/users" 
                            wire:navigate
                            title="{{ __('Gestión de Usuarios') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/users*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-users class="w-3.5 h-3.5 text-purple-600 shrink-0" />
                            <span class="truncate">{{ __('Usuarios y Roles') }}</span>
                        </a>
                        <a 
                            href="/settings/notifications" 
                            wire:navigate
                            title="{{ __('Configuración de Notificaciones') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/notifications*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-bell-ring class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                            <span class="truncate">{{ __('Notificaciones') }}</span>
                        </a>
                    @endif
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/documentation" 
                            wire:navigate
                            title="{{ __('Guía de Comportamientos') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/documentation*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-book-open class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                            <span class="truncate">{{ __('Guía Comportamientos') }}</span>
                        </a>
                    @endif
                    <a 
                        href="/settings/language" 
                        wire:navigate
                        title="{{ __('Idioma / Language') }}" 
                        class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/language*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                        <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                        <span class="truncate">{{ __('Idioma') }}</span>
                    </a>
                    @if(auth()->user()?->isAdmin() || auth()->user()?->isCoordinator())
                        <a 
                            href="/settings/color-coding" 
                            wire:navigate
                            title="{{ __('Personalización de Colores') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/color-coding*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-palette class="w-3.5 h-3.5 text-pink-600 shrink-0" />
                            <span class="truncate">{{ __('Color Coding') }}</span>
                        </a>
                        <a 
                            href="/settings/substatuses" 
                            wire:navigate
                            title="{{ __('Configuración de Subestatus') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/substatuses*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-tags class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Subestatus') }}</span>
                        </a>
                        <a 
                            href="/settings/installation-types" 
                            wire:navigate
                            title="{{ __('Configuración de Tipos de Instalación') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/installation-types*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-truck class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Instalación') }}</span>
                        </a>
                        <a 
                            href="/settings/subtasks" 
                            wire:navigate
                            title="{{ __('Plantillas de Subtareas') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/subtasks*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-list-checks class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Plantillas Subtareas') }}</span>
                        </a>
                    @endif
                    @if(auth()->user()?->isAdmin())
                        <a 
                            href="/settings/trello-mapping" 
                            wire:navigate
                            title="{{ __('Mapeo de Listas Trello') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/trello-mapping*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-sliders class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Mapeo Listas Trello') }}</span>
                        </a>
                        <a 
                            href="/settings/backups" 
                            wire:navigate
                            title="{{ __('Respaldos de Base de Datos (⌘S)') }}" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center justify-between gap-2 transition {{ request()->is('settings/backups*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-lucide-database class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                                <span class="truncate">{{ __('Respaldos') }}</span>
                            </div>
                            <kbd class="text-[10px] font-mono text-zinc-400 bg-stone-200/70 border border-stone-300/80 px-1 py-0.2 rounded leading-tight">⌘S</kbd>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Backlog -->
            <a href="/backlog" wire:navigate title="{{ __('Backlog de Órdenes') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('backlog*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                <x-lucide-box class="w-4 h-4 text-zinc-500 shrink-0" />
                <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Backlog') }}</span>
            </a>

            <!-- Sincronización -->
            @if(auth()->user()?->isAdmin() || auth()->user()?->isCoordinator())
                <a href="/trello-sync" wire:navigate title="{{ __('Sincronización Trello') }}" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition text-xs {{ request()->is('trello-sync*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-refresh-cw class="w-4 h-4 text-blue-600 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">{{ __('Sincronización') }}</span>
                </a>
            @endif
        </div>

        <!-- User Profile & Logout Sidebar Footer -->
        @auth
            <div 
                x-data="{ userMenuOpen: false }"
                class="relative px-3 py-2 border-t border-[#e9e9e7] bg-[#f7f7f5] flex items-center justify-between gap-2 shrink-0">
                
                <a 
                    x-show="sidebarOpen"
                    href="{{ route('settings.profile') }}" 
                    wire:navigate
                    class="flex items-center gap-2 min-w-0 group hover:opacity-80 transition" 
                    title="{{ auth()->user()->name }}">
                    @if(auth()->user()->avatar_url)
                        <div x-data="{ imgError: false }" class="shrink-0">
                            <img src="{{ auth()->user()->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ auth()->user()->name }}" class="w-7 h-7 rounded-full object-cover shadow-2xs border border-stone-200" />
                            <div x-show="imgError" x-cloak class="w-7 h-7 rounded-full bg-stone-900 text-white font-bold text-[11px] flex items-center justify-center shadow-2xs">
                                {{ auth()->user()->initials }}
                            </div>
                        </div>
                    @else
                        <div class="w-7 h-7 rounded-full bg-stone-900 text-white font-bold text-[11px] flex items-center justify-center shrink-0 shadow-2xs">
                            {{ auth()->user()->initials }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-zinc-900 truncate leading-tight group-hover:text-stone-900">{{ auth()->user()->name }}</p>
                        <span class="text-[10px] text-zinc-500 block truncate">{{ auth()->user()->role?->label() ?? __('Diseñador') }}</span>
                    </div>
                </a>

                <!-- Collapsed Avatar Button with Popover -->
                <button 
                    x-show="!sidebarOpen"
                    @click="userMenuOpen = !userMenuOpen"
                    type="button"
                    class="w-7 h-7 mx-auto rounded-full overflow-hidden flex items-center justify-center shrink-0 hover:ring-2 hover:ring-stone-400 transition cursor-pointer shadow-2xs {{ auth()->user()->avatar_url ? 'border border-stone-200' : 'bg-stone-900 text-white font-bold text-[11px]' }}"
                    title="{{ auth()->user()->name }}">
                    @if(auth()->user()->avatar_url)
                        <div x-data="{ imgError: false }" class="w-full h-full flex items-center justify-center">
                            <img src="{{ auth()->user()->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ auth()->user()->name }}" class="w-full h-full object-cover" />
                            <span x-show="imgError" x-cloak class="font-bold text-[11px] text-white bg-stone-900 w-full h-full flex items-center justify-center">{{ auth()->user()->initials }}</span>
                        </div>
                    @else
                        {{ auth()->user()->initials }}
                    @endif
                </button>

                <!-- Floating Popover when Sidebar is Collapsed -->
                <div 
                    x-show="userMenuOpen && !sidebarOpen" 
                    x-cloak
                    @click.outside="userMenuOpen = false"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="absolute left-14 bottom-2 z-50 bg-white shadow-xl border border-stone-200 rounded-xl p-2 min-w-[200px] space-y-1 text-xs"
                    style="display: none;">
                    <div class="px-2 py-1.5 border-b border-stone-100">
                        <p class="font-semibold text-zinc-900 truncate">{{ auth()->user()->name }}</p>
                        <span class="text-[10px] text-zinc-500 block truncate">{{ auth()->user()->role?->label() ?? __('Diseñador') }}</span>
                    </div>
                    <a href="{{ route('settings.profile') }}" wire:navigate class="w-full px-2 py-1.5 rounded-lg font-medium flex items-center gap-2 text-zinc-700 hover:bg-stone-50 transition">
                        <x-lucide-user class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('Mi Perfil') }}</span>
                    </a>
                    <a href="/settings/language" wire:navigate class="w-full px-2 py-1.5 rounded-lg font-medium flex items-center gap-2 text-zinc-700 hover:bg-stone-50 transition">
                        <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500" />
                        <span>{{ __('Idioma') }}</span>
                    </a>
                    <div class="border-t border-stone-100 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-2 py-1.5 rounded-lg font-medium flex items-center gap-2 text-red-600 hover:bg-red-50 transition cursor-pointer text-left">
                            <x-lucide-log-out class="w-3.5 h-3.5" />
                            <span>{{ __('Cerrar Sesión') }}</span>
                        </button>
                    </form>
                </div>

                <!-- Expanded Logout Icon Button -->
                <form method="POST" action="{{ route('logout') }}" x-show="sidebarOpen">
                    @csrf
                    <button type="submit" class="p-1 rounded text-zinc-400 hover:text-red-600 hover:bg-stone-200/60 transition cursor-pointer" title="{{ __('Cerrar Sesión') }}">
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
        class="app-main-container flex-1 h-screen max-h-screen flex flex-col w-full bg-[#fbfbfa] transition-all duration-200 ease-in-out overflow-hidden">
        
        <!-- Demo Environment Notification Banner -->
        @if(app(\App\Services\DemoEnvironmentService::class)->isDemo())
            <div class="bg-amber-500/10 border-b border-amber-500/25 px-6 py-1.5 flex items-center justify-between text-xs text-amber-900 shrink-0 select-none">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 font-bold tracking-wider bg-amber-500/20 text-amber-900 px-2 py-0.5 rounded text-[10px] uppercase">
                        <x-lucide-database class="w-3 h-3 text-amber-700" />
                        {{ __('Modo Demostración') }}
                    </span>
                    <span class="text-[11px] text-amber-800">
                        {{ __('Base de datos aislada (demo.database.sqlite) — Desconectado de Trello') }}
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-[11px] font-medium text-amber-900 hover:text-amber-950 underline cursor-pointer">
                        {{ __('Salir de Demo') }}
                    </button>
                </form>
            </div>
        @endif

        <!-- Top Utility Bar -->
        <header class="h-11 border-b border-[#e9e9e7] bg-[#fbfbfa] px-6 flex items-center justify-between text-xs text-zinc-500 sticky top-0 z-50 backdrop-blur-xs shrink-0 gap-4">
            <div class="flex items-center gap-2.5 shrink-0">
                <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-[#efefed] text-zinc-500 hover:text-zinc-900 transition" title="Toggle Sidebar">
                    <x-lucide-panel-left class="w-4 h-4" />
                </button>
                <span class="font-medium text-zinc-700 hidden sm:flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-sm bg-[#eda621] flex items-center justify-center shrink-0">
                        <img src="{{ asset('images/kudos-hand-white.svg') }}" alt="{{ config('app.name') }}" class="w-2.5 h-2.5 object-contain">
                    </div>
                    {{ config('app.name') }}
                </span>
                <span class="hidden sm:inline">/</span>
                <span class="text-zinc-500 font-normal truncate max-w-[120px] sm:max-w-none">{{ __($title ?? 'Dashboard') }}</span>
            </div>

            <!-- Global Workspace Active Orders Search Bar -->
            <livewire:orders.header-search />

            @php
                $totalWorkspaceOrdersCount = \App\Models\Order::inWorkspace()->count();
                $overdueCount = \App\Models\Order::inWorkspace()->where('substatus', \App\Enums\Substatus::OVERDUE)->count();
            @endphp
            <div class="flex items-center gap-3 shrink-0">
                <!-- Livewire Notification Center -->
                <livewire:notifications.notification-center />

                <button 
                    id="tour-demo-btn"
                    @click="window.dispatchEvent(new CustomEvent('toggle-tutorial-mode'))"
                    title="{{ __('Activar / Desactivar Modo Demo Presentación') }}"
                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 transition flex items-center gap-1.5 cursor-pointer shrink-0 shadow-2xs">
                    <x-lucide-presentation class="w-3.5 h-3.5 text-emerald-600" />
                    <span class="hidden lg:inline">{{ __('Modo Demo') }}</span>
                </button>
                <span class="text-zinc-500 font-mono text-[11px] hidden md:inline">{{ __('Órdenes Activas') }}: <strong>{{ $totalWorkspaceOrdersCount }}</strong></span>
                @if($overdueCount > 0)
                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-semibold text-[10px]">
                        {{ $overdueCount }} {{ __('Atrasadas') }}
                    </span>
                @endif

                @auth
                    <!-- Header User Profile & Logout Dropdown -->
                    <div class="relative shrink-0 border-l border-[#e9e9e7] pl-2.5 ml-0.5" x-data="{ open: false }">
                        <button 
                            @click="open = !open" 
                            type="button" 
                            class="flex items-center gap-1.5 p-1 rounded-lg hover:bg-stone-200/60 transition cursor-pointer"
                            title="{{ auth()->user()->name }} ({{ auth()->user()->role?->label() }})">
                            @if(auth()->user()->avatar_url)
                                <div x-data="{ imgError: false }" class="shrink-0">
                                    <img src="{{ auth()->user()->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ auth()->user()->name }}" class="w-6 h-6 rounded-full object-cover shadow-2xs border border-stone-200" />
                                    <div x-show="imgError" x-cloak class="w-6 h-6 rounded-full bg-stone-900 text-white font-bold text-[10px] flex items-center justify-center shadow-2xs">
                                        {{ auth()->user()->initials }}
                                    </div>
                                </div>
                            @else
                                <div class="w-6 h-6 rounded-full bg-stone-900 text-white font-bold text-[10px] flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ auth()->user()->initials }}
                                </div>
                            @endif
                            <x-lucide-chevron-down class="w-3 h-3 text-zinc-400" />
                        </button>

                        <div 
                            x-show="open" 
                            x-cloak
                            @click.outside="open = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 bg-white border border-stone-200 rounded-xl shadow-lg py-1 z-50 text-xs"
                            style="display: none;">
                            
                            <div class="px-3 py-2 border-b border-stone-100">
                                <p class="font-semibold text-zinc-900 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-zinc-500 truncate">{{ auth()->user()->email }}</p>
                                <div class="mt-1">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-semibold border {{ auth()->user()->role?->badgeStyle() ?? 'bg-emerald-100 text-emerald-800 border-emerald-300' }}">
                                        {{ auth()->user()->role?->label() ?? __('Diseñador') }}
                                    </span>
                                </div>
                            </div>

                            <a href="{{ route('settings.profile') }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-zinc-700 hover:bg-stone-50 transition">
                                <x-lucide-user class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ __('Mi Perfil y Configuración') }}</span>
                            </a>

                            <a href="/settings/language" wire:navigate class="flex items-center gap-2 px-3 py-2 text-zinc-700 hover:bg-stone-50 transition">
                                <x-lucide-languages class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ __('Idioma') }}</span>
                            </a>

                            <div class="border-t border-stone-100 my-1"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 transition cursor-pointer text-left">
                                    <x-lucide-log-out class="w-3.5 h-3.5" />
                                    <span>{{ __('Cerrar Sesión') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <!-- Main Slot -->
        <main class="app-main-content flex-1 w-full px-6 py-4 flex flex-col min-h-0 overflow-y-auto custom-vertical-scrollbar">
            {{ $slot }}
        </main>
    </div>

    <!-- Global Order Detail Side Flyout Drawer Component -->
    <livewire:orders.order-detail-modal />

    <!-- Global Client Detail Side Flyout Drawer Component -->
    <livewire:clients.client-flyout-panel />

    <!-- Global Create Order Modal Component -->
    <livewire:orders.create-order-modal />

    <!-- Global Unsaved Changes Warning Modal -->
    <x-dirty-confirm-modal />

    <!-- Global Database Backup Shortcut Handler & Feedback -->
    <livewire:settings.database-backup-shortcut />

    <!-- Global Toast Notification Banner -->
    <div 
        x-data="{ 
            show: false, 
            message: '',
            timeout: null,
            notify(msg) {
                this.message = msg;
                this.show = true;
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => { this.show = false; }, 2500);
            }
        }"
        @toast.window="notify($event.detail.message || $event.detail)"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        style="display: none;"
        class="fixed bottom-5 right-5 z-[9999] bg-stone-900 text-white text-xs font-medium px-3.5 py-2.5 rounded-lg shadow-xl border border-stone-800 flex items-center gap-2 pointer-events-none"
    >
        <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-400 shrink-0" />
        <span x-text="message"></span>
    </div>

    @livewireScripts
    @fluxScripts
</body>
</html>
