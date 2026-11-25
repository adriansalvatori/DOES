<div class="space-y-4">
@if($order)
                    <!-- Timeline Section Header -->
                    <x-orders.tab-section-header :title="__('History')">
                        <x-slot:actions>
                            <span class="text-meta text-ink-3 font-mono font-medium">{{ $order->getTimelineEvents()->count() }} {{ __('record(s)') }}</span>
                        </x-slot:actions>
                    </x-orders.tab-section-header>
                    
                    <div class="bg-canvas border border-line rounded-card p-3.5 sm:p-4 space-y-3.5 ">
                        <div class="relative pl-6 space-y-4 pr-2 timeline-scrollbar">
                                @forelse($order->getTimelineEvents() as $index => $event)
                                    @php
                                        $isCrucial = str_contains(strtoupper((string) $event->new_value), 'PRODUCCI')
                                            || str_contains(strtoupper((string) $event->new_value), 'PRODUCTION')
                                            || str_contains(strtoupper((string) $event->event_type), 'APPROVAL')
                                            || str_contains(strtoupper((string) $event->event_type), 'APPROVED')
                                            || str_contains(strtoupper((string) $event->event_type), 'HOLD')
                                            || str_contains(strtoupper((string) $event->event_type), 'DELAY_RESOLVED');
                                        $isAuto = $event->isAutomated() && ! $isCrucial;
                                    @endphp
                                    <div class="relative group">
                                        <!-- Vertical Line Segment -->
                                        @if(!$loop->last || $order->current_due_date)
                                            <span class="absolute left-[-16px] -translate-x-1/2 top-2.5 bottom-[-16px] w-0.5 bg-line" aria-hidden="true"></span>
                                        @endif

                                        <!-- Event Content -->
                                        <div class="min-w-0 {{ $isAuto ? 'opacity-85 hover:opacity-100 transition-opacity' : '' }}">
                                            <div class="relative flex items-center justify-between gap-2 flex-wrap min-h-5">
                                                <!-- Node Dot: perfectly centered vertically with the title and horizontally on the line -->
                                                @if($isAuto)
                                                    <span class="absolute left-[-16px] top-1/2 -translate-x-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full ring-2 ring-white flex items-center justify-center {{ $event->getNodeColorClass() }}" style="{{ $event->getNodeInlineStyle() }}" aria-hidden="true"></span>
                                                @elseif($isCrucial)
                                                    <span class="absolute left-[-16px] top-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full ring-2.5 ring-white  flex items-center justify-center {{ $event->getNodeColorClass() }}" style="{{ $event->getNodeInlineStyle() }}" aria-hidden="true"></span>
                                                @else
                                                    <span class="absolute left-[-16px] top-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full ring-2 ring-white  flex items-center justify-center {{ $event->getNodeColorClass() }}" style="{{ $event->getNodeInlineStyle() }}" aria-hidden="true"></span>
                                                @endif

                                                <h5 class="text-xs leading-5  {{ $isCrucial ? 'font-semibold text-zinc-950 ' : ($isAuto ? 'font-medium text-ink-2' : 'font-semibold text-ink') }}">
                                                    {!! $event->getFormattedTitleHtml() !!}
                                                </h5>
                                                <div class="flex items-center gap-1.5 shrink-0 text-meta text-ink-3 font-mono">
                                                    @if($event->actor && !str_contains(strtoupper((string)$event->event_type), 'COMMENT'))
                                                        @if($isAuto)
                                                            <span class="inline-flex items-center gap-0.5 text-ink-3 font-sans text-meta">
                                                                <x-lucide-bot class="w-3 h-3 text-ink-3 shrink-0" />
                                                                <span>{{ str_contains(strtolower($event->actor), 'automation') ? 'Auto' : $event->actor }}</span>
                                                            </span>
                                                        @else
                                                            <span class="px-1.5 py-0.5 rounded bg-sunken border border-line text-ink-2 font-sans font-medium text-meta">
                                                                {{ $event->actor }}
                                                            </span>
                                                        @endif
                                                        <span class="text-ink-3">•</span>
                                                    @endif
                                                    <span class="{{ $isCrucial ? 'font-semibold text-ink' : '' }}">{{ $event->getDisplayDate() }}</span>
                                                </div>
                                            </div>

                                            <!-- Secondary Discreet Metadata (Non-intrusive) -->
                                            <div class="space-y-0.5 text-meta text-ink-3">
                                                @if(is_array($event->metadata) && isset($event->metadata['from_list']) && isset($event->metadata['to_list']))
                                                    <div class="flex items-center gap-1 pt-0.5">
                                                        <span>{{ __('Trello:') }}</span>
                                                        <span class="text-ink-3">{{ $event->metadata['from_list'] }}</span>
                                                        <span class="text-ink-3">→</span>
                                                        <span class="text-ink-2 font-medium">{{ $event->metadata['to_list'] }}</span>
                                                    </div>
                                                @endif

                                                @if(str_contains($event->event_type, 'DELAY_RESOLVED'))
                                                    @php
                                                        $promisedDate = null;
                                                        if (!empty($event->new_value) && preg_match('/\d{4}-\d{2}-\d{2}/', (string)$event->new_value, $matches)) {
                                                            try {
                                                                $promisedDate = \Carbon\Carbon::parse($matches[0])->locale(app()->getLocale())->translatedFormat('d M, Y');
                                                            } catch (\Throwable $e) {}
                                                        }
                                                    @endphp
                                                    <div class="flex items-center gap-1.5 pt-0.5 text-ink-2 font-medium">
                                                        <x-lucide-calendar class="w-3 h-3 text-ok shrink-0" />
                                                        <span>{{ __('Agreed delivery:') }} <strong class="text-ink font-semibold">{{ $promisedDate ?? $event->new_value }}</strong></span>
                                                    </div>
                                                @endif



                                                @if(is_array($event->metadata) && isset($event->metadata['trigger_type']))
                                                    <div class="flex items-center gap-1.5 pt-0.5">
                                                        <x-lucide-zap class="w-2.5 h-2.5 text-ink-3 shrink-0" />
                                                        <span>{{ __('Source:') }} {{ strtolower(str_replace('_', ' ', (string)$event->metadata['trigger_type'])) }}</span>
                                                        @if(isset($event->metadata['priority']) && $event->metadata['priority'] === 'urgent')
                                                            <span class="text-meta font-semibold text-danger bg-danger-soft px-1 py-0.5 rounded border border-danger/30 ">{{ __('Urgent') }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            @if(is_array($event->metadata) && (isset($event->metadata['reason']) || isset($event->metadata['comment'])) && $event->event_type !== 'ORDER_APPROVED')
                                                @php
                                                    $isCommentEvent = str_contains(strtoupper((string) $event->event_type), 'COMMENT');
                                                    $eventText = $event->metadata['comment'] ?? $event->metadata['reason'] ?? null;
                                                @endphp

                                                @if($isCommentEvent)
                                                    @php
                                                        $previewText = trim(preg_replace('/[*_#`~>\[\]]/', '', strip_tags((string) $eventText)));
                                                        $previewText = preg_replace('/\s+/', ' ', $previewText);
                                                    @endphp
                                                    <div x-data="{ expanded: false }" class="mt-1.5 rounded-card bg-sunken border border-line  hover:border-line-strong transition-all overflow-hidden">
                                                        <!-- Clickable Header / Toggle -->
                                                        <div 
                                                            @click="expanded = !expanded" 
                                                            class="flex items-center justify-between gap-2 p-2.5 hover:bg-sunken transition-colors cursor-pointer select-none group"
                                                            :title="expanded ? '{{ __('Click to collapse comment') }}' : '{{ __('Click to expand comment') }}'">
                                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                                <x-lucide-message-square class="w-3.5 h-3.5 text-ink-2 shrink-0" />
                                                                <span class="text-xs text-ink-2 truncate font-normal" x-show="!expanded">
                                                                    {{ Str::limit($previewText, 80) }}
                                                                </span>
                                                                <span class="text-meta font-semibold text-ink" x-show="expanded" x-cloak>
                                                                    {{ __('Full comment') }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <span class="inline-flex items-center gap-1 text-meta font-semibold text-ink-2 bg-sunken px-2 py-0.5 rounded-control border border-line group-hover:bg-sunken transition-colors">
                                                                    <span x-text="expanded ? '{{ __('Collapse') }}' : '{{ __('Expand') }}'"></span>
                                                                    <x-lucide-chevron-down class="w-3 h-3 transition-transform duration-200" ::class="expanded ? 'rotate-180' : ''" />
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- Collapsible Content -->
                                                        <div x-show="expanded" x-cloak class="px-3 pb-3 pt-1 border-t border-line space-y-2">
                                                            <div class="text-xs text-ink-2 leading-relaxed break-words prose prose-xs prose-stone max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-0.5 [&_ul.task-list]:list-none [&_ul.task-list]:pl-0 [&_li.task-list-item]:list-none [&_h1]:text-sm [&_h1]:font-semibold [&_h1]:text-ink [&_h1]:mt-2.5 [&_h1]:mb-1 [&_h2]:text-xs [&_h2]:font-semibold [&_h2]:text-ink [&_h2]:mt-2 [&_h2]:mb-0.5 [&_h3]:text-xs [&_h3]:font-semibold [&_h3]:text-ink [&_h3]:mt-1.5 [&_h3]:mb-0.5 [&_hr]:my-2.5 [&_hr]:border-line [&_p]:my-1">
                                                                {!! \App\Livewire\Orders\OrderDetailModal::formatCommentMarkdown($eventText) !!}
                                                            </div>
                                                            <div class="pt-1 flex justify-end">
                                                                <button 
                                                                    type="button" 
                                                                    @click.stop="expanded = false" 
                                                                    class="inline-flex items-center gap-1 text-meta font-medium text-ink-3 hover:text-ink-2 transition cursor-pointer">
                                                                    <x-lucide-chevron-up class="w-3 h-3" />
                                                                    <span>{{ __('Collapse') }}</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="mt-1 pl-2.5 border-l-2 {{ str_contains(strtoupper((string) $event->event_type), 'HOLD') || str_contains(strtoupper((string) $event->event_type), 'BLOCK') ? 'border-warn/30 bg-warn-soft' : 'border-line-strong bg-sunken' }} rounded-r-md py-1 px-2 text-xs text-ink-2 space-y-1">
                                                        <p class="leading-relaxed select-text">{{ $eventText }}</p>
                                                        @if(isset($event->metadata['blocked_duration']))
                                                            <span class="inline-flex items-center gap-1 text-meta text-ok bg-ok-soft px-1.5 py-0.5 rounded border border-ok/30 font-medium">
                                                                <x-lucide-clock class="w-3 h-3 text-ok shrink-0" />
                                                                {{ __('Blocked for :duration', ['duration' => $event->metadata['blocked_duration']]) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif

                                            @php
                                                $hasApprovalDetails = ($event->event_type === 'ORDER_APPROVED' || str_contains($event->event_type, 'APPROVAL')) 
                                                    && is_array($event->metadata) 
                                                    && (!empty($event->metadata['approval_type']) || !empty($event->metadata['approval_type_label']) || !empty($event->metadata['approval_note']) || !empty($event->metadata['approval_image']) || !empty($event->metadata['new_due_date']));
                                            @endphp
                                            @if($hasApprovalDetails)
                                                <div class="mt-1.5 p-2.5 rounded-control bg-ok-soft border border-ok/30 text-xs text-ok space-y-1.5 ">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="flex items-center gap-1.5 font-semibold text-ok">
                                                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-ok shrink-0" />
                                                            <span>{{ $event->metadata['approval_type_label'] ?? match($event->metadata['approval_type'] ?? '') {
                                                                'camila' => __('Approved by Camila'),
                                                                'manager', 'gabriela' => __('Approved by :name', ['name' => \App\Models\Setting::getManagerName()]),
                                                                default => __('Approved by Client'),
                                                            } }}</span>
                                                        </div>
                                                        @if(!empty($event->metadata['new_due_date']))
                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                <span class="px-2 py-0.5 rounded-full text-meta font-mono font-semibold bg-ok-soft text-ok border border-ok/30">
                                                                    {{ __('SLA:') }} {{ $event->metadata['new_due_date'] }}
                                                                </span>
                                                                @if(!empty($event->metadata['is_urgente']) || (!empty($event->metadata['sla_reason']) && str_contains(strtolower($event->metadata['sla_reason']), 'urgent')) || (!empty($event->metadata['sla_message']) && str_contains(strtolower($event->metadata['sla_message']), 'urgent')))
                                                                    <span class="px-1.5 py-0.5 rounded text-meta font-semibold bg-danger-soft text-danger border border-danger/30 ">
                                                                        {{ __('Urgent (Same Day)') }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if(!empty($event->metadata['approval_note']))
                                                        <p class="p-2 rounded bg-surface/80 border border-ok/30 text-ink-2 text-meta leading-relaxed break-words italic">
                                                            "{{ $event->metadata['approval_note'] }}"
                                                        </p>
                                                    @endif

                                                    @if(!empty($event->metadata['approval_image']))
                                                        <div class="pt-0.5">
                                                            <button 
                                                                type="button" 
                                                                wire:click="$parent.previewMedia('{{ $event->metadata['approval_image'] }}', '{{ __('Approval Proof') }}', 'image')" 
                                                                class="inline-flex items-center gap-2 p-1 rounded-control bg-surface border border-ok/30 hover:border-ok/30 text-ok transition cursor-pointer group ">
                                                                <div x-data="{ imgError: false }" class="shrink-0">
                                                                    <img src="{{ $event->metadata['approval_image'] }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ __('Proof') }}" class="w-8 h-8 object-cover rounded border border-line">
                                                                    <div x-show="imgError" x-cloak class="w-8 h-8 rounded border border-line bg-sunken flex items-center justify-center text-ink-3">
                                                                        <x-lucide-file-text class="w-4 h-4" />
                                                                    </div>
                                                                </div>
                                                                <span class="font-medium text-meta group-hover:underline text-ok pr-1.5">{{ __('View attached proof') }}</span>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="relative flex items-center min-h-5">
                                        <span class="absolute left-[-16px] top-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-line ring-2 ring-white " aria-hidden="true"></span>
                                        <p class="text-xs text-ink-3">{{ __('No timeline events yet.') }}</p>
                                    </div>
                                @endforelse

                                <!-- SLA / Current Due Date Node if set -->
                                @if($order->current_due_date)
                                    <div class="relative group pt-1">
                                        <div class="relative flex items-center justify-between gap-2 flex-wrap min-h-5">
                                            <span class="absolute left-[-16px] top-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full {{ $order->isOverdue() ? 'bg-danger' : 'bg-ok' }} ring-2.5 ring-white  flex items-center justify-center text-white" aria-hidden="true">
                                                @if($order->isOverdue())
                                                    <x-lucide-alert-triangle class="w-1.5 h-1.5 text-white stroke-[2.5]" />
                                                @else
                                                    <x-lucide-flag class="w-1.5 h-1.5 text-white stroke-[2.5]" />
                                                @endif
                                            </span>
                                            <h5 class="text-xs leading-5 font-semibold {{ $order->isOverdue() ? 'text-danger' : 'text-ok' }}">
                                                {{ __('Delivery Target') }} {{ $order->isOverdue() ? '(' . __('Past due') . ')' : '(' . __('On track') . ')' }}
                                            </h5>
                                            <span class="text-meta font-mono font-semibold {{ $order->isOverdue() ? 'text-danger' : 'text-ok' }}">
                                                {{ $order->current_due_date->translatedFormat('d M, g:i A') }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Summary Duration Footer Box (matching mockup) -->
                            <div class="pt-3 border-t border-line flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-1.5 text-ink-2">
                                    <x-lucide-clock class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                    <span>{{ __('Elapsed time:') }}</span>
                                    <strong class="text-ink font-mono font-semibold">{{ $order->created_at->diffForHumans(null, true) }}</strong>
                                </div>

                                @if($order->current_due_date)
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-control border text-meta font-semibold  {{ $order->isOverdue() ? 'bg-danger-soft text-danger border-danger/30' : 'bg-ok-soft text-ok border-ok/30' }}">
                                        @if($order->isOverdue())
                                            <x-lucide-alert-triangle class="w-3.5 h-3.5 text-danger shrink-0" />
                                            <span>{{ __('SLA Overdue') }}</span>
                                        @else
                                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-ok shrink-0" />
                                            <span>{{ __('Near deadline') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
@endif
</div>
