<!DOCTYPE html>
<html lang="es" class="h-full bg-[#fbfbfa] text-zinc-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Kudos Design Ops - Trello Workflow Manager' }}</title>
    
    <!-- PWA & Favicon / App Icon -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kudos DOES">
    <meta name="theme-color" content="#fbfbfa">
    
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=3">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
    
    <style>
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #fbfbfa;
            color: #252525;
        }
        code, pre, .font-mono {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
    </style>
</head>
<body 
    x-data="{ 
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false',
        dashboardOpen: {{ (request()->is('/') || request()->is('analytics*')) ? 'true' : 'false' }},
        configOpen: {{ (request()->is('settings*') || request()->is('trello-sync*')) ? 'true' : 'false' }} 
    }"
    x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebar_open', val))"
    class="h-full bg-[#fbfbfa] text-zinc-800 flex antialiased selection:bg-stone-200">

    <!-- Notion Left Sidebar Navigation (Collapsible) -->
    <aside 
        :class="sidebarOpen ? 'w-64' : 'w-16'"
        class="fixed inset-y-0 left-0 bg-[#f7f7f5] border-r border-[#e9e9e7] flex flex-col justify-between z-40 select-none transition-all duration-200 ease-in-out">
        
        <div class="p-3 space-y-4">
            <!-- Workspace / Brand Header & Collapse Toggle -->
            <div class="flex items-center justify-between pb-3 border-b border-[#e9e9e7]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset('favicon.png') }}" alt="Kudos Icon" class="w-7 h-7 rounded-md object-contain shrink-0 shadow-xs">
                    <div x-show="sidebarOpen" x-transition.opacity class="min-w-0">
                        <h1 class="font-semibold text-xs text-zinc-900 tracking-tight truncate">Kudos Design Ops</h1>
                        <span class="text-[10px] text-zinc-500 font-normal block truncate">Trello Workflow Layer</span>
                    </div>
                </div>

                <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-[#efefed] text-zinc-400 hover:text-zinc-700 transition shrink-0" title="Colapsar / Expandir Sidebar">
                    <x-lucide-panel-left-close x-show="sidebarOpen" class="w-4 h-4" />
                    <x-lucide-panel-left-open x-show="!sidebarOpen" class="w-4 h-4" />
                </button>
            </div>

            <!-- Operational Navigation Links (Notion Sidebar Item Style) -->
            <nav class="space-y-1 text-xs">
                <!-- Dashboard Item with Submenu Dropdown -->
                <div class="relative" @click.outside="if (!sidebarOpen) dashboardOpen = false">
                    <button 
                        @click="dashboardOpen = !dashboardOpen" 
                        title="Dashboard" 
                        class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center justify-between transition cursor-pointer text-xs {{ (request()->is('/') || request()->is('analytics*')) ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <x-lucide-layout-dashboard class="w-4 h-4 text-zinc-500 shrink-0" />
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Dashboard</span>
                        </div>
                        <x-lucide-chevron-down 
                            x-show="sidebarOpen" 
                            class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" 
                            x-bind:class="dashboardOpen ? 'rotate-180' : ''" />
                    </button>

                    <!-- Expanded Sub-menu when Sidebar is Open -->
                    <div 
                        x-show="dashboardOpen && sidebarOpen" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="mt-1 pl-6 space-y-1 text-xs">
                        <a 
                            href="/" 
                            title="Centro de Control Operativo" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('/') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-activity class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Centro de Control</span>
                        </a>
                        <a 
                            href="/analytics" 
                            title="Analytics Dashboard" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('analytics*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-bar-chart-3 class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Analytics</span>
                        </a>
                    </div>

                    <!-- Floating Popover Sub-menu when Sidebar is Collapsed -->
                    <div 
                        x-show="dashboardOpen && !sidebarOpen" 
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 -translate-x-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-x-0"
                        class="absolute left-14 top-0 z-50 bg-white shadow-xl border border-stone-200 rounded-xl p-1.5 min-w-[180px] space-y-1 text-xs">
                        <div class="px-2 py-1 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">Dashboard</div>
                        <a 
                            href="/" 
                            title="Centro de Control Operativo" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('/') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-activity class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Centro de Control</span>
                        </a>
                        <a 
                            href="/analytics" 
                            title="Analytics Dashboard" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('analytics*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-bar-chart-3 class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Analytics</span>
                        </a>
                    </div>
                </div>

                <a href="/kanban" title="Kanban Board" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('kanban*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-kanban class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Kanban Board</span>
                </a>
                <a href="/planner" title="Planificador Semanal" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('planner*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-calendar-days class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Planificador Semanal</span>
                </a>
                <a href="/tasks" title="Tareas Vinculadas" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('tasks*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-check-square class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Tareas Vinculadas</span>
                </a>
                <a href="/resolver" title="Vista Resolver" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('resolver*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-alert-triangle class="w-4 h-4 text-orange-600 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Vista Resolver</span>
                </a>
                <a href="/trash" title="Papelera de Reciclaje" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('trash*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-trash-2 class="w-4 h-4 text-red-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Papelera</span>
                </a>

                <!-- Team Designers List -->
                <div class="pt-3 border-t border-[#e9e9e7] space-y-1.5">
                    <span x-show="sidebarOpen" x-transition.opacity class="text-[10px] uppercase font-semibold text-zinc-400 tracking-wider block px-2">Diseñadores</span>
                    <div class="space-y-1.5 text-[11px] text-zinc-600 font-medium px-2">
                        <div class="flex items-center gap-2" title="Euralíz (Magenta)">
                            <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-500 shrink-0 ring-2 ring-fuchsia-100"></span>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Euralíz</span>
                        </div>
                        <div class="flex items-center gap-2" title="César (Cyan)">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 shrink-0 ring-2 ring-cyan-100"></span>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">César</span>
                        </div>
                        <div class="flex items-center gap-2" title="Adrián (Verde)">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0 ring-2 ring-emerald-100"></span>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Adrián</span>
                        </div>
                        <div class="flex items-center gap-2" title="Diseñador Externo (Amarillo)">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shrink-0 ring-2 ring-amber-100"></span>
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Externo</span>
                        </div>
                    </div>
                </div>

                <!-- Single Configuración Item with Context Dropdown Menu -->
                <div class="pt-3 border-t border-[#e9e9e7] relative" @click.outside="if (!sidebarOpen) configOpen = false">
                    <button 
                        @click="configOpen = !configOpen" 
                        title="Configuración" 
                        class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center justify-between transition cursor-pointer text-xs {{ (request()->is('settings*') || request()->is('trello-sync*')) ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <x-lucide-settings class="w-4 h-4 text-zinc-500 shrink-0" />
                            <span x-show="sidebarOpen" x-transition.opacity class="truncate">Configuración</span>
                        </div>
                        <x-lucide-chevron-down 
                            x-show="sidebarOpen" 
                            class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" 
                            x-bind:class="configOpen ? 'rotate-180' : ''" />
                    </button>

                    <!-- Expanded Sub-menu when Sidebar is Open -->
                    <div 
                        x-show="configOpen && sidebarOpen" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="mt-1 pl-6 space-y-1 text-xs">
                        <a 
                            href="/settings/language" 
                            title="Idioma / Language" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/language*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Idioma') }}</span>
                        </a>
                        <a 
                            href="/settings/substatuses" 
                            title="Configuración de Subestatus" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/substatuses*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-tags class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Subestatus') }}</span>
                        </a>
                        <a 
                            href="/settings/subtasks" 
                            title="Plantillas de Subtareas" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('settings/subtasks*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-list-checks class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Plantillas Subtareas') }}</span>
                        </a>
                        <a 
                            href="/trello-sync" 
                            title="Sincronización Trello" 
                            class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2 transition {{ request()->is('trello-sync*') ? 'bg-[#e2e2e0] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                            <x-lucide-refresh-cw class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Sincronización Trello') }}</span>
                        </a>
                    </div>

                    <!-- Floating Popover Context Menu when Sidebar is Collapsed -->
                    <div 
                        x-show="configOpen && !sidebarOpen" 
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 -translate-x-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-x-0"
                        class="absolute left-14 bottom-0 z-50 bg-white shadow-xl border border-stone-200 rounded-xl p-1.5 min-w-[170px] space-y-1 text-xs">
                        <div class="px-2 py-1 border-b border-stone-100 font-bold text-[10px] uppercase text-zinc-400">{{ __('Configuración') }}</div>
                        <a 
                            href="/settings/language" 
                            title="Idioma / Language" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/language*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-languages class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Idioma') }}</span>
                        </a>
                        <a 
                            href="/settings/substatuses" 
                            title="Configuración de Subestatus" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/substatuses*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-tags class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">{{ __('Subestatus') }}</span>
                        </a>
                        <a 
                            href="/settings/subtasks" 
                            title="Plantillas de Subtareas" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('settings/subtasks*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-list-checks class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Plantillas Subtareas</span>
                        </a>
                        <a 
                            href="/trello-sync" 
                            title="Sincronización Trello" 
                            class="w-full px-2.5 py-1.5 rounded-lg font-medium flex items-center gap-2 transition {{ request()->is('trello-sync*') ? 'bg-stone-100 text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-stone-50 hover:text-zinc-900' }}">
                            <x-lucide-refresh-cw class="w-3.5 h-3.5 text-zinc-500 shrink-0" />
                            <span class="truncate">Sincronización Trello</span>
                        </a>
                    </div>
                </div>

                <!-- Backlog Link Below Configuración -->
                <a href="/backlog" title="Backlog de Órdenes" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('backlog*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-box class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Backlog</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-3 border-t border-[#e9e9e7] bg-[#f7f7f5] text-[11px] text-zinc-500 flex items-center justify-between">
            <span x-show="sidebarOpen" x-transition.opacity class="flex items-center gap-1">
                <x-lucide-info class="w-3.5 h-3.5 text-zinc-400 shrink-0" /> Notion Light
            </span>
            <span x-show="sidebarOpen" x-transition.opacity class="font-mono text-[10px] text-zinc-400">v2.0</span>
        </div>

    </aside>

    <!-- Main Content Container (Dynamic Padding for Collapsible Sidebar) -->
    <div 
        :class="sidebarOpen ? 'pl-64' : 'pl-16'"
        class="flex-1 h-screen max-h-screen flex flex-col w-full bg-[#fbfbfa] transition-all duration-200 ease-in-out overflow-hidden">
        
        <!-- Top Utility Bar -->
        <header class="h-11 border-b border-[#e9e9e7] bg-[#fbfbfa] px-6 flex items-center justify-between text-xs text-zinc-500 sticky top-0 z-30 backdrop-blur-xs shrink-0">
            <div class="flex items-center gap-2.5">
                <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-[#efefed] text-zinc-500 hover:text-zinc-900 transition" title="Toggle Sidebar">
                    <x-lucide-panel-left class="w-4 h-4" />
                </button>
                <span class="font-medium text-zinc-700 flex items-center gap-1.5">
                    <img src="{{ asset('favicon.png') }}" alt="Kudos" class="w-4 h-4 rounded-xs shrink-0">
                    Kudos Design Ops
                </span>
                <span>/</span>
                <span class="text-zinc-500 font-normal">{{ $title ?? 'Dashboard' }}</span>
            </div>

            @php
                $totalWorkspaceOrdersCount = \App\Models\Order::inWorkspace()->count();
                $overdueCount = \App\Models\Order::inWorkspace()->where('substatus', \App\Enums\Substatus::OVERDUE)->count();
            @endphp
            <div class="flex items-center gap-3">
                <span class="text-zinc-500 font-mono text-[11px]">Órdenes Activas: <strong>{{ $totalWorkspaceOrdersCount }}</strong></span>
                @if($overdueCount > 0)
                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-semibold text-[10px]">
                        {{ $overdueCount }} Atrasadas
                    </span>
                @endif
            </div>
        </header>

        <!-- Main Slot -->
        <main class="flex-1 w-full px-6 py-4 flex flex-col min-h-0 overflow-hidden">
            {{ $slot }}
        </main>
    </div>

    <!-- Global Order Detail Side Flyout Drawer Component -->
    <livewire:orders.order-detail-modal />

    @livewireScripts
    @fluxScripts
</body>
</html>
