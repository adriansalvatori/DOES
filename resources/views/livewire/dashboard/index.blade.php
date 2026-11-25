@php
    $isDesigner = (bool) auth()->user()?->isDesigner();
    $locale = app()->getLocale();
    $dateLabel = now()->locale($locale)->translatedFormat($locale === 'es' ? 'l j \d\e F' : 'l, F j');

    $overdueTag = function ($order) {
        $days = $order->current_due_date ? (int) abs(now()->startOfDay()->diffInDays($order->current_due_date->copy()->startOfDay(), false)) : 0;

        return ['tone' => 'danger', 'icon' => 'alert-circle', 'label' => __('Overdue :count d', ['count' => $days])];
    };
    $blockedTag = function ($order) {
        $reason = $order->blocking_reason_other ?: $order->blocking_reason?->value;

        return ['tone' => 'warn', 'icon' => 'lock', 'label' => $reason ? __('Blocked').': '.$reason : __('Blocked')];
    };
    $camilaTag = ['tone' => 'info', 'icon' => 'user-check', 'label' => __('With Camila')];
    $clientTag = ['tone' => 'info', 'icon' => 'mail', 'label' => __('Client not responding')];

    // Needs attention: one row per order (reasons merged), then follow-up tasks.
    $attention = collect();
    foreach ($overdueOrders as $o) {
        $attention[$o->id] = ['order' => $o, 'tags' => [$overdueTag($o)], 'action' => null];
    }
    if (! $isDesigner) {
        foreach ($resolverOrders as $o) {
            $row = $attention[$o->id] ?? ['order' => $o, 'tags' => [], 'action' => null];
            $row['tags'][] = $blockedTag($o);
            $row['action'] = ['label' => __('Unblock'), 'wire' => "openResolveModal({$o->id})"];
            $attention[$o->id] = $row;
        }
    }
    $attentionTasks = collect();
    foreach ($camilaFollowUpTasks as $t) {
        $attentionTasks->push(['task' => $t, 'tags' => [$camilaTag], 'action' => ['label' => __('Review now'), 'wire' => "openCamilaModal({$t->id})"]]);
    }
    foreach ($clientFollowUpTasks as $t) {
        $attentionTasks->push(['task' => $t, 'tags' => [$clientTag], 'action' => ['label' => __('Done'), 'wire' => "completeTask({$t->id})"]]);
    }
    $attentionCount = $attention->count() + $attentionTasks->count();
    $todayCount = $toDoTodayOrders->count() + $toDoTodayTasks->count();

    $segments = [
        ['tab' => 'all', 'id' => null, 'count' => $inProgressCount, 'label' => __('in progress'), 'tone' => null],
        ['tab' => 'overdue', 'id' => 'tour-stats-overdue', 'count' => $overdueOrders->count(), 'label' => trans_choice('{1} overdue|[2,*] overdue', $overdueOrders->count()), 'tone' => 'danger'],
    ];
    if (! $isDesigner) {
        $segments[] = ['tab' => 'resolver', 'id' => 'tour-stats-resolver', 'count' => $resolverOrders->count(), 'label' => trans_choice('{1} blocked|[2,*] blocked', $resolverOrders->count()), 'tone' => 'warn'];
    }
    $segments[] = ['tab' => 'today', 'id' => 'tour-stats-today', 'count' => $todayCount, 'label' => __('for today'), 'tone' => null];
    $segments[] = ['tab' => 'camila', 'id' => null, 'count' => $camilaFollowUpTasks->count(), 'label' => __('with Camila'), 'tone' => null];
    $segments[] = ['tab' => 'client', 'id' => null, 'count' => $clientFollowUpTasks->count(), 'label' => __('waiting on the client'), 'tone' => null];

    // Secondary lists and the single full-width lists, defined once.
    $secondary = [
        'alta' => ['label' => __('Ready ALTA'), 'icon' => 'rocket', 'items' => $readyForAltaOrders, 'empty' => __('No orders ready for ALTA.')],
        'pronostico' => ['label' => __('Forecast'), 'icon' => 'trending-up', 'items' => $pronosticoAltaOrders, 'empty' => __('No orders sent to clients this week in the forecast.')],
        'new_orders' => ['label' => __('New'), 'icon' => 'sparkles', 'items' => $newTrelloOrders, 'empty' => __('No new Trello orders waiting in the Backlog.')],
    ];
@endphp

<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1">

    <x-ui.page-header :title="__('Today')" :subtitle="$dateLabel">
        <x-slot:actions>
            <div id="tour-designer-pills" class="flex items-center gap-1.5 overflow-x-auto max-w-full py-0.5">
                <button type="button" wire:click="selectDesigner('all')" aria-pressed="{{ $selectedDesigner === 'all' ? 'true' : 'false' }}" class="ui-chip shrink-0">
                    {{ __('All') }}
                </button>
                @foreach($designers as $designer)
                    <button type="button" wire:key="designer-filter-{{ $designer->id }}" wire:click="selectDesigner('{{ $designer->id }}')"
                        aria-pressed="{{ (string) $selectedDesigner === (string) $designer->id ? 'true' : 'false' }}"
                        title="{{ $designer->name }}" class="ui-chip shrink-0 !pl-1">
                        <x-ui.person :person="$designer" size="sm" />
                        <span>{{ \Illuminate\Support\Str::before($designer->name, ' ') }}</span>
                    </button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session()->has('message'))
        <div class="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-body text-ink">
            <x-lucide-check-circle-2 class="w-4 h-4 text-ok shrink-0" />
            <span class="truncate">{{ session('message') }}</span>
        </div>
    @endif

    <p id="tour-dashboard-stats" class="text-row text-ink-2 flex flex-wrap items-center gap-x-1.5 gap-y-1">
        @foreach($segments as $i => $seg)
            @if($i > 0)<span class="text-ink-3" aria-hidden="true">·</span>@endif
            @php
                $selected = $activeTab === $seg['tab'];
                $risk = $seg['count'] > 0 ? ($seg['tone'] === 'danger' ? 'text-danger' : ($seg['tone'] === 'warn' ? 'text-warn' : null)) : null;
            @endphp
            <button type="button" @if($seg['id']) id="{{ $seg['id'] }}" @endif wire:click="setActiveTab('{{ $seg['tab'] }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                class="cursor-pointer rounded-control px-1 -mx-0.5 hover:bg-sunken {{ $selected ? 'text-ink font-medium underline underline-offset-4 decoration-2' : ($risk ?? 'text-ink-2') }}">
                <span class="tabular-nums">{{ $seg['count'] }}</span> {{ $seg['label'] }}
            </button>
        @endforeach
    </p>

    @if($activeTab === 'all')
        <section class="space-y-2" aria-labelledby="hoy-attention">
            <h2 id="hoy-attention" class="text-section font-semibold text-ink">{{ __('Needs attention') }} <span class="text-ink-3 font-normal tabular-nums">{{ $attentionCount }}</span></h2>
            @if($attentionCount === 0)
                <x-ui.empty icon="check-circle-2" :title="__('Nothing needs attention right now')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($attention as $r)
                        @include('livewire.dashboard.partials.row', ['order' => $r['order'], 'rowKey' => 'attn-order-'.$r['order']->id, 'tags' => $r['tags'], 'action' => $r['action']])
                    @endforeach
                    @foreach($attentionTasks as $r)
                        @if($r['task']->order)
                            @include('livewire.dashboard.partials.row', ['order' => $r['task']->order, 'rowKey' => 'attn-task-'.$r['task']->id, 'tags' => $r['tags'], 'action' => $r['action'], 'line2' => $r['task']->title, 'person' => $r['task']->assignee ?? $r['task']->order->primary_designer])
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($activeTab === 'all' || $activeTab === 'today')
        <section class="space-y-2" aria-labelledby="hoy-today">
            <h2 id="hoy-today" class="text-section font-semibold text-ink">{{ __('Scheduled today') }} <span class="text-ink-3 font-normal tabular-nums">{{ $todayCount }}</span></h2>
            @if($todayCount === 0)
                <x-ui.empty icon="calendar" :title="__('No work scheduled for today.')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($toDoTodayTasks as $tTask)
                        @if($tTask->order)
                            @include('livewire.dashboard.partials.row', ['order' => $tTask->order, 'rowKey' => 'today-task-'.$tTask->id, 'line2' => $tTask->title, 'person' => $tTask->assignee ?? $tTask->order->primary_designer, 'toggle' => ['wire' => "completeTask({$tTask->id})", 'done' => false, 'title' => __('Complete subtask')]])
                        @endif
                    @endforeach
                    @foreach($toDoTodayOrders as $order)
                        @include('livewire.dashboard.partials.row', ['order' => $order, 'rowKey' => 'today-order-'.$order->id, 'showDue' => true, 'toggle' => ['wire' => "markDoneToday({$order->id})", 'done' => (bool) $order->done_today, 'title' => $order->done_today ? __('Completed (Click to uncheck)') : __('Mark as completed')]])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($activeTab === 'overdue')
        <section class="space-y-2">
            <h2 class="text-section font-semibold text-ink">{{ __('Overdue Orders') }} <span class="text-ink-3 font-normal tabular-nums">{{ $overdueOrders->count() }}</span></h2>
            @if($overdueOrders->isEmpty())
                <x-ui.empty icon="check-circle-2" :title="__('No overdue orders at this moment.')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($overdueOrders as $order)
                        @include('livewire.dashboard.partials.row', ['order' => $order, 'rowKey' => 'overdue-order-'.$order->id, 'tags' => [$overdueTag($order)]])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($activeTab === 'camila')
        <section class="space-y-2">
            <h2 class="text-section font-semibold text-ink">{{ __('Camila Reviews') }} <span class="text-ink-3 font-normal tabular-nums">{{ $camilaFollowUpTasks->count() }}</span></h2>
            @if($camilaFollowUpTasks->isEmpty())
                <x-ui.empty icon="user-check" :title="__('No pending tasks or Camila reviews.')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($camilaFollowUpTasks as $t)
                        @if($t->order)
                            @include('livewire.dashboard.partials.row', ['order' => $t->order, 'rowKey' => 'camila-task-'.$t->id, 'tags' => [$camilaTag], 'line2' => $t->title, 'person' => $t->assignee ?? $t->order->primary_designer, 'action' => ['label' => __('Review now'), 'wire' => "openCamilaModal({$t->id})"]])
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if(! $isDesigner && $activeTab === 'resolver')
        <section class="space-y-2">
            <h2 class="text-section font-semibold text-ink">{{ __('Blocked orders') }} <span class="text-ink-3 font-normal tabular-nums">{{ $resolverOrders->count() }}</span></h2>
            @if($resolverOrders->isEmpty())
                <x-ui.empty icon="check-circle-2" :title="__('No blocked orders need resolving.')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($resolverOrders as $order)
                        @include('livewire.dashboard.partials.row', ['order' => $order, 'rowKey' => 'resolver-order-'.$order->id, 'tags' => [$blockedTag($order)], 'action' => ['label' => __('Unblock'), 'wire' => "openResolveModal({$order->id})"]])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($activeTab === 'client')
        <section class="space-y-2">
            <h2 class="text-section font-semibold text-ink">{{ __('Waiting on the client') }} <span class="text-ink-3 font-normal tabular-nums">{{ $clientFollowUpTasks->count() }}</span></h2>
            @if($clientFollowUpTasks->isEmpty())
                <x-ui.empty icon="mail" :title="__('No pending client follow-ups.')" class="rounded-card border border-line bg-surface" />
            @else
                <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                    @foreach($clientFollowUpTasks as $t)
                        @if($t->order)
                            @include('livewire.dashboard.partials.row', ['order' => $t->order, 'rowKey' => 'client-task-'.$t->id, 'tags' => [$clientTag], 'line2' => $t->title, 'person' => $t->assignee ?? $t->order->primary_designer, 'action' => ['label' => __('Done'), 'wire' => "completeTask({$t->id})"]])
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @foreach($secondary as $tab => $cfg)
        @if($activeTab === $tab)
            <section class="space-y-2">
                <h2 class="text-section font-semibold text-ink">{{ $cfg['label'] }} <span class="text-ink-3 font-normal tabular-nums">{{ $cfg['items']->count() }}</span></h2>
                @if($cfg['items']->isEmpty())
                    <x-ui.empty :icon="$cfg['icon']" :title="$cfg['empty']" class="rounded-card border border-line bg-surface" />
                @else
                    <div class="rounded-card border border-line bg-surface divide-y divide-line overflow-hidden">
                        @foreach($cfg['items'] as $order)
                            @php
                                $sentAt = $order->last_sent_to_client_at ?? $order->updated_at;
                                $rowData = ['order' => $order, 'rowKey' => $tab.'-order-'.$order->id];
                                if ($tab === 'pronostico' && $sentAt) {
                                    $rowData['meta'] = __('Sent').' '.$sentAt->locale($locale)->translatedFormat('j M, H:i');
                                }
                                if ($tab === 'new_orders') {
                                    $rowData['action'] = ['label' => __('Move to Workspace'), 'wire' => "moveToWorkspace({$order->id})"];
                                    $rowData['extra'] = $order->trello_card_id ? 'https://trello.com/c/'.$order->trello_card_id : null;
                                }
                            @endphp
                            @include('livewire.dashboard.partials.row', $rowData)
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    @endforeach

    <nav class="border-t border-line pt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-body text-ink-2" aria-label="{{ __('Other lists') }}">
        @foreach($secondary as $tab => $cfg)
            <button type="button" wire:click="setActiveTab('{{ $tab }}')" aria-pressed="{{ $activeTab === $tab ? 'true' : 'false' }}"
                class="inline-flex items-center gap-1.5 cursor-pointer hover:text-ink {{ $activeTab === $tab ? 'text-ink font-medium underline underline-offset-4 decoration-2' : '' }}">
                {{ svg('lucide-'.$cfg['icon'], 'w-3.5 h-3.5 text-ink-3', ['aria-hidden' => 'true']) }}
                {{ $cfg['label'] }} <span class="tabular-nums text-ink-3">{{ $cfg['items']->count() }}</span>
            </button>
        @endforeach
    </nav>

    @include('livewire.dashboard.partials.modals')
</div>
