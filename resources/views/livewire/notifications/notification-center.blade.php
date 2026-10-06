<div class="relative" x-data="{ open: false }" @click.outside="open = false" wire:poll.30s>
    
    <!-- Bell Icon Trigger Button -->
    <button 
        @click="open = !open" 
        type="button" 
        class="relative p-1.5 rounded-lg text-zinc-500 hover:text-zinc-800 hover:bg-[#efefed] transition cursor-pointer"
        title="{{ __('Centro de Notificaciones') }}">
        <x-lucide-bell class="w-4 h-4" />

        @if($unreadCount > 0)
            @php
                $currentUser = auth()->user();
                $userDesignerId = $currentUser?->designer?->id;
                $hasUnreadAssigned = false;
                foreach($notifications as $item) {
                    if ($item->unread()) {
                        $itemDesignerId = $item->data['designer_id'] ?? null;
                        if ($userDesignerId && $itemDesignerId && (int)$userDesignerId === (int)$itemDesignerId) {
                            $hasUnreadAssigned = true;
                            break;
                        }
                    }
                }
                $dotColor = $hasUnreadAssigned ? 'bg-rose-500' : 'bg-emerald-500';
                $pingColor = $hasUnreadAssigned ? 'bg-rose-400' : 'bg-emerald-400';
            @endphp
            <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $pingColor }} opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $dotColor }}"></span>
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
        <div class="px-3.5 py-2.5 bg-stone-50/80 flex items-center justify-between">
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
                    class="text-[11px] font-medium text-stone-600 hover:text-stone-900 transition cursor-pointer">
                    {{ __('Marcar leídas') }}
                </button>
            @endif
        </div>

        <!-- Notification Items List -->
        <div class="max-h-80 overflow-y-auto divide-y divide-stone-100 text-xs">
            @php
                $currentUser = auth()->user();
                $userDesignerId = $currentUser?->designer?->id;
                $hasUnread = $notifications->contains(fn($item) => $item->unread());
                $hasRead = $notifications->contains(fn($item) => !$item->unread());
                $firstReadRendered = false;
            @endphp

            @forelse($notifications as $n)
                @php
                    $isRead = ! $n->unread();
                    $isUrgent = !empty($n->data['is_urgent']) || in_array($n->data['event_type'] ?? '', ['order_overdue', 'order_urgent'], true);
                    $label = $n->data['label'] ?? null;
                    if (!$label) {
                        $label = match($n->data['event_type'] ?? '') {
                            'new_order' => 'New Order',
                            'order_overdue' => 'Overdue',
                            'status_changed' => 'Status',
                            'order_blocked' => 'Blocked',
                            'order_unblocked' => 'Unblocked',
                            'order_approved_alta' => 'Approved',
                            'new_attachments' => 'New File',
                            'new_comment' => 'New Comment',
                            'order_due_today' => 'Due Today',
                            'order_urgent' => 'Urgent',
                            'welcome_email_sent', 'overdue_email_sent' => 'Email Sent',
                            default => $n->data['title'] ?? 'Notification',
                        };
                    }

                    $companyName = $n->data['company_name'] ?? null;
                    $taskName = $n->data['task_name'] ?? null;
                    $orderId = $n->data['order_id'] ?? null;
                    $detailText = $n->data['detail_text'] ?? null;
                    $actorName = $n->data['actor_name'] ?? null;
                    $nDesignerId = $n->data['designer_id'] ?? null;
                    $nDesignerName = $n->data['designer_name'] ?? null;

                    $isAssignedToMe = $userDesignerId && $nDesignerId && (int) $userDesignerId === (int) $nDesignerId;
                    $isUnassignedToMe = ! $isAssignedToMe;

                    if ($companyName && $taskName && trim(strtolower($companyName)) === trim(strtolower($taskName))) {
                        $headlineParts = [$taskName];
                    } else {
                        $headlineParts = array_filter([$companyName, $taskName]);
                    }
                    $headline = implode(' • ', $headlineParts);
                    if (empty($headline)) {
                        $headline = __('General');
                    }

                    if ($isRead) {
                        $labelBadgeClass = 'bg-stone-100 text-stone-400 border-stone-200/60 font-medium';
                        $containerClass = 'opacity-40 hover:opacity-85 bg-stone-50/20 text-zinc-500';
                    } else {
                        $labelBadgeClass = match(strtolower($label)) {
                            'urgent', 'overdue', 'blocked' => 'bg-red-100 text-red-700 border-red-200',
                            'new order', 'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'new file', 'new comment' => 'bg-sky-100 text-sky-800 border-sky-200',
                            'status', 'unblocked' => 'bg-amber-100 text-amber-800 border-amber-200',
                            'due today' => 'bg-orange-100 text-orange-800 border-orange-200',
                            default => 'bg-stone-100 text-stone-700 border-stone-200',
                        };
                        $containerClass = $isUrgent 
                            ? 'bg-red-50/60 hover:bg-red-50 border-l-2 border-l-red-500 font-medium opacity-100' 
                            : 'bg-amber-50/30 hover:bg-amber-50/60 opacity-100 text-zinc-800 font-medium';
                    }
                @endphp

                @if($isRead && $hasUnread && !$firstReadRendered)
                    @php $firstReadRendered = true; @endphp
                    <div class="px-3.5 py-1 bg-stone-100/80 border-y border-stone-200/60 flex items-center gap-2 text-[10px] font-bold text-stone-400 uppercase tracking-wider select-none">
                        <span>{{ __('Leídas') }}</span>
                        <div class="h-px bg-stone-200/80 flex-1"></div>
                    </div>
                @endif

                <div 
                    wire:click="markAsRead('{{ $n->id }}')" 
                    class="px-3.5 py-2.5 transition duration-150 cursor-pointer flex items-start gap-2.5 {{ $containerClass }}">
                    
                    <div class="shrink-0 mt-0.5">
                        @if($isUrgent && ! $isRead)
                            <x-lucide-alert-triangle class="w-3.5 h-3.5 text-red-600 animate-pulse" />
                        @elseif($n->unread())
                            @if($isUnassignedToMe)
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block shadow-sm" title="{{ __('Orden no asignada a ti') }}"></span>
                            @else
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block shadow-sm" title="{{ __('Orden asignada a ti') }}"></span>
                            @endif
                        @else
                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-zinc-300" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        @if($isUnassignedToMe)
                            <!-- Compact Layout for unassigned cards (no bold text) -->
                            <div class="flex items-center gap-1.5 mb-1 min-w-0 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border leading-none shrink-0 {{ $nDesignerName ? 'bg-cyan-100 text-cyan-800 border-cyan-200' : 'bg-stone-100 text-stone-600 border-stone-200' }}">
                                    {{ $nDesignerName ?: __('Sin asignar') }}
                                </span>
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-medium border leading-none shrink-0 {{ $labelBadgeClass }}">
                                    {{ __($label) }}
                                </span>
                                <span class="text-xs {{ $isRead ? 'font-normal text-zinc-500' : 'font-normal text-zinc-700' }} truncate min-w-0">
                                    {{ $headline }}
                                </span>
                            </div>

                            @if($detailText)
                                <p class="text-[11px] {{ $isRead ? 'text-zinc-400' : 'text-zinc-500' }} leading-snug mb-1 font-normal">
                                    {{ $detailText }}
                                </p>
                            @endif

                            <div class="flex items-center justify-between text-[10px] {{ $isRead ? 'text-zinc-300' : 'text-zinc-400' }} mt-1 pt-1 border-t border-stone-100/60">
                                <span>{{ $n->created_at->diffForHumans() }}</span>
                                @if($actorName)
                                    <span class="font-normal {{ $isRead ? 'text-zinc-400' : 'text-zinc-500' }}">{{ __('por') }} {{ $actorName }}</span>
                                @endif
                            </div>
                        @else
                            <!-- Standard Layout for assigned cards -->
                            <div class="flex items-center gap-2 mb-1 min-w-0">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border leading-none shrink-0 {{ $labelBadgeClass }}">
                                    {{ __($label) }}
                                </span>
                                @if($companyName)
                                    <span class="text-[11px] {{ $isRead ? 'font-medium text-zinc-500' : 'font-bold text-zinc-700' }} truncate">
                                        {{ $companyName }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs {{ $isRead ? 'font-medium text-zinc-600' : 'font-semibold text-zinc-900' }} leading-snug truncate">
                                {{ $taskName ?: ($companyName ?: __('Orden')) }}
                            </p>

                            @if($detailText)
                                <p class="text-[11px] {{ $isRead ? 'text-zinc-400' : 'text-zinc-500' }} leading-snug mt-0.5 font-normal">
                                    {{ $detailText }}
                                </p>
                            @endif

                            <div class="flex items-center justify-between text-[10px] {{ $isRead ? 'text-zinc-300' : 'text-zinc-400' }} mt-1.5 pt-1 border-t border-stone-100/60">
                                <span>{{ $n->created_at->diffForHumans() }}</span>
                                @if($actorName)
                                    <span class="font-medium {{ $isRead ? 'text-zinc-400' : 'text-zinc-500' }}">{{ __('por') }} {{ $actorName }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-zinc-400">
                    <x-lucide-bell-off class="w-5 h-5 mx-auto mb-1.5 text-zinc-300" />
                    <p class="text-xs">{{ __('No tienes notificaciones pendientes') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Footer: System Notification & Sound Controls -->
        <div 
            x-data="{
                permission: (window.KudosNotifier ? window.KudosNotifier.getPermissionState() : ('Notification' in window ? Notification.permission : 'unsupported')),
                soundEnabled: (window.KudosNotifier ? window.KudosNotifier.getSoundEnabled() : (localStorage.getItem('kudos_sound_enabled') !== 'false')),
                init() {
                    window.addEventListener('kudos-notification-permission-changed', (e) => {
                        this.permission = e.detail.permission;
                    });
                    window.addEventListener('kudos-sound-preference-changed', (e) => {
                        this.soundEnabled = e.detail.enabled;
                    });
                },
                requestPerm() {
                    if (window.KudosNotifier) {
                        window.KudosNotifier.requestPermission().then(p => { this.permission = p; });
                    } else if ('Notification' in window) {
                        Notification.requestPermission().then(p => { this.permission = p; });
                    }
                },
                toggleSound() {
                    this.soundEnabled = !this.soundEnabled;
                    if (window.KudosNotifier) {
                        window.KudosNotifier.setSoundEnabled(this.soundEnabled);
                    }
                },
                testNotification() {
                    if (window.KudosNotifier) {
                        window.KudosNotifier.playChime();
                        if (this.permission === 'granted') {
                            window.KudosNotifier.showSystemNotification({
                                title: '🔔 Kudos DOES (Prueba)',
                                body: '{{ __('¡El sonido y las notificaciones del sistema están activos!') }}',
                            });
                        }
                    }
                }
            }"
            class="px-3.5 py-2 bg-stone-50/90 border-t border-stone-100 flex items-center justify-between text-[11px] text-zinc-500">
            
            <template x-if="permission === 'default'">
                <button 
                    @click="requestPerm()" 
                    type="button" 
                    class="w-full flex items-center justify-center gap-1.5 py-1 px-2.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-zinc-800 font-medium transition cursor-pointer text-[11px]">
                    <x-lucide-bell-ring class="w-3.5 h-3.5 text-amber-500" />
                    <span>{{ __('Activar avisos de escritorio y sonido') }}</span>
                </button>
            </template>

            <template x-if="permission === 'granted'">
                <div class="w-full flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-emerald-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block shadow-2xs"></span>
                        <span class="font-medium text-[10px] text-zinc-700">{{ __('Avisos activos') }}</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button 
                            @click="toggleSound()" 
                            type="button" 
                            class="p-1 rounded hover:bg-stone-200/70 text-zinc-600 transition cursor-pointer"
                            :title="soundEnabled ? '{{ __('Silenciar sonido') }}' : '{{ __('Activar sonido') }}'">
                            <span x-show="soundEnabled">
                                <x-lucide-volume-2 class="w-3.5 h-3.5 text-zinc-600" />
                            </span>
                            <span x-show="!soundEnabled" x-cloak>
                                <x-lucide-volume-x class="w-3.5 h-3.5 text-zinc-400" />
                            </span>
                        </button>

                        <button 
                            @click="testNotification()" 
                            type="button" 
                            class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-200/80 hover:bg-stone-200 text-zinc-700 transition cursor-pointer"
                            title="{{ __('Reproducir sonido de prueba y mostrar notificación') }}">
                            {{ __('Probar') }}
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="permission === 'denied'">
                <div class="w-full flex items-center justify-between gap-1 text-[10px] text-amber-800">
                    <div class="flex items-center gap-1 min-w-0">
                        <x-lucide-bell-off class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <span class="truncate">{{ __('Avisos bloqueados en navegador') }}</span>
                    </div>
                    <button 
                        @click="testNotification()" 
                        type="button" 
                        class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-200 hover:bg-stone-300 text-zinc-700 transition cursor-pointer shrink-0">
                        {{ __('Probar sonido') }}
                    </button>
                </div>
            </template>

            <template x-if="permission === 'unsupported'">
                <div class="w-full flex items-center justify-between gap-1 text-[10px] text-zinc-400">
                    <span>{{ __('Avisos no soportados') }}</span>
                    <button 
                        @click="testNotification()" 
                        type="button" 
                        class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-200 hover:bg-stone-300 text-zinc-700 transition cursor-pointer shrink-0">
                        {{ __('Probar sonido') }}
                    </button>
                </div>
            </template>
        </div>

    </div>

</div>
