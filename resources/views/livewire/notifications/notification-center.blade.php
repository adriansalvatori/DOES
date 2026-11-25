<div 
    class="relative" 
    x-data="{ 
        open: false,
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
                        title: 'Kudos DOES · ' + @js(__('Test notification')),
                        body: @js(__('Sound and system notifications are on.')),
                    });
                }
            }
        }
    }" 
    @click.outside="open = false" 
    wire:poll.30s.visible>
    
    <!-- Bell trigger -->
    <button
        @click="open = !open"
        type="button"
        class="ui-btn ui-btn-ghost ui-btn-icon relative"
        :aria-expanded="open.toString()"
        aria-label="{{ __('Notification center') }}"
        title="{{ __('Notification center') }}">
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
            @endphp
            <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full ring-2 ring-surface {{ $hasUnreadAssigned ? 'bg-danger' : 'bg-ink-3' }}"></span>
        @endif
    </button>

    <!-- Dropdown -->
    <div
        x-show="open"
        x-cloak
        style="display: none;"
        class="absolute right-0 mt-2 w-80 sm:w-96 bg-surface border border-line rounded-card shadow-menu z-50 overflow-hidden">

        <div class="px-4 py-3 flex items-center justify-between border-b border-line">
            <div class="flex items-center gap-2">
                <h3 class="text-row font-semibold text-ink">{{ __('Notifications') }}</h3>
                @if($unreadCount > 0)
                    <span class="text-meta text-ink-3">{{ trans_choice('{1} :count new|[2,*] :count new', $unreadCount) }}</span>
                @endif
            </div>

            @if($unreadCount > 0)
                <button wire:click="markAllAsRead" type="button" class="ui-btn ui-btn-ghost ui-btn-sm">
                    {{ __('Mark as read') }}
                </button>
            @endif
        </div>

        <!-- Permission prompt (only when the browser has not been asked yet) -->
        <div x-show="permission === 'default'" x-cloak class="px-4 py-3 flex items-center justify-between gap-3 border-b border-line bg-sunken">
            <div class="min-w-0">
                <p class="text-body font-medium text-ink">{{ __('Turn on alerts on this device?') }}</p>
                <p class="text-meta text-ink-3 truncate">{{ __('Get sound alerts for your orders') }}</p>
            </div>
            <button @click="requestPerm()" type="button" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">{{ __('Turn on') }}</button>
        </div>

        <!-- Items -->
        <div class="max-h-96 overflow-y-auto divide-y divide-line">
            @php
                $currentUser = auth()->user();
                $userDesignerId = $currentUser?->designer?->id;
                $hasUnread = $notifications->contains(fn($item) => $item->unread());
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
                    $detailText = $n->data['detail_text'] ?? null;
                    $actorName = $n->data['actor_name'] ?? null;
                    $nDesignerId = $n->data['designer_id'] ?? null;
                    $nDesignerName = $n->data['designer_name'] ?? null;
                    $isAssignedToMe = $userDesignerId && $nDesignerId && (int) $userDesignerId === (int) $nDesignerId;

                    if ($companyName && $taskName && trim(strtolower($companyName)) === trim(strtolower($taskName))) {
                        $headline = $taskName;
                    } else {
                        $headline = implode(' · ', array_filter([$companyName, $taskName])) ?: __('General');
                    }

                    $labelTone = match(strtolower($label)) {
                        'urgent', 'overdue', 'blocked' => 'danger',
                        'due today', 'status', 'unblocked' => 'warn',
                        'approved' => 'ok',
                        default => 'neutral',
                    };
                @endphp

                @if($isRead && $hasUnread && !$firstReadRendered)
                    @php $firstReadRendered = true; @endphp
                    <div class="px-4 py-1.5 bg-sunken text-meta font-medium text-ink-3 select-none">{{ __('Read') }}</div>
                @endif

                <div wire:click="markAsRead('{{ $n->id }}')" class="px-4 py-3 flex items-start gap-3 cursor-pointer hover:bg-sunken transition">
                    <span class="mt-1.5 h-2 w-2 rounded-full shrink-0 {{ $isRead ? 'bg-transparent' : ($isAssignedToMe || $isUrgent ? 'bg-danger' : 'bg-ink-3') }}"
                          @if(! $isRead) title="{{ $isAssignedToMe ? __('Order assigned to you') : __('Order not assigned to you') }}" @endif></span>

                    <div class="flex-1 min-w-0 {{ $isRead ? 'ui-done' : '' }}">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-ui.tag :tone="$isRead ? 'neutral' : $labelTone">{{ __($label) }}</x-ui.tag>
                            @if($nDesignerName && ! $isAssignedToMe)
                                <span class="text-meta text-ink-3 truncate">{{ $nDesignerName }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-body truncate {{ $isRead ? '' : 'font-medium text-ink' }}">{{ $headline }}</p>
                        @if($detailText)
                            <p class="text-meta text-ink-3 mt-0.5">{{ $detailText }}</p>
                        @endif
                        <p class="text-meta text-ink-3 mt-1">
                            {{ $n->created_at->diffForHumans() }}@if($actorName) · {{ __('by') }} {{ $actorName }}@endif
                        </p>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <x-lucide-bell-off class="w-5 h-5 mx-auto mb-2 text-ink-3" />
                    <p class="text-body text-ink-3">{{ __('You\'re all caught up') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Footer: system notification and sound controls -->
        <div x-show="permission !== 'default'" class="px-4 py-2 border-t border-line flex items-center justify-between text-meta text-ink-3">
            <template x-if="permission === 'granted'">
                <div class="w-full flex items-center justify-between">
                    <span>{{ __('Alerts on') }}</span>
                    <div class="flex items-center gap-1">
                        <button @click="toggleSound()" type="button" class="ui-btn ui-btn-ghost ui-btn-icon ui-btn-sm"
                                :title="soundEnabled ? @js(__('Mute sound')) : @js(__('Turn sound on'))"
                                :aria-label="soundEnabled ? @js(__('Mute sound')) : @js(__('Turn sound on'))">
                            <span x-show="soundEnabled"><x-lucide-volume-2 class="w-4 h-4" /></span>
                            <span x-show="!soundEnabled" x-cloak><x-lucide-volume-x class="w-4 h-4" /></span>
                        </button>
                        <button @click="testNotification()" type="button" class="ui-btn ui-btn-ghost ui-btn-sm"
                                title="{{ __('Play a test sound and show a notification') }}">{{ __('Test') }}</button>
                    </div>
                </div>
            </template>

            <template x-if="permission === 'denied'">
                <div class="w-full flex items-center justify-between gap-2">
                    <span class="truncate">{{ __('Alerts blocked in browser') }}</span>
                    <button @click="testNotification()" type="button" class="ui-btn ui-btn-ghost ui-btn-sm shrink-0">{{ __('Test sound') }}</button>
                </div>
            </template>

            <template x-if="permission === 'unsupported'">
                <div class="w-full flex items-center justify-between gap-2">
                    <span>{{ __('Alerts not supported') }}</span>
                    <button @click="testNotification()" type="button" class="ui-btn ui-btn-ghost ui-btn-sm shrink-0">{{ __('Test sound') }}</button>
                </div>
            </template>
        </div>
    </div>
</div>
