<div class="relative" x-data="{ open: false }" @click.outside="open = false" wire:poll.20s>
    
    <!-- Bell Icon Trigger Button -->
    <button 
        @click="open = !open" 
        type="button" 
        class="relative p-1.5 rounded-lg text-zinc-500 hover:text-zinc-800 hover:bg-[#efefed] transition cursor-pointer"
        title="{{ __('Centro de Notificaciones') }}">
        <x-lucide-bell class="w-4 h-4" />

        @if($unreadCount > 0)
            <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
            </span>
        @endif
    </button>

    <!-- Notification Dropdown Popover -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
        style="display: none;"
        class="absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-stone-200/90 rounded-2xl shadow-xl z-50 overflow-hidden divide-y divide-stone-100">
        
        <!-- Header -->
        <div class="p-3 bg-stone-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-xs text-zinc-900">{{ __('Notificaciones') }}</h3>
                @if($unreadCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">
                        {{ $unreadCount }} {{ __('nuevas') }}
                    </span>
                @endif
            </div>

            @if($unreadCount > 0)
                <button 
                    wire:click="markAllAsRead" 
                    type="button" 
                    class="text-[11px] font-medium text-stone-600 hover:text-stone-900 transition">
                    {{ __('Marcar leídas') }}
                </button>
            @endif
        </div>

        <!-- Notification Items List -->
        <div class="max-h-80 overflow-y-auto divide-y divide-stone-100 text-xs">
            @forelse($notifications as $n)
                <div 
                    wire:click="markAsRead('{{ $n->id }}')" 
                    class="p-3 transition cursor-pointer flex items-start gap-2.5 {{ $n->unread() ? 'bg-amber-50/40 hover:bg-amber-50/80 font-medium' : 'hover:bg-stone-50 text-zinc-600' }}">
                    
                    <div class="shrink-0 mt-0.5">
                        @if($n->unread())
                            <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                        @else
                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-zinc-400" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-zinc-800 leading-snug">
                            {{ $n->data['message'] ?? ($n->data['title'] ?? __('Nueva notificación')) }}
                        </p>
                        @if(isset($n->data['task_name']))
                            <p class="text-[11px] text-zinc-500 truncate font-mono mt-0.5">
                                {{ $n->data['task_name'] }}
                            </p>
                        @endif
                        <span class="text-[10px] text-zinc-400 block mt-1">
                            {{ $n->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-zinc-400">
                    <x-lucide-bell-off class="w-6 h-6 mx-auto mb-2 text-zinc-300" />
                    <p class="text-xs">{{ __('No tienes notificaciones pendientes') }}</p>
                </div>
            @endforelse
        </div>

    </div>

</div>
