<div class="h-full w-full max-w-4xl flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10">
    
    <x-ui.page-header :title="__('Notification settings (admin)')" :subtitle="__('Manage the 11 global alert events. Users receive every active notification and can\'t turn them off.')">
        <x-slot:actions>
            <flux:button wire:click="enableAll" type="button" variant="outline" size="sm" icon="check">{{ __('Enable all') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>
    <p class="text-meta text-ink-3 -mt-3 shrink-0">{{ __('Changes are saved instantly.') }}</p>

    @if(session('success'))
        <div class="p-3 bg-ok-soft text-ok border border-line rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $items = [
            'new_order' => [
                'title' => '1. New order',
                'desc' => 'Fires when an order is created manually or imported from Trello.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_overdue' => [
                'title' => '2. Overdue order',
                'desc' => 'Fires when the due date passes. It is pinned to the top of the notification center.',
                'badge' => 'Urgent',
                'tone' => 'danger',
            ],
            'status_changed' => [
                'title' => '3. Core status change',
                'desc' => 'Fires when the order moves to another column or core status.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_blocked' => [
                'title' => '4. Order blocked',
                'desc' => 'Fires when an order becomes blocked or needs information.',
                'badge' => 'High',
                'tone' => 'warn',
            ],
            'order_unblocked' => [
                'title' => '5. Order unblocked',
                'desc' => 'Fires when an order block is resolved and it returns to the flow.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_approved_alta' => [
                'title' => '6. Order approved, pending ALTA',
                'desc' => 'Fires when the client or review approves the order for production.',
                'badge' => 'High',
                'tone' => 'neutral',
            ],
            'new_attachments' => [
                'title' => '7. New attachments on an order',
                'desc' => 'Fires when images, PDFs or other files are uploaded to the order.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'new_comment' => [
                'title' => '8. New comment on an order',
                'desc' => 'Fires when a user posts an internal note or comment.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_due_today' => [
                'title' => '9. Order due TODAY',
                'desc' => 'Fires automatically when the agreed due date is today.',
                'badge' => 'High',
                'tone' => 'warn',
            ],
            'order_urgent' => [
                'title' => '10. Order marked URGENT',
                'desc' => 'Fires when an order is flagged as URGENT. It is pinned to the top.',
                'badge' => 'Urgent',
                'tone' => 'danger',
            ],
            'overdue_email_sent' => [
                'title' => '11. Send overdue email',
                'desc' => 'Internal notice when the platform sends the overdue email to the client.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'welcome_email_sent' => [
                'title' => '12. Send welcome email',
                'desc' => 'Internal notice when the initial welcome email for an order is sent.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
        ];
    @endphp

    <div class="bg-surface border border-line rounded-card px-4">
        <div class="divide-y divide-line">
            @foreach($items as $key => $item)
                @php $on = (bool) ($settings[$key] ?? true); @endphp
                <div class="py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-body font-medium text-ink">{{ __($item['title']) }}</h3>
                            <x-ui.tag :tone="$item['tone']">{{ __($item['badge']) }}</x-ui.tag>
                        </div>
                        <p class="text-meta text-ink-3 mt-0.5">{{ __($item['desc']) }}</p>
                    </div>
                    <button
                        wire:click="toggleSetting('{{ $key }}')"
                        type="button"
                        role="switch"
                        aria-checked="{{ $on ? 'true' : 'false' }}"
                        aria-label="{{ __($item['title']) }}"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-gold {{ $on ? 'bg-ink' : 'bg-line-strong' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $on ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

</div>
