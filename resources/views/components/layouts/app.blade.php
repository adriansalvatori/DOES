<!DOCTYPE html>
<html lang="es" class="h-full bg-[#fbfbfa] text-zinc-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Kudos Design Ops - Trello Workflow Manager' }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #fbfbfa;
            color: #252525;
        }
        code, pre, .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body 
    x-data="{ sidebarOpen: localStorage.getItem('sidebar_open') !== 'false' }"
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
                    <div class="w-7 h-7 rounded-md bg-stone-200 border border-stone-300 flex items-center justify-center text-zinc-700 font-bold text-xs shrink-0 shadow-xs">
                        <x-lucide-layers class="w-4 h-4 text-zinc-700" />
                    </div>
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

            <!-- Navigation Links (Notion Sidebar Item Style) -->
            <nav class="space-y-1 text-xs">
                <a href="/" title="Dashboard Operativo" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('/') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-layout-dashboard class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Dashboard</span>
                </a>
                <a href="/backlog" title="Backlog de Órdenes" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('backlog*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-box class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Backlog (Órdenes)</span>
                </a>
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
                    <x-lucide-alert-triangle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Vista Resolver</span>
                </a>
                <a href="/trello-sync" title="Sincronización Trello" class="w-full px-2.5 py-1.5 rounded-md font-medium flex items-center gap-2.5 transition {{ request()->is('trello-sync*') ? 'bg-[#ebebeb] text-zinc-900 font-semibold' : 'text-zinc-600 hover:bg-[#efefed] hover:text-zinc-900' }}">
                    <x-lucide-refresh-cw class="w-4 h-4 text-zinc-500 shrink-0" />
                    <span x-show="sidebarOpen" x-transition.opacity class="truncate">Sincronización Trello</span>
                </a>
            </nav>

            <!-- Team Designers List -->
            <div class="pt-3 border-t border-[#e9e9e7] space-y-1.5">
                <span x-show="sidebarOpen" x-transition.opacity class="text-[10px] uppercase font-semibold text-zinc-400 tracking-wider block px-2">Diseñadores</span>
                <div class="space-y-1 text-[11px] text-zinc-600 font-medium px-2">
                    <div class="flex items-center gap-2" title="Euralíz">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 shrink-0"></span>
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">Euralíz</span>
                    </div>
                    <div class="flex items-center gap-2" title="Adrián">
                        <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">Adrián</span>
                    </div>
                    <div class="flex items-center gap-2" title="César">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <span x-show="sidebarOpen" x-transition.opacity class="truncate">César</span>
                    </div>
                </div>
            </div>
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
        class="flex-1 min-h-screen flex flex-col w-full bg-[#fbfbfa] transition-all duration-200 ease-in-out">
        
        <!-- Top Utility Bar -->
        <header class="h-11 border-b border-[#e9e9e7] bg-[#fbfbfa] px-6 flex items-center justify-between text-xs text-zinc-500 sticky top-0 z-30 backdrop-blur-xs">
            <div class="flex items-center gap-2.5">
                <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-[#efefed] text-zinc-500 hover:text-zinc-900 transition" title="Toggle Sidebar">
                    <x-lucide-panel-left class="w-4 h-4" />
                </button>
                <span class="font-medium text-zinc-700">Kudos Design Ops</span>
                <span>/</span>
                <span class="text-zinc-500 font-normal">{{ $title ?? 'Dashboard' }}</span>
            </div>

            @php
                $totalOrdersCount = \App\Models\Order::count();
                $overdueCount = \App\Models\Order::where('substatus', \App\Enums\Substatus::OVERDUE)->count();
            @endphp
            <div class="flex items-center gap-3">
                <span class="text-zinc-500 font-mono text-[11px]">Tarjetas: <strong>{{ $totalOrdersCount }}</strong></span>
                @if($overdueCount > 0)
                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-semibold text-[10px]">
                        {{ $overdueCount }} Atrasadas
                    </span>
                @endif
            </div>
        </header>

        <!-- Main Slot -->
        <main class="flex-1 w-full px-6 py-5">
            {{ $slot }}
        </main>
    </div>

    <!-- Global Order Detail Side Flyout Drawer Component -->
    <livewire:orders.order-detail-modal />

    @livewireScripts
    @fluxScripts
</body>
</html>
