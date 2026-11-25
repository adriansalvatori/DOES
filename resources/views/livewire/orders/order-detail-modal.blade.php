@php
    $presetDataList = isset($subtaskPresets) ? $subtaskPresets->map(function($p) {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'badge_style' => $p->badgeStyle(),
            'category' => $p->category?->value ?? 'new_design',
            'is_work_task' => (bool) $p->is_work_task,
        ];
    })->values()->toArray() : [];
@endphp
<div>
    <!-- Notion Side Flyout Drawer (Light Mode Panel) -->
    <div 
        x-data="{
            isOpen: @js((bool) $showModal),
            initialForm: null,
            init() {
                if (this.isOpen) {
                    this.snapshot();
                    window.KudosDirtyGuard.register('order-detail-modal', () => this.isDirty(), this.$el);
                    window.KudosModalStack?.register('order-detail-modal', this.$el, () => this.confirmClose(() => $wire.closeModal()));
                }
                this.$watch('$wire.isEditing', (val) => {
                    if (val) {
                        this.snapshot();
                        window.KudosModalStack?.bringToFront('order-detail-modal');
                    }
                });
                this.$watch('$wire.showModal', (show) => {
                    this.isOpen = Boolean(show);
                    if (!show) {
                        window.KudosDirtyGuard.unregister('order-detail-modal');
                        window.KudosModalStack?.unregister('order-detail-modal');
                    } else {
                        this.snapshot();
                        window.KudosDirtyGuard.register('order-detail-modal', () => this.isDirty(), this.$el);
                        window.KudosModalStack?.register('order-detail-modal', this.$el, () => this.confirmClose(() => $wire.closeModal()));
                        window.KudosModalStack?.bringToFront('order-detail-modal');
                    }
                });
                this.$watch('$wire.orderId', () => {
                    if (this.isOpen) {
                        window.KudosModalStack?.bringToFront('order-detail-modal');
                    }
                });
                this.$cleanup(() => {
                    window.KudosDirtyGuard.unregister('order-detail-modal');
                    window.KudosModalStack?.unregister('order-detail-modal');
                });
            },
            openImmediate() {
                this.isOpen = true;
                window.KudosModalStack?.bringToFront('order-detail-modal');
            },
                snapshot() {
                    this.initialForm = JSON.stringify({
                        wo: ($wire.editWoNumber || '').toString().trim(),
                        trelloId: ($wire.editTrelloCardId || '').toString().trim(),
                        company: ($wire.editCompanyName || '').toString().trim(),
                        location: ($wire.editLocationName || '').toString().trim(),
                        resp: ($wire.editResponsiblePerson || '').toString().trim(),
                        task: ($wire.editTaskName || '').toString().trim(),
                        designers: Array.from($wire.editDesignerIds || []).map(String).sort(),
                        status: ($wire.editCoreStatus || '').toString().trim(),
                        substatus: ($wire.editSubstatus || '').toString().trim(),
                        due: ($wire.editDueDate || '').toString().trim(),
                        clientRev: Number($wire.editClientRevisionCount || 0),
                        internalRev: Number($wire.editInternalRevisionCount || 0)
                    });
                },
                isEditDirty() {
                    if (!$wire.isEditing || !this.initialForm) return false;
                    const current = JSON.stringify({
                        wo: ($wire.editWoNumber || '').toString().trim(),
                        trelloId: ($wire.editTrelloCardId || '').toString().trim(),
                        company: ($wire.editCompanyName || '').toString().trim(),
                        location: ($wire.editLocationName || '').toString().trim(),
                        resp: ($wire.editResponsiblePerson || '').toString().trim(),
                        task: ($wire.editTaskName || '').toString().trim(),
                        designers: Array.from($wire.editDesignerIds || []).map(String).sort(),
                        status: ($wire.editCoreStatus || '').toString().trim(),
                        substatus: ($wire.editSubstatus || '').toString().trim(),
                        due: ($wire.editDueDate || '').toString().trim(),
                        clientRev: Number($wire.editClientRevisionCount || 0),
                        internalRev: Number($wire.editInternalRevisionCount || 0)
                    });
                    return current !== this.initialForm;
                },
                isCommentDirty() {
                    if (!$wire.showModal) return false;
                    const c = ($wire.newTrelloComment || '').trim();
                    return c.length > 0;
                },
                isDirty() {
                    if (!$wire.showModal) return false;
                    return this.isEditDirty() || this.isCommentDirty();
                },
                confirmClose(action) {
                    if (window.KudosDirtyGuard && window.KudosDirtyGuard.isConfirmModalOpen) {
                        return;
                    }
                    let title = @js(__('Save order changes?'));
                    let description = @js(__('You have unsaved edited information in the order.'));

                    if (this.isEditDirty() && this.isCommentDirty()) {
                        title = @js(__('Save changes and post comment?'));
                        description = @js(__('You have unsaved edited order fields and a draft comment.'));
                    } else if (this.isEditDirty()) {
                        title = @js(__('Save order edits?'));
                        description = @js(__('You have unsaved changes in the order fields.'));
                    } else if (this.isCommentDirty()) {
                        title = @js(__('Post comment draft?'));
                        description = @js(__('You have a comment draft written on the card.'));
                    }

                    if (this.isDirty()) {
                        window.KudosDirtyGuard.openConfirmModal({
                            title: title,
                            description: description,
                            cancelText: @js(__('Cancel')),
                            discardText: @js(__('Don\'t save')),
                            saveText: @js(__('Save')),
                            onCancel: () => {},
                            onDiscard: () => {
                                this.isOpen = false;
                                window.KudosDirtyGuard.unregister('order-detail-modal');
                                window.KudosModalStack?.unregister('order-detail-modal');
                                action();
                            },
                            onSave: () => {
                                this.isOpen = false;
                                window.KudosDirtyGuard.unregister('order-detail-modal');
                                window.KudosModalStack?.unregister('order-detail-modal');
                                if (this.isEditDirty()) {
                                    $wire.saveOrder(false);
                                }
                                if (this.isCommentDirty()) {
                                    $wire.addTrelloComment();
                                }
                                action();
                            }
                        });
                    } else {
                        this.isOpen = false;
                        window.KudosDirtyGuard.unregister('order-detail-modal');
                        window.KudosModalStack?.unregister('order-detail-modal');
                        action();
                    }
                }
            }"
            x-show="isOpen"
            x-cloak
            @open-order-detail.window="openImmediate()"
            @pointerdown="window.KudosModalStack?.bringToFront('order-detail-modal')"
            @keydown.window.escape="if (window.KudosModalStack ? window.KudosModalStack.isTop('order-detail-modal') : !document.getElementById('client-flyout-panel')) confirmClose(() => $wire.closeModal())"
            data-modal="order-detail"
            class="fixed inset-0 flex justify-end"
            style="z-index: 300;"
        >
            <!-- Backdrop Overlay -->
            <div @click="confirmClose(() => $wire.closeModal())" class="fixed inset-0 bg-black/30 backdrop-blur-xs transition-opacity"></div>

            <!-- Slide-over Right Panel (Fully Responsive Width) -->
            <div class="fixed inset-y-0 right-0 w-full sm:max-w-xl md:max-w-2xl lg:max-w-3xl bg-surface border-l border-line shadow-panel flex flex-col h-full max-h-screen animate-in slide-in-from-right duration-200 overflow-hidden">
                @if($showModal && $order)
            
            <!-- Flyout Header -->
            <div class="px-4 sm:px-6 py-4 border-b border-line bg-surface shrink-0 z-20 space-y-3">
                <!-- Top bar: identifiers left, actions right -->
                <div class="flex items-center justify-between gap-3 min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($order->wo_number)
                            <x-wo-badge :number="$order->wo_number" variant="dark" show-copy-icon />
                        @else
                            <x-ui.tag>{{ __('No WO') }}</x-ui.tag>
                        @endif

                        @if($order->is_missing_from_trello)
                            <x-ui.tag icon="alert-triangle" title="{{ __('Missing in Trello') }}">
                                <span class="hidden sm:inline">{{ __('Missing in Trello') }}</span>
                            </x-ui.tag>
                        @endif

                        <span class="text-meta text-ink-3 flex items-center gap-1 whitespace-nowrap">
                            <x-lucide-clock class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ ($order->trello_created_at ?? $order->created_at)?->translatedFormat('d M, Y (H:i)') ?? '—' }}</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if(!$isEditing)
                            <button
                                type="button"
                                wire:click="startEditing"
                                @click="window.KudosModalStack?.bringToFront('order-detail-modal')"
                                class="ui-btn ui-btn-secondary ui-btn-sm"
                                title="{{ __('Edit order fields') }}">
                                <x-lucide-pencil />
                                <span>{{ __('Edit') }}</span>
                            </button>
                        @else
                            <button
                                type="button"
                                @click.prevent="confirmClose(() => $wire.cancelEditing())"
                                class="ui-btn ui-btn-secondary ui-btn-sm"
                                title="{{ __('Cancel editing') }}">
                                <x-lucide-x />
                                <span>{{ __('Cancel') }}</span>
                            </button>
                        @endif

                        {{-- One primary next step: unblock when blocked/incoming, otherwise approve. --}}
                        @if($order->isBlocked() || $order->core_status === \App\Enums\CoreStatus::ENTRANTE)
                            <button type="button" wire:click="openUnblockModal" class="ui-btn ui-btn-primary ui-btn-sm" title="{{ __('Unblock order') }}">
                                <x-lucide-unlock />
                                <span>{{ __('Unblock') }}</span>
                            </button>
                        @elseif(!$order->approved)
                            <button type="button" wire:click="openApprovalModal" class="ui-btn ui-btn-primary ui-btn-sm" title="{{ __('Approve order') }}">
                                <x-lucide-check-circle-2 />
                                <span>{{ __('Approve') }}</span>
                            </button>
                        @endif

                        <flux:dropdown position="bottom" align="end">
                            <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" title="{{ __('More options') }}" aria-label="{{ __('More options') }}">
                                <x-lucide-more-horizontal />
                            </button>
                            <flux:menu>
                                <flux:menu.item icon="document-duplicate" wire:click="$dispatch('open-duplicate-order', { orderId: {{ $order->id }} })">{{ __('Duplicate order') }}</flux:menu.item>

                                @if($order->approved || $order->isBlocked() || $order->core_status === \App\Enums\CoreStatus::ENTRANTE)
                                    <flux:menu.item icon="check-circle" wire:click="openApprovalModal">{{ $order->approved ? __('View or record approval') : __('Approve order') }}</flux:menu.item>
                                @endif

                                @if($order->isBlocked())
                                    <flux:menu.item icon="lock-open" wire:click="openUnblockModal">{{ __('Unblock order') }}</flux:menu.item>
                                @else
                                    <flux:menu.item icon="no-symbol" wire:click="openBlockModal">{{ __('Mark as blocked') }}</flux:menu.item>
                                @endif

                                @if($order->in_workspace)
                                    <flux:menu.item icon="archive-box" wire:click="moveToBacklog" wire:confirm="{{ __('Move this order back to Backlog?') }}">{{ __('Send to Backlog') }}</flux:menu.item>
                                @else
                                    <flux:menu.item icon="plus-circle" wire:click="addToWorkspaceDirectly">{{ __('Add to Workspace') }}</flux:menu.item>
                                @endif

                                @if($order->trello_url)
                                    <flux:menu.item icon="arrow-top-right-on-square" href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer">{{ __('View order in Trello') }}</flux:menu.item>
                                @endif

                                @if($order->core_status !== \App\Enums\CoreStatus::ARCHIVED)
                                    <flux:menu.item icon="archive-box-arrow-down" wire:click="openArchiveModal">{{ __('Archive order') }}</flux:menu.item>
                                @endif

                                @if(auth()->user()?->canDeleteOrders())
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteOrder" wire:confirm="{{ __('Move order :name to the recycle bin? It will be archived in Trello.', ['name' => $order->company_name]) }}">{{ __('Send to trash (archive)') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>

                        <button
                            type="button"
                            @click="confirmClose(() => $wire.closeModal())"
                            class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon"
                            title="{{ __('Close panel (Esc)') }}"
                            aria-label="{{ __('Close panel') }}">
                            <x-lucide-x />
                        </button>
                    </div>
                </div>

                <!-- Title block -->
                <div class="min-w-0 flex items-start gap-3">
                    <button
                        wire:click="toggleDoneToday"
                        type="button"
                        class="w-5 h-5 mt-0.5 rounded-full border transition flex items-center justify-center shrink-0 cursor-pointer {{ $order->done_today ? 'bg-ok border-ok text-white' : 'border-line-strong hover:border-ink bg-surface text-transparent hover:text-ink-3' }}"
                        title="{{ $order->done_today ? __('Completed (Click to uncheck)') : __('Mark as completed') }}">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                    </button>
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <div class="flex items-center gap-x-3 gap-y-1 flex-wrap min-w-0">
                            <button
                                type="button"
                                wire:click="openClientDetail"
                                class="text-left inline-flex items-center gap-1.5 focus:outline-none cursor-pointer rounded-control -ml-1.5 px-1.5 py-0.5 hover:bg-sunken transition-colors"
                                title="{{ __('View details for :client', ['client' => $order->company_name ?: __('client')]) }}">
                                <h2 class="text-title font-semibold text-ink break-words {{ $order->done_today ? 'ui-done' : '' }}">
                                    {{ $order->company_name ?: __('No Client') }}
                                </h2>
                                <x-lucide-external-link class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                            </button>
                            <x-orders.location :location="$order->location_text" />
                        </div>

                        @if($order->task_name)
                            <p class="text-body text-ink-2 break-words {{ $order->done_today ? 'ui-done' : '' }}">{{ $order->task_name }}</p>
                        @endif
                    </div>
                </div>

                <!-- Stage, health and neutral properties -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Stage (clickable dropdown) -->
                    <div class="relative" x-data="{ open: false }" x-dropdown-nav>
                        <button
                            type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="ui-btn ui-btn-secondary ui-btn-sm"
                            title="{{ __('Click to change core status') }}">
                            <x-orders.stage :status="$order->core_status" />
                            <x-lucide-chevron-down class="text-ink-3 transition-transform" ::class="{ 'rotate-180': open }" />
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-cloak
                            class="absolute left-0 top-full mt-1 z-[350] w-60 bg-surface border border-line rounded-card shadow-menu py-1 overflow-y-auto max-h-72 text-body">
                            <div class="px-3 py-1.5 text-meta font-medium text-ink-3">{{ __('Change main status') }}</div>
                            @foreach($coreStatuses as $st)
                                <button
                                    type="button"
                                    wire:click="changeCoreStatus('{{ $st->value }}')"
                                    @click="open = false"
                                    class="w-full text-left px-3 py-1.5 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink transition flex items-center justify-between gap-2 {{ $order->core_status === $st ? 'font-semibold' : '' }}">
                                    <span class="truncate">{{ $st->label() }}</span>
                                    @if($order->core_status === $st)
                                        <x-lucide-check class="w-4 h-4 text-ink shrink-0" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- The stage button already reads "Bloqueado" for that column; the tag only adds blocked state to other stages. --}}
                    @if($order->isBlocked() && $order->core_status !== \App\Enums\CoreStatus::ENTRANTE)
                        <x-ui.tag tone="warn" icon="lock">{{ __('Blocked') }}</x-ui.tag>
                    @endif
                    @if($order->isOverdue() && !$order->done_today)
                        <x-ui.tag tone="danger" icon="alert-circle">{{ __('Overdue') }}</x-ui.tag>
                    @endif
                    @if($order->approved)
                        <x-ui.tag tone="ok" icon="check-circle-2" title="{{ $order->approval_note ?: ($order->approval_type_label ?? __('Order Approved')) }}">
                            {{ $order->approval_type_label ?? __('Approved') }}
                            @if($order->approval_image_path)
                                <button
                                    type="button"
                                    wire:click="previewMedia('{{ Storage::url($order->approval_image_path) }}', '{{ __('Approval Proof') }}', 'image')"
                                    class="cursor-pointer"
                                    title="{{ __('View approval proof') }}">
                                    <x-lucide-paperclip class="w-3 h-3 inline" />
                                </button>
                            @endif
                        </x-ui.tag>
                    @endif

                    @php $respBadge = $order->display_responsible_person; @endphp
                    <span class="text-meta text-ink-2 inline-flex items-center gap-1">
                        <x-lucide-user class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                        <span>{{ $respBadge }}</span>
                    </span>

                    @if(!$order->in_workspace)
                        <span class="text-meta text-ink-2 inline-flex items-center gap-1">
                            <x-lucide-box class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                            <span>{{ __('In Backlog') }}</span>
                        </span>
                    @endif

                    @if($order->substatus)
                        <span class="text-meta text-ink-2 inline-flex items-center gap-1">
                            <x-lucide-circle-dot class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                            <span>{{ $order->substatus->label() }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Flash Notification Banner -->
            @if (session()->has('message'))
                <div class="bg-ok-soft border-b border-ok/30 text-ok px-5 py-2 text-xs font-medium flex items-center gap-2 shrink-0">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-ok shrink-0" />
                    <span>{{ session('message') }}</span>
                </div>
            @endif

            <!-- Flyout Body (Scrollable Notion Page Content) -->
            <div class="overflow-y-auto flex-1 min-h-0 scrollbar-thin [scrollbar-gutter:stable]">

                <!-- Top Properties / Edit Form Section -->
                <div class="px-4 sm:px-6 pt-4 sm:pt-5 space-y-5 {{ $isEditing ? 'pb-24' : 'pb-5' }}">

                    <!-- Pending WO Update Alert Banner -->
                @if($order->pending_wo_number)
                    <div class="bg-warn-soft border-2 border-warn/30 rounded-card p-4  space-y-3 shrink-0 ring-2 ring-line-strong animate-in fade-in zoom-in duration-150">
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-card bg-warn text-white shrink-0 ">
                                <x-lucide-alert-triangle class="w-5 h-5 stroke-[2.5]" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <h4 class="font-semibold text-xs text-warn   flex items-center gap-1.5">
                                        {{ __('WO update from Trello available') }}
                                    </h4>
                                    <span class="px-2 py-0.5 rounded-full bg-warn-soft border border-warn/30 text-meta font-semibold text-warn">
                                        {{ __('Requires decision') }}
                                    </span>
                                </div>
                                <p class="text-xs text-warn leading-relaxed">
                                    {{ __('A new WO number was detected in Trello:') }} <x-wo-badge :number="$order->pending_wo_number" variant="amber" show-copy-icon /> <span class="text-meta text-warn">({{ __('Trello card title') }})</span>.
                                </p>
                                <p class="text-xs text-warn leading-relaxed">
                                    {{ __('The number currently registered in DOES is:') }} @if($order->wo_number)<x-wo-badge :number="$order->wo_number" variant="amber" show-copy-icon />@else<strong class="font-mono bg-surface px-1.5 py-0.5 rounded border border-warn/30 text-warn font-semibold ">{{ __('No WO / WO 0000') }}</strong>@endif <span class="text-meta text-warn">(DOES)</span>.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-warn/30">
                            <button 
                                type="button" 
                                wire:click="dismissPendingWo" 
                                class="ui-btn ui-btn-secondary ui-btn-sm">
                                <x-lucide-x />
                                <span>{{ __('Keep') }} {{ $order->wo_number ?: __('current') }} (DOES)</span>
                            </button>

                            @if(auth()->user()?->canManageWorkOrders())
                                <button 
                                    type="button" 
                                    wire:click="acceptPendingWo" 
                                    class="ui-btn ui-btn-primary ui-btn-sm">
                                    <x-lucide-check />
                                    <span>{{ __('Update to') }} {{ $order->pending_wo_number }} (Trello)</span>
                                </button>
                            @else
                                <span class="px-2.5 py-1.5 rounded-control bg-warn-soft text-warn font-medium text-xs flex items-center gap-1.5">
                                    <x-lucide-lock class="w-3.5 h-3.5 text-warn shrink-0" />
                                    <span>{{ __('WO suggestion: :wo (only coordinators and leads can apply changes)', ['wo' => $order->pending_wo_number]) }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
                
                @if($isEditing)
                    <!-- EDIT FORM MODE -->
                    <div class="bg-canvas border border-line rounded-card p-4 space-y-4">
                        <div class="flex items-center justify-between border-b border-line pb-2">
                            <h4 class="font-semibold text-xs text-ink   flex items-center gap-1.5">
                                <x-lucide-edit-3 class="w-4 h-4 text-ink-2" /> {{ __('Edit Order Information') }}
                            </h4>
                            <span class="text-meta text-ink-3">{{ __('Modify dates, designer, WO or dissected fields') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                            <!-- WO Number -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="ui-label">{{ __('Order number') }}</label>
                                    @if(auth()->user()?->canManageWorkOrders())
                                        <button 
                                            type="button" 
                                            wire:click="generateWoNumber" 
                                            wire:loading.attr="disabled"
                                            class="text-meta font-medium text-ink-2 hover:text-ink-2 flex items-center gap-1 cursor-pointer transition select-none disabled:opacity-50"
                                            title="{{ __('Create next WO number automatically') }}">
                                            <x-lucide-sparkles class="w-3 h-3 text-warn" wire:loading.remove wire:target="generateWoNumber" />
                                            <x-lucide-loader-2 class="w-3 h-3 animate-spin text-ink-3" wire:loading wire:target="generateWoNumber" />
                                            <span>{{ __('Create WO') }}</span>
                                        </button>
                                    @endif
                                </div>
                                <div class="flex rounded-control ">
                                    <span class="inline-flex items-center px-2.5 rounded-l-md border border-r-0 border-line bg-sunken text-ink-2 font-mono font-semibold text-xs select-none">
                                        WO
                                    </span>
                                    <input 
                                        type="text" 
                                        wire:model="editWoNumber" 
                                        placeholder="16253" 
                                        @if(!auth()->user()?->canManageWorkOrders()) disabled readonly @endif
                                        class="bg-surface border border-line rounded-r-md px-3 py-1.5 text-xs text-ink focus:outline-none w-full font-mono font-semibold {{ !auth()->user()?->canManageWorkOrders() ? 'bg-sunken! text-ink-3! cursor-not-allowed select-none' : '' }}">
                                </div>
                                @if(!auth()->user()?->canManageWorkOrders())
                                    <p class="text-meta text-ink-3 flex items-center gap-1 mt-0.5">
                                        <x-lucide-lock class="w-3 h-3 text-ink-3 shrink-0" />
                                        <span>{{ __('Designers can\'t change the WO number.') }}</span>
                                    </p>
                                @endif
                            </div>

                            <!-- Trello Card ID / Link (Custom Searchable Dropdown) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectCard(id) {
                                         $wire.set('editTrelloCardId', id);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label flex items-center gap-1">
                                    <x-lucide-external-link class="w-3.5 h-3.5 text-ink-2" />
                                    <span>{{ __('Trello card ID / link') }}</span>
                                </label>
                                
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editTrelloCardId" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. AbCdEf12 or search by company/WO...') }}" 
                                        class="ui-input pr-7 font-mono">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink-2 p-0.5"
                                        title="{{ __('View available Trello cards') }}">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Custom Searchable Dropdown Popup -->
                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu max-h-48 overflow-y-auto divide-y divide-line text-xs">
                                    
                                    <div class="px-2.5 py-1 bg-sunken border-b border-line font-semibold text-meta  text-ink-3">
                                        {{ __('Available Trello Cards') }} ({{ count($availableTrelloCards) }})
                                    </div>

                                    @forelse($availableTrelloCards as $tc)
                                        <button 
                                            type="button"
                                            x-show="!$wire.editTrelloCardId || '{{ strtolower(addslashes($tc->trello_card_id . ' ' . $tc->wo_number . ' ' . $tc->company_name . ' ' . $tc->task_name . ' ' . $tc->trello_title)) }}'.includes(($wire.editTrelloCardId || '').toLowerCase())"
                                            @click="selectCard('{{ $tc->trello_card_id }}')" 
                                            class="w-full text-left p-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer flex items-center justify-between gap-2 transition">
                                            <div class="min-w-0">
                                                <span class="font-semibold text-ink block truncate text-meta">
                                                    {{ $tc->trello_title ?: ($tc->company_name ?: __('Trello Card')) }}
                                                </span>
                                                @if($tc->task_name && $tc->task_name !== $tc->trello_title)
                                                    <span class="text-meta text-ink-3 block truncate">{{ $tc->task_name }}</span>
                                                @endif
                                            </div>
                                            <span class="font-mono text-meta text-ink-2 bg-sunken px-1.5 py-0.5 rounded border border-line shrink-0">
                                                {{ substr($tc->trello_card_id, 0, 8) }}...
                                            </span>
                                        </button>
                                    @empty
                                        <div class="p-3 text-center text-ink-3 italic text-meta">{{ __('No cards recorded yet.') }}</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Responsible Person (Searchable Dropdown Menu) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectResp(id, name) {
                                         $wire.set('editContactId', id);
                                         $wire.set('editResponsiblePerson', name);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <div class="flex items-center justify-between">
                                    <label class="ui-label">{{ __('Responsible person / client') }}</label>
                                </div>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editResponsiblePerson" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('E.g. MARCELA or search a client...') }}" 
                                        class="ui-input pr-7">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink-2 p-0.5 cursor-pointer"
                                        title="{{ __('Show client list') }}">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu max-h-60 overflow-y-auto divide-y divide-line text-xs custom-vertical-scrollbar">
                                    
                                    {{-- Contactos de esta compañía --}}
                                    @if(!empty($clientContacts) && $clientContacts->isNotEmpty())
                                        <div class="px-2.5 py-1 bg-ok-soft border-b border-ok/30 font-semibold text-meta  text-ok flex items-center justify-between">
                                            <span>{{ __('Contacts at this company') }}</span>
                                            <x-lucide-user-check class="w-3 h-3 text-ok" />
                                        </div>
                                        @foreach($clientContacts as $c)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editResponsiblePerson || '{{ strtolower(addslashes($c->name)) }}'.includes(($wire.editResponsiblePerson || '').toLowerCase())"
                                                @click="selectResp({{ $c->id }}, '{{ addslashes($c->name) }}')" 
                                                class="w-full text-left px-3 py-2 hover:bg-ok-soft focus:bg-ok-soft focus:outline-none cursor-pointer font-semibold text-ink transition truncate block">
                                                {{ $c->name }}
                                            </button>
                                        @endforeach
                                    @endif

                                    {{-- Otros clientes del directorio global --}}
                                    @php
                                        $linkedIds = !empty($clientContacts) ? $clientContacts->pluck('id')->toArray() : [];
                                        $otherContacts = $directoryContacts->filter(fn($dc) => !in_array($dc->id, $linkedIds, true));
                                    @endphp

                                    @if($otherContacts->isNotEmpty())
                                        <div class="px-2.5 py-1 bg-sunken border-b border-line font-semibold text-meta  text-ink-3 flex items-center justify-between">
                                            <span>{{ __('Client directory') }}</span>
                                            <x-lucide-users class="w-3 h-3 text-ink-3" />
                                        </div>
                                        @foreach($otherContacts as $dc)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editResponsiblePerson || '{{ strtolower(addslashes($dc->name)) }}'.includes(($wire.editResponsiblePerson || '').toLowerCase())"
                                                @click="selectResp({{ $dc->id }}, '{{ addslashes($dc->name) }}')" 
                                                class="w-full text-left px-3 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-medium text-ink transition truncate block">
                                                {{ $dc->name }}
                                            </button>
                                        @endforeach
                                    @endif

                                    {{-- Botón intuitivo para crear contacto sobre la marcha si no existe --}}
                                    <div x-show="Boolean($wire.editResponsiblePerson && $wire.editResponsiblePerson.trim().length >= 2)">
                                        <button 
                                            type="button" 
                                            @click="$wire.createContactForCompany($wire.editResponsiblePerson); open = false;"
                                            class="w-full text-left p-2.5 bg-ok-soft hover:bg-ok-soft text-ok border-t border-ok/30 transition font-semibold text-xs flex items-center justify-between gap-2 cursor-pointer"
                                        >
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <x-lucide-user-plus class="w-4 h-4 text-ok shrink-0" />
                                                <span class="truncate">{{ __('Create a contact for this company') }} <strong class="" x-text="$wire.editResponsiblePerson"></strong></span>
                                            </div>
                                            <span class="text-meta text-ok bg-ok-soft px-1.5 py-0.5 rounded font-semibold shrink-0">{{ __('Create') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Empresa (Searchable Dropdown) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectComp(comp) {
                                         $wire.set('editCompanyName', comp);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <div class="flex items-center justify-between">
                                    <label class="ui-label">{{ __('Company name') }}</label>
                                    @if($order->client_id || $order->company_name || $editCompanyName)
                                        <button 
                                            type="button" 
                                            wire:click="openClientDetail" 
                                            class="text-meta font-medium text-ok hover:text-ok flex items-center gap-1 cursor-pointer transition select-none"
                                            title="{{ __('View client details') }}">
                                            <x-lucide-external-link class="w-3 h-3" />
                                            <span>{{ __('View Client') }}</span>
                                        </button>
                                    @endif
                                </div>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editCompanyName" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. MAIN STREET BAKERY...') }}" 
                                        class="ui-input pr-7"
                                        x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink-2 p-0.5">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu max-h-40 overflow-y-auto divide-y divide-line text-xs">
                                    @forelse($existingCompanies as $comp)
                                        <button 
                                            type="button"
                                            x-show="!$wire.editCompanyName || '{{ strtolower(addslashes($comp)) }}'.includes(($wire.editCompanyName || '').toLowerCase())"
                                            @click="selectComp('{{ addslashes($comp) }}')" 
                                            class="w-full text-left p-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-semibold text-ink transition">
                                            {{ $comp }}
                                        </button>
                                    @empty
                                        <div class="p-2.5 text-ink-3 italic text-meta">{{ __('Enter a new company name...') }}</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Locación / Sede (Searchable Dropdown Menu) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectLoc(loc) {
                                         $wire.set('editLocationName', loc);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label">{{ __('Location / branch (optional)') }}</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="editLocationName" 
                                        @focus="open = true"
                                        @click.outside="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('e.g. TALPA 8, NORTH LOCATION...') }}" 
                                        class="ui-input pr-7">
                                    
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink-2 p-0.5"
                                        title="{{ __('View available locations') }}">
                                        <x-lucide-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu max-h-48 overflow-y-auto divide-y divide-line text-xs">
                                    
                                    @if(!empty($clientLocations))
                                        <div class="px-2.5 py-1 bg-ok-soft border-b border-ok/30 font-semibold text-meta  text-ok flex items-center justify-between">
                                            <span>{{ __('Client locations') }}</span>
                                            <x-lucide-map-pin class="w-3 h-3 text-danger" />
                                        </div>
                                        @foreach($clientLocations as $cLoc)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editLocationName || '{{ strtolower(addslashes($cLoc)) }}'.includes(($wire.editLocationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($cLoc) }}')" 
                                                class="w-full text-left p-2 hover:bg-ok-soft focus:bg-ok-soft focus:outline-none cursor-pointer font-semibold text-ink  transition flex items-center justify-between">
                                                <span>{{ $cLoc }}</span>
                                                <span class="text-meta text-ok font-medium normal-case">({{ __('Registered') }})</span>
                                            </button>
                                        @endforeach
                                    @endif

                                    @php
                                        $otherLocations = array_diff($existingLocations->toArray(), $clientLocations ?? []);
                                    @endphp

                                    @if(!empty($otherLocations))
                                        @if(!empty($clientLocations))
                                            <div class="px-2.5 py-1 bg-sunken border-b border-line font-semibold text-meta  text-ink-3">
                                                {{ __('Other locations') }}
                                            </div>
                                        @endif
                                        @foreach($otherLocations as $loc)
                                            <button 
                                                type="button"
                                                x-show="!$wire.editLocationName || '{{ strtolower(addslashes($loc)) }}'.includes(($wire.editLocationName || '').toLowerCase())"
                                                @click="selectLoc('{{ addslashes($loc) }}')" 
                                                class="w-full text-left p-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-semibold text-ink  transition">
                                                {{ $loc }}
                                            </button>
                                        @endforeach
                                    @endif

                                    @if(empty($clientLocations) && empty($otherLocations))
                                        <div class="p-2.5 text-ink-3 italic text-meta">{{ __('Enter a new location...') }}</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Tarea -->
                            <div class="space-y-1">
                                <label class="ui-label">{{ __('Design task / work') }}</label>
                                <input type="text" wire:model="editTaskName" class="ui-input" x-on:blur="$event.target.value = $event.target.value.toUpperCase()">
                            </div>

                            <!-- Diseñadores -->
                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="ui-label">{{ __('Assigned designers (multiple allowed)') }}</label>
                                <div class="flex flex-wrap items-center gap-1.5 p-2 bg-sunken border border-line rounded-control min-h-[38px]">
                                    @foreach($designers as $des)
                                        @php $isAssigned = in_array((int)$des->id, array_map('intval', $editDesignerIds)); @endphp
                                        <button 
                                            type="button" 
                                            wire:click="toggleDesigner({{ $des->id }})"
                                            class="ui-chip"
                                            aria-pressed="{{ $isAssigned ? 'true' : 'false' }}"
                                        >
                                            <span class="w-2 h-2 rounded-full {{ $des->dot_color_class }}" style="{{ $des->dot_inline_style }}"></span>
                                            <span>{{ $des->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                <span class="text-meta text-ink-3 block">{{ __('* If you select External Designer, Euralíz will be added automatically.') }}</span>
                            </div>

                            <!-- Fecha Límite -->
                            <div class="space-y-1">
                                <label class="ui-label">{{ __('Due date') }}</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="date" wire:model="editDueDate" class="ui-input font-mono">
                                    @if($editDueDate)
                                        <button 
                                            type="button" 
                                            wire:click="$set('editDueDate', '')" 
                                            class="px-2.5 py-1.5 rounded-control bg-sunken hover:bg-danger-soft text-ink-2 hover:text-danger border border-line hover:border-danger/30 text-xs font-medium transition shrink-0 whitespace-nowrap cursor-pointer flex items-center gap-1.5" 
                                            title="{{ __('Set due date to None') }}">
                                            <x-lucide-calendar-off class="w-3.5 h-3.5" />
                                            <span>{{ __('No Date') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Core Status (Custom Searchable Combobox) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     selectStatus(val) {
                                         $wire.set('editCoreStatus', val);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label">{{ __('Trello list / main status') }}</label>
                                <div class="relative">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        @click.outside="open = false"
                                        class="bg-surface border border-line hover:border-line-strong rounded-control px-3 py-1.5 text-xs text-ink w-full text-left flex items-center justify-between font-medium">
                                        <span>{{ \App\Enums\CoreStatus::tryFrom($editCoreStatus)?->label() ?? __('Select status...') }}</span>
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu max-h-48 overflow-y-auto divide-y divide-line text-xs">
                                    @foreach($coreStatuses as $st)
                                        <button 
                                            type="button"
                                            @click="selectStatus('{{ $st->value }}')" 
                                            class="w-full text-left p-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer font-medium text-ink transition flex items-center justify-between">
                                            <span>{{ $st->label() }}</span>
                                            @if($editCoreStatus === $st->value)
                                                <x-lucide-check class="w-3.5 h-3.5 text-ok stroke-[3]" />
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Substatus (Custom Searchable Combobox with Color Badges) -->
                            <div class="space-y-1 relative" 
                                 x-data="{ 
                                     open: false,
                                     search: '',
                                     selectSub(val) {
                                         $wire.set('editSubstatus', val);
                                         this.open = false;
                                     }
                                 }"
                                 x-dropdown-nav>
                                <label class="ui-label">{{ __('Operational substatus') }}</label>
                                <div class="relative">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        @click.outside="open = false"
                                        class="bg-surface border border-line hover:border-line-strong rounded-control px-3 py-1.5 text-xs text-ink w-full text-left flex items-center justify-between font-medium">
                                        @if($editSubstatus)
                                            @php $subEnum = \App\Enums\Substatus::tryFrom($editSubstatus); @endphp
                                            <span class="text-body text-ink">{{ $editSubstatus }}</span>
                                        @else
                                            <span class="text-ink-3 italic">{{ __('No Substatus') }}</span>
                                        @endif
                                        <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3" />
                                    </button>
                                </div>

                                <div 
                                    x-show="open" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute left-0 right-0 top-full mt-1 z-50 bg-surface border border-line rounded-control shadow-menu overflow-hidden text-xs flex flex-col">
                                    <div class="p-1.5 border-b border-line bg-sunken">
                                        <div class="relative flex items-center">
                                            <x-lucide-search class="w-3.5 h-3.5 text-ink-3 absolute left-2 pointer-events-none" />
                                            <input 
                                                type="text" 
                                                x-model="search"
                                                x-ref="subSearchInput"
                                                x-init="$watch('open', value => { if (value) { search = ''; $nextTick(() => $refs.subSearchInput?.focus()); } })"
                                                placeholder="{{ __('Search substatus...') }}"
                                                class="w-full pl-7 pr-6 py-1 text-xs bg-surface border border-line rounded text-ink placeholder:text-ink-3 focus:outline-none focus:border-line-strong">
                                            <button 
                                                type="button" 
                                                x-show="search.length > 0" 
                                                @click="search = ''; $refs.subSearchInput.focus()" 
                                                class="absolute right-1.5 text-ink-3 hover:text-ink-2">
                                                <x-lucide-x class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="max-h-56 overflow-y-auto divide-y divide-line">
                                        <button 
                                            type="button"
                                            x-show="!search || @js(mb_strtolower(__('No substatus'))).includes(search.toLowerCase().trim())"
                                            @click="selectSub('')" 
                                            class="w-full text-left p-2.5 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer text-ink-3 italic transition flex items-center justify-between">
                                            <span>{{ __('No Substatus') }}</span>
                                            @if(!$editSubstatus)
                                                <x-lucide-check class="w-3.5 h-3.5 text-ok stroke-[3]" />
                                            @endif
                                        </button>
                                        @foreach($substatuses as $sub)
                                            <button 
                                                type="button"
                                                x-show="!search || '{{ strtolower(addslashes($sub->value.' '.$sub->label())) }}'.includes(search.toLowerCase().trim())"
                                                @click="selectSub('{{ $sub->value }}')" 
                                                class="w-full text-left p-2 hover:bg-sunken focus:bg-sunken focus:outline-none cursor-pointer transition flex items-center justify-between">
                                                <span class="text-body text-ink">{{ $sub->label() }}</span>
                                                @if($editSubstatus === $sub->value)
                                                    <x-lucide-check class="w-3.5 h-3.5 text-ok stroke-[3]" />
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Revisiones Cliente -->
                            <div class="space-y-1">
                                <label class="ui-label">{{ __('No. of client revisions') }}</label>
                                <input type="number" min="0" wire:model="editClientRevisionCount" class="ui-input font-mono">
                            </div>

                            <!-- Revisiones Internas -->
                            <div class="space-y-1">
                                <label class="ui-label">{{ __('Internal revisions') }}</label>
                                <input type="number" min="0" wire:model="editInternalRevisionCount" class="ui-input font-mono">
                            </div>
                        </div>

                        <!-- Form Save Buttons -->
                        @php
                            // Record data (WO, company, location, Trello link, contact) always asks before saving.
                            $saveLabels = json_encode([
                                'wo' => __('WO'),
                                'trelloId' => __('Trello card ID'),
                                'company' => __('Company'),
                                'location' => __('Location'),
                                'resp' => __('Responsible person'),
                            ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
                            $saveJsTemplate = <<<'JS'
(async () => { const i = JSON.parse(initialForm || '{}'); const L = __LABELS__; const c = { wo: $wire.editWoNumber, trelloId: $wire.editTrelloCardId, company: $wire.editCompanyName, location: $wire.editLocationName, resp: $wire.editResponsiblePerson }; const ch = Object.keys(L).filter(k => String(i[k] ?? '') !== String(c[k] ?? '').trim()); if (ch.length) { const ok = await KudosConfirmChange({ field: ch.map(k => L[k]).join(', '), from: ch.map(k => i[k] || '—').join(' · '), to: ch.map(k => String(c[k] || '—').trim()).join(' · ') }); if (!ok) return; } $wire.saveOrder(__ADD__); })()
JS;
                            $saveJs = fn (bool $add) => str_replace(['__LABELS__', '__ADD__'], [$saveLabels, $add ? 'true' : 'false'], $saveJsTemplate);
                        @endphp
                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                            <button type="button" @click.prevent="confirmClose(() => $wire.cancelEditing())" class="ui-btn ui-btn-ghost">
                                {{ __('Cancel') }}
                            </button>

                            @if(!$order->in_workspace)
                                <button
                                    type="button"
                                    @click.prevent="{{ $saveJs(true) }}"
                                    :disabled="!isEditDirty()"
                                    class="ui-btn ui-btn-secondary"
                                >
                                    <span>{{ __('Save and add to Workspace') }}</span>
                                </button>
                            @endif

                            <button
                                type="button"
                                @click.prevent="{{ $saveJs(false) }}"
                                :disabled="!isEditDirty()"
                                class="ui-btn ui-btn-primary"
                            >
                                <span>{{ __('Save changes') }}</span>
                            </button>
                        </div>
                    </div>
                @else
                    <!-- READ MODE -->

                    <!-- Metadata Property Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 text-body py-1">
                        <div class="min-w-0">
                            <span class="ui-label !mb-1">{{ __('Designers') }}</span>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                @forelse($order->assigned_designers as $des)
                                    <x-ui.person :person="$des" size="sm" show-name />
                                @empty
                                    <span class="text-ink-3 text-body">{{ __('Unassigned') }}</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="min-w-0">
                            <span class="ui-label !mb-1">{{ __('Trello card ID') }}</span>
                            @if($order->trello_card_id)
                                <a href="{{ $order->trello_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-body text-ink-2 hover:text-ink hover:underline max-w-full min-w-0" title="{{ $order->trello_card_id }}">
                                    <x-lucide-external-link class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                    <span class="truncate">{{ $order->trello_card_id }}</span>
                                </a>
                            @else
                                <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                    <button
                                        wire:click="createCardOnTrello"
                                        wire:loading.attr="disabled"
                                        type="button"
                                        class="ui-btn ui-btn-secondary ui-btn-sm"
                                        title="{{ __('Create new card in Trello for this order') }}">
                                        <x-lucide-plus wire:loading.class="hidden" wire:target="createCardOnTrello" />
                                        <x-lucide-loader-2 wire:loading wire:target="createCardOnTrello" class="animate-spin" />
                                        <span>{{ __('Create in Trello') }}</span>
                                    </button>
                                    <button
                                        wire:click="startEditing"
                                        type="button"
                                        class="ui-btn ui-btn-ghost ui-btn-sm"
                                        title="{{ __('Link existing Trello ID') }}">
                                        <x-lucide-link />
                                        <span>{{ __('Link') }}</span>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <span class="ui-label !mb-1">{{ __('Due date') }}</span>
                            @if($order->current_due_date)
                                <x-orders.due :date="$order->current_due_date" :done="(bool) $order->done_today" />
                            @else
                                <span class="text-ink-3 text-body">{{ __('No Date') }}</span>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <span class="ui-label !mb-1">{{ __('Client revisions') }}</span>
                            <span class="text-ink text-body tabular-nums">{{ $order->client_revision_count }}</span>
                        </div>
                    </div>

                    <!-- Overdue Delay Resolution Prompt Banner -->
                    @if($order->isOverdue())
                        <div class="bg-danger-soft rounded-card p-3 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-body text-danger flex items-center gap-1.5">
                                    <x-lucide-alert-octagon class="w-4 h-4 shrink-0" /> {{ __('This order is overdue') }}
                                </h4>
                                <p class="text-meta text-ink-2 mt-0.5">{{ __('Requires recording the new agreed date with the client to resolve the delay.') }}</p>
                            </div>
                            <button wire:click="$set('showDelayModal', true)" class="ui-btn ui-btn-secondary ui-btn-sm">
                                {{ __('Resolve delay') }}
                            </button>
                        </div>
                    @endif
                @endif
                </div>

                @if(!$isEditing)
                    <!-- 3-TAB SYSTEM: PIEZAS+SUBTASKS | COMMENTS + ADJUNTOS | TIMELINE -->
                    <div x-data="{ activeTab: @js($activeTab) }">
                        <!-- Tab Navigation Bar -->
                        <div class="border-b border-line bg-surface sticky top-0 z-20 px-4 sm:px-6">
                            <nav class="flex items-center gap-1 -mb-px overflow-x-auto scrollbar-none" aria-label="Tabs">
                                <!-- Tab 1: PIEZAS+SUBTASKS -->
                                <button
                                    type="button"
                                    @click="activeTab = 'pieces'"
                                    :class="activeTab === 'pieces'
                                        ? 'border-ink text-ink font-semibold'
                                        : 'border-transparent text-ink-3 hover:text-ink font-medium'"
                                    class="py-2.5 px-3 text-body border-b-2 transition-colors flex items-center gap-2 cursor-pointer shrink-0 select-none"
                                >
                                    <span>{{ __('Items and subtasks') }}</span>
                                    @php
                                        $tabPiecesCount = $order->elements->count();
                                        $tabSubtasks = $order->relatedTasks;
                                        $tabTotalSubtasks = $tabSubtasks->count();
                                        $tabDoneSubtasks = $tabSubtasks->filter(fn($t) => $t->isDone())->count();
                                    @endphp
                                    @if($tabPiecesCount > 0 || $tabTotalSubtasks > 0)
                                        <span class="text-meta font-normal text-ink-3 tabular-nums">
                                            {{ $tabPiecesCount }}@if($tabTotalSubtasks > 0) · {{ $tabDoneSubtasks }}/{{ $tabTotalSubtasks }}@endif
                                        </span>
                                    @endif
                                </button>

                                <!-- Tab 2: COMMENTS + ADJUNTOS -->
                                <button
                                    type="button"
                                    @click="activeTab = 'comments'"
                                    :class="activeTab === 'comments'
                                        ? 'border-ink text-ink font-semibold'
                                        : 'border-transparent text-ink-3 hover:text-ink font-medium'"
                                    class="py-2.5 px-3 text-body border-b-2 transition-colors flex items-center gap-2 cursor-pointer shrink-0 select-none"
                                >
                                    <span>{{ __('Comments & attachments') }}</span>
                                    @php
                                        $tabCommentsCount = is_countable($trelloComments ?? null) ? count($trelloComments) : 0;
                                        $tabAttachmentsCount = is_countable($trelloAttachments ?? null) ? count($trelloAttachments) : 0;
                                    @endphp
                                    @if($tabCommentsCount > 0 || $tabAttachmentsCount > 0)
                                        <span class="text-meta font-normal text-ink-3 tabular-nums">
                                            {{ $tabCommentsCount }}@if($tabAttachmentsCount > 0) · {{ $tabAttachmentsCount }}@endif
                                        </span>
                                    @endif
                                </button>

                                <!-- Tab 3: TIMELINE -->
                                <button
                                    type="button"
                                    @click="activeTab = 'timeline'"
                                    :class="activeTab === 'timeline'
                                        ? 'border-ink text-ink font-semibold'
                                        : 'border-transparent text-ink-3 hover:text-ink font-medium'"
                                    class="py-2.5 px-3 text-body border-b-2 transition-colors flex items-center gap-2 cursor-pointer shrink-0 select-none"
                                >
                                    <span>{{ __('Timeline') }}</span>
                                    @php
                                        $tabTimelineCount = $order->getTimelineEvents()->count();
                                    @endphp
                                    @if($tabTimelineCount > 0)
                                        <span class="text-meta font-normal text-ink-3 tabular-nums">{{ $tabTimelineCount }}</span>
                                    @endif
                                </button>
                            </nav>
                        </div>

                        <!-- RECYCLED UNIFIED TAB PANELS CONTAINER (Guarantees identical separation across all tabs) -->
                        <div class="px-4 sm:px-6 pt-5 pb-24">
                            <!-- TAB 1: PIEZAS+SUBTASKS -->
                            <div x-show="activeTab === 'pieces'" class="space-y-6">

                    <!-- Piezas & Elementos en Alta Section -->
                    @include('livewire.orders.partials.alta-elements-section')

                    <!-- Related Subtasks Section -->
                    @php
                        $subtasks = $order->relatedTasks;
                        $totalSubtasks = $subtasks->count();
                        $doneSubtasks = $subtasks->filter(fn($t) => $t->isDone())->count();
                        $progressPercent = $totalSubtasks > 0 ? (int) round(($doneSubtasks / $totalSubtasks) * 100) : 0;
                    @endphp

                    <div class="space-y-3 pt-6 border-t border-line">
                        <!-- Subtasks Header & Progress Bar -->
                        <div class="space-y-2 pb-2">
                            <x-orders.tab-section-header :title="__('Subtasks')">
                                <x-slot:badge>
                                    <span class="text-meta text-ink-3 tabular-nums">{{ $doneSubtasks }}/{{ $totalSubtasks }}</span>
                                </x-slot:badge>
                                @if($totalSubtasks > 0)
                                    <x-slot:actions>
                                        <span class="text-meta text-ink-3 tabular-nums">{{ $progressPercent }}%</span>
                                    </x-slot:actions>
                                @endif
                            </x-orders.tab-section-header>

                            @if($totalSubtasks > 0)
                                <div class="w-full bg-sunken rounded-full h-1.5 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-300 bg-ink" 
                                        style="width: {{ $progressPercent }}%"
                                    ></div>
                                </div>
                            @endif
                        </div>

                        <!-- Subtasks List -->
                        <div class="space-y-1.5">
                            @forelse($subtasks as $task)
                                <div 
                                    wire:key="subtask-{{ $task->id }}"
                                    x-data="{ 
                                        editing: false, 
                                        title: @js($task->title),
                                        openType: false,
                                        openCategory: false,
                                        openDate: false,
                                        openAssignee: false,
                                        assigneeSearch: '',
                                        saveTitle() {
                                            const trimmed = this.title.trim();
                                            if (trimmed && trimmed !== @js($task->title)) {
                                                $wire.updateTaskTitle({{ $task->id }}, trimmed);
                                            } else {
                                                this.title = @js($task->title);
                                            }
                                            this.editing = false;
                                        }
                                    }" 
                                    class="hover:bg-sunken rounded-control px-2.5 py-2 flex items-center justify-between text-xs gap-3 transition group relative"
                                    :class="{ 'z-30': openType || openCategory || openDate || openAssignee }"
                                >
                                    <!-- Read Mode View -->
                                    <div x-show="!editing" class="flex items-center justify-between gap-3 w-full min-w-0">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <!-- Status Checkbox Button -->
                                            <button 
                                                wire:click="toggleTaskStatus({{ $task->id }})" 
                                                type="button"
                                                class="w-4 h-4 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $task->isDone() ? 'bg-ok border-ok/30 text-white ' : 'border-line-strong hover:border-ok/30 bg-surface text-transparent hover:text-ok' }}"
                                                title="{{ $task->isDone() ? __('Mark as pending') : __('Mark as completed') }}">
                                                <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                            </button>

                                            <!-- Subtask Title & Tags -->
                                            <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
                                                @if($task->isSystemTask())
                                                    <x-ui.tag title="{{ __('Subtask automatically generated by the system') }} ({{ $task->colorCodingLabel() }})">{{ $task->colorCodingShortLabel() }}</x-ui.tag>
                                                @endif

                                                <span 
                                                    @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                                    class="font-medium text-ink text-body break-words cursor-pointer hover:underline decoration-line-strong underline-offset-2 transition {{ $task->isDone() ? 'ui-done font-normal' : '' }}" 
                                                    title="{{ __('Click to edit subtask name') }}">
                                                    {{ $task->display_title }}
                                                </span>

                                                <!-- Subtask Category Badge (Clickable dropdown) -->
                                                <div class="relative inline-block">
                                                    @php
                                                        $cat = $task->category;
                                                    @endphp
                                                    <button 
                                                        type="button" 
                                                        @click.stop="openCategory = !openCategory; openType = false; openDate = false; openAssignee = false"
                                                        class="inline-flex items-center gap-1 h-6 px-1.5 rounded-control text-meta font-medium text-ink-2 hover:bg-sunken hover:text-ink transition cursor-pointer shrink-0"
                                                        title="{{ __('When completed:') }} {{ $cat->returnActionLabel() }}. {{ __('Click to change') }}">
                                                                                                                <span>{{ $cat->shortReturnLabel() }}</span>
                                                        <x-lucide-chevron-down class="w-3 h-3 text-ink-3" />
                                                    </button>

                                                    <!-- Category Dropdown Menu -->
                                                    <div 
                                                        x-show="openCategory" 
                                                        @click.outside="openCategory = false"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        class="absolute left-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-1.5 min-w-[210px] space-y-1 text-left text-xs">
                                                        <div class="px-2 py-0.5 font-semibold text-meta  text-ink-3 ">
                                                            {{ __('When marked as ready, send to:') }}
                                                        </div>
                                                        @foreach(\App\Enums\SubtaskCategory::cases() as $categoryCase)
                                                            <button 
                                                                type="button"
                                                                wire:click="updateTaskCategory({{ $task->id }}, '{{ $categoryCase->value }}')"
                                                                @click="openCategory = false"
                                                                class="w-full flex items-center justify-between px-2 py-1.5 rounded-control text-xs font-medium transition cursor-pointer hover:bg-sunken {{ $cat === $categoryCase ? 'font-semibold text-ink bg-sunken' : 'text-ink-2' }}">
                                                                <span class="flex items-center gap-2">
                                                                                                                                        <span>{{ $categoryCase->label() }}</span>
                                                                </span>
                                                                @if($cat === $categoryCase)
                                                                    <x-lucide-check class="w-3.5 h-3.5 text-ink stroke-[2.5]" />
                                                                @endif
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <!-- Work vs Admin Badge (Clickable dropdown) -->
                                                <div class="relative inline-block">
                                                    <button 
                                                        type="button" 
                                                        @click.stop="openType = !openType; openDate = false; openAssignee = false"
                                                        class="inline-flex items-center gap-1 h-6 px-1.5 rounded-control text-meta font-medium text-ink-2 hover:bg-sunken hover:text-ink transition cursor-pointer shrink-0"
                                                        title="{{ __('Click to change type (Work / Admin)') }}">
                                                        @if($task->is_work_task !== false)
                                                            <x-lucide-wrench class="w-3.5 h-3.5 text-ink-3" />
                                                            <span>{{ __('Work') }}</span>
                                                        @else
                                                            <x-lucide-clipboard-list class="w-3.5 h-3.5 text-ink-3" />
                                                            <span>{{ __('Admin') }}</span>
                                                        @endif
                                                        <x-lucide-chevron-down class="w-3 h-3 text-ink-3" />
                                                    </button>

                                                    <!-- Type Dropdown Menu -->
                                                    <div 
                                                        x-show="openType" 
                                                        @click.outside="openType = false"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        class="absolute left-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-1.5 min-w-[150px] space-y-1 text-left text-xs">
                                                        <div class="px-2 py-0.5 font-semibold text-meta  text-ink-3 ">
                                                            {{ __('Subtask Type') }}
                                                        </div>
                                                        <button 
                                                            type="button"
                                                            wire:click="updateTaskType({{ $task->id }}, true)"
                                                            @click="openType = false"
                                                            class="w-full flex items-center justify-between px-2 py-1.5 rounded-control text-xs font-medium transition cursor-pointer hover:bg-sunken {{ $task->is_work_task !== false ? 'text-ink-2 font-semibold bg-sunken' : 'text-ink-2' }}">
                                                            <span class="flex items-center gap-1.5">
                                                                <x-lucide-wrench class="w-3.5 h-3.5 text-ink-2" />
                                                                <span>{{ __('Work') }}</span>
                                                            </span>
                                                            @if($task->is_work_task !== false)
                                                                <x-lucide-check class="w-3.5 h-3.5 text-ink-2 stroke-[2.5]" />
                                                            @endif
                                                        </button>
                                                        <button 
                                                            type="button"
                                                            wire:click="updateTaskType({{ $task->id }}, false)"
                                                            @click="openType = false"
                                                            class="w-full flex items-center justify-between px-2 py-1.5 rounded-control text-xs font-medium transition cursor-pointer hover:bg-sunken {{ $task->is_work_task === false ? 'text-ink font-semibold bg-warn-soft' : 'text-ink-2' }}">
                                                            <span class="flex items-center gap-1.5">
                                                                <x-lucide-clipboard-list class="w-3.5 h-3.5 text-ink-3" />
                                                                <span>{{ __('Admin') }}</span>
                                                            </span>
                                                            @if($task->is_work_task === false)
                                                                <x-lucide-check class="w-3.5 h-3.5 text-ink stroke-[2.5]" />
                                                            @endif
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Date Badge (Clickable popover) -->
                                                <div class="relative inline-block">
                                                    <button 
                                                        type="button" 
                                                        @click.stop="openDate = !openDate; openType = false; openAssignee = false"
                                                        class="inline-flex items-center gap-1 h-6 px-1.5 rounded-control text-meta font-medium text-ink-2 hover:bg-sunken hover:text-ink transition cursor-pointer shrink-0"
                                                        title="{{ __('Click to change scheduled date') }}">
                                                        <x-lucide-calendar class="w-3.5 h-3.5 text-ink-3" />
                                                        <span>{{ $task->scheduled_date ? $task->scheduled_date->translatedFormat('d M') : __('+ Date') }}</span>
                                                        <x-lucide-chevron-down class="w-3 h-3 text-ink-3" />
                                                    </button>

                                                    <!-- Date Dropdown Popover -->
                                                    <div 
                                                        x-show="openDate" 
                                                        @click.outside="openDate = false"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        class="absolute left-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-2.5 min-w-[210px] space-y-2 text-left text-xs">
                                                        
                                                        <div class="font-semibold text-meta  text-ink-3 ">
                                                            {{ __('Scheduled Date') }}
                                                        </div>

                                                        <!-- Quick presets: Hoy, Mañana -->
                                                        <div class="grid grid-cols-2 gap-1.5">
                                                            <button 
                                                                type="button"
                                                                wire:click="updateTaskDate({{ $task->id }}, '{{ now()->toDateString() }}')"
                                                                @click="openDate = false"
                                                                class="px-2 py-1 text-meta font-medium rounded-control border text-center transition cursor-pointer {{ $task->scheduled_date?->isToday() ? 'bg-ink text-white border-zinc-900 font-semibold' : 'bg-sunken hover:bg-sunken text-ink-2 border-line' }}">
                                                                {{ __('Today') }}
                                                            </button>
                                                            <button 
                                                                type="button"
                                                                wire:click="updateTaskDate({{ $task->id }}, '{{ now()->addDay()->toDateString() }}')"
                                                                @click="openDate = false"
                                                                class="px-2 py-1 text-meta font-medium rounded-control border text-center transition cursor-pointer {{ $task->scheduled_date?->isTomorrow() ? 'bg-ink text-white border-zinc-900 font-semibold' : 'bg-sunken hover:bg-sunken text-ink-2 border-line' }}">
                                                                {{ __('Tomorrow') }}
                                                            </button>
                                                        </div>

                                                        <!-- Custom Date Input -->
                                                        <div class="space-y-1 pt-1.5 border-t border-line">
                                                            <label class="text-meta text-ink-3 font-medium block">{{ __('Choose another date:') }}</label>
                                                            <input 
                                                                type="date" 
                                                                value="{{ $task->scheduled_date?->toDateString() }}"
                                                                @change="$wire.updateTaskDate({{ $task->id }}, $event.target.value); openDate = false"
                                                                class="w-full bg-canvas border border-line focus:border-line-strong rounded-control px-2 py-1 text-xs text-ink font-mono focus:outline-none">
                                                        </div>

                                                        <!-- Clear Date option if date is set -->
                                                        @if($task->scheduled_date)
                                                            <div class="pt-1 border-t border-line">
                                                                <button 
                                                                    type="button" 
                                                                    wire:click="updateTaskDate({{ $task->id }}, null)"
                                                                    @click="openDate = false"
                                                                    class="w-full text-center py-1 text-meta text-danger hover:text-danger hover:bg-danger-soft rounded-control transition cursor-pointer font-medium">
                                                                    {{ __('Remove scheduled date') }}
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Assignee Badge (Clickable dropdown) -->
                                                <div class="relative inline-block">
                                                    <button 
                                                        type="button" 
                                                        @click.stop="openAssignee = !openAssignee; openType = false; openDate = false"
                                                        class="inline-flex items-center gap-1 h-6 px-1.5 rounded-control text-meta font-medium text-ink-2 hover:bg-sunken hover:text-ink transition cursor-pointer shrink-0"
                                                        title="{{ __('Click to change assignee') }}">
                                                        <x-lucide-user class="w-3.5 h-3.5 text-ink-3" />
                                                        <span>{{ $task->assignee ? $task->assignee->name : __('+ Assign') }}</span>
                                                        <x-lucide-chevron-down class="w-3 h-3 text-ink-3" />
                                                    </button>

                                                    <!-- Assignee Dropdown Popover -->
                                                    <div 
                                                        x-show="openAssignee" 
                                                        @click.outside="openAssignee = false"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        class="absolute left-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-2 min-w-[190px] max-w-[250px] space-y-1.5 text-left text-xs">
                                                        
                                                        <div class="font-semibold text-meta  text-ink-3  px-1">
                                                            {{ __('Assign Rep') }}
                                                        </div>

                                                        @if(count($designers) > 4)
                                                            <div class="px-1">
                                                                <input 
                                                                    type="text" 
                                                                    x-model="assigneeSearch"
                                                                    placeholder="{{ __('Search designer...') }}" 
                                                                    class="w-full bg-canvas border border-line rounded-control px-2 py-1 text-xs text-ink focus:outline-none focus:border-line-strong">
                                                            </div>
                                                        @endif

                                                        <div class="max-h-48 overflow-y-auto space-y-0.5 scrollbar-thin">
                                                            <!-- Unassign option -->
                                                            <button 
                                                                type="button"
                                                                wire:click="updateTaskAssignee({{ $task->id }}, null)"
                                                                @click="openAssignee = false"
                                                                class="w-full flex items-center justify-between px-2 py-1.5 rounded-control text-xs transition cursor-pointer hover:bg-sunken {{ !$task->assignee_id ? 'font-semibold text-ink bg-sunken' : 'text-ink-3' }}">
                                                                <span class="flex items-center gap-1.5">
                                                                    <x-lucide-user-x class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                                                    <span>{{ __('Unassigned') }}</span>
                                                                </span>
                                                                @if(!$task->assignee_id)
                                                                    <x-lucide-check class="w-3.5 h-3.5 text-ink stroke-[2.5] shrink-0" />
                                                                @endif
                                                            </button>

                                                            @foreach($designers as $des)
                                                                <button 
                                                                    type="button"
                                                                    x-show="!assigneeSearch || '{{ strtolower(addslashes($des->name)) }}'.includes(assigneeSearch.toLowerCase())"
                                                                    wire:click="updateTaskAssignee({{ $task->id }}, {{ $des->id }})"
                                                                    @click="openAssignee = false"
                                                                    class="w-full flex items-center justify-between px-2 py-1.5 rounded-control text-xs transition cursor-pointer hover:bg-sunken {{ $task->assignee_id == $des->id ? 'font-semibold text-ink bg-sunken' : 'text-ink-2' }}">
                                                                    <span class="flex items-center gap-1.5 truncate">
                                                                        <x-lucide-user class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                                                                        <span class="truncate">{{ $des->name }}</span>
                                                                    </span>
                                                                    @if($task->assignee_id == $des->id)
                                                                        <x-lucide-check class="w-3.5 h-3.5 text-ink stroke-[2.5] shrink-0" />
                                                                    @endif
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex items-center gap-1 shrink-0">
                                            <!-- Edit Name Button -->
                                            <button 
                                                type="button"
                                                @click="editing = true; $nextTick(() => $refs.editInput.focus())"
                                                class="p-1 rounded-control text-ink-3 hover:text-ink-2 hover:bg-line transition cursor-pointer opacity-80 sm:opacity-0 group-hover:opacity-100" 
                                                title="{{ __('Edit subtask name') }}">
                                                <x-lucide-pencil class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete Subtask Button -->
                                            <button 
                                                wire:click="deleteTask({{ $task->id }})" 
                                                wire:confirm="{{ __('Are you sure you want to delete the subtask') }} '{{ addslashes($task->title) }}'?" 
                                                class="p-1 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer opacity-80 sm:opacity-0 group-hover:opacity-100" 
                                                title="{{ __('Delete subtask') }}">
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Edit Mode View -->
                                    <form 
                                        x-show="editing" 
                                        @submit.prevent="saveTitle()" 
                                        class="flex items-center gap-2 w-full"
                                        x-cloak
                                    >
                                        <input 
                                            x-ref="editInput"
                                            type="text" 
                                            x-model="title"
                                            @keydown.escape="editing = false; title = @js($task->title)"
                                            placeholder="{{ __('Subtask name...') }}"
                                            class="flex-1 bg-surface border border-line-strong rounded-control px-2.5 py-1 text-xs text-ink font-semibold focus:outline-none focus:ring-2 focus:ring-ink/20"
                                        >
                                        <button 
                                            type="submit" 
                                            class="px-2.5 py-1 rounded-control bg-ink hover:bg-ink text-white font-semibold text-xs  transition flex items-center gap-1 cursor-pointer">
                                            <x-lucide-check class="w-3.5 h-3.5" />
                                            <span>{{ __('Save') }}</span>
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="editing = false; title = @js($task->title)" 
                                            class="p-1 rounded-control text-ink-3 hover:text-ink-2 hover:bg-line transition cursor-pointer"
                                            title="{{ __('Cancel') }}">
                                            <x-lucide-x class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="p-4 text-center text-xs text-ink-3 bg-canvas rounded-card border border-line">
                                    {{ __('No subtasks created yet.') }}
                                </div>
                            @endforelse
                        </div>

                        <!-- Add Manual Subtask Form -->
                        <div 
                            x-data="{
                                presetDropdownOpen: false,
                                presetHighlightedIndex: -1,
                                subtaskPresetsList: @js($presetDataList),
                                subtaskTitle: @entangle('newTaskTitle'),
                                getFilteredPresets() {
                                    const list = this.subtaskPresetsList || [];
                                    const q = (this.subtaskTitle || '').toLowerCase().trim();
                                    if (!q) return list;
                                    return list.filter(p => p.title.toLowerCase().includes(q));
                                },
                                selectPreset(preset) {
                                    this.subtaskTitle = preset.title;
                                    if (preset.is_work_task !== undefined && preset.is_work_task !== null) {
                                        $wire.set('newTaskIsWork', Boolean(preset.is_work_task));
                                    }
                                    if (preset.category) {
                                        $wire.set('newTaskCategory', preset.category);
                                    }
                                    this.presetDropdownOpen = false;
                                    this.presetHighlightedIndex = -1;
                                },
                                navigatePreset(step) {
                                    const list = this.getFilteredPresets();
                                    if (!list.length) return;
                                    if (this.presetHighlightedIndex === -1) {
                                        this.presetHighlightedIndex = step > 0 ? 0 : list.length - 1;
                                    } else {
                                        this.presetHighlightedIndex = (this.presetHighlightedIndex + step + list.length) % list.length;
                                    }
                                    this.$nextTick(() => {
                                        const container = this.$refs.presetDropdownPanel;
                                        if (container) {
                                            const items = container.querySelectorAll('.preset-item-btn');
                                            if (items[this.presetHighlightedIndex]) {
                                                items[this.presetHighlightedIndex].scrollIntoView({ block: 'nearest' });
                                            }
                                        }
                                    });
                                }
                            }"
                            @click.outside="presetDropdownOpen = false"
                            class="bg-canvas border border-line rounded-card p-3 space-y-2.5  relative">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-body font-medium text-ink">{{ __('Add subtask') }}</span>
                                <div class="flex items-center gap-1.5 text-xs">
                                    <!-- Destination Selector: When marked as done, send to... -->
                                    <div x-data="{ 
                                        open: false, 
                                        selected: @entangle('newTaskCategory'),
                                        options: {
                                            '': { label: '{{ __('On finish: Auto') }}', dot: 'bg-ink-3' },
                                            'new_design': { label: '{{ __('Return to previous status') }}', dot: 'bg-ink-3' },
                                            'client_adjustments': { label: '{{ __('Sent to Client') }}', dot: 'bg-ink-3' },
                                            'camila_adjustments': { label: '{{ __('Sent to Camila') }}', dot: 'bg-ink-3' },
                                            'production_adjustments': { label: '{{ __('In Production') }}', dot: 'bg-ink-3' },
                                            'management': { label: '{{ __('Do not move order') }}', dot: 'bg-ink-3' }
                                        }
                                    }" class="relative">
                                        <button 
                                            type="button" 
                                            @click="open = !open" 
                                            class="ui-btn ui-btn-secondary ui-btn-sm"
                                            title="{{ __('When marked as ready, send order to...') }}">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="options[selected]?.dot || 'bg-ink-3'"></span>
                                            <span x-text="options[selected]?.label || '{{ __('On finish: Auto') }}'"></span>
                                            <x-lucide-chevron-down class="w-3 h-3 text-ink-3 transition-transform duration-200" ::class="{ 'rotate-180': open }" />
                                        </button>
                                        <div 
                                            x-show="open" 
                                            @click.outside="open = false" 
                                            x-cloak 
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute right-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-1.5 min-w-[200px] space-y-0.5 text-left text-xs">
                                            <template x-for="(opt, key) in options" :key="key">
                                                <button 
                                                    type="button" 
                                                    @click="selected = key; open = false" 
                                                    class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-control text-xs font-medium transition cursor-pointer hover:bg-sunken" 
                                                    :class="selected === key ? 'font-semibold text-ink bg-sunken' : 'text-ink-2'">
                                                    <span class="flex items-center gap-2">
                                                        <span class="w-2 h-2 rounded-full shrink-0" :class="opt.dot"></span>
                                                        <span x-text="opt.label"></span>
                                                    </span>
                                                    <span x-show="selected === key">
                                                        <x-lucide-check class="w-3.5 h-3.5 text-ink stroke-[2.5]" />
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Work vs Admin -->
                                    <button
                                        type="button"
                                        wire:click="$set('newTaskIsWork', true)"
                                        class="ui-chip"
                                        aria-pressed="{{ $newTaskIsWork ? 'true' : 'false' }}"
                                        title="{{ __('Design/production work') }}">
                                        <span>{{ __('Work') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('newTaskIsWork', false)"
                                        class="ui-chip"
                                        aria-pressed="{{ !$newTaskIsWork ? 'true' : 'false' }}"
                                        title="{{ __('Administrative management/follow-up') }}">
                                        <span>{{ __('Admin') }}</span>
                                    </button>
                                </div>
                            </div>

                            @if($order && ($order->core_status === \App\Enums\CoreStatus::ENVIADO_AL_CLIENTE || $order->origin_core_status === \App\Enums\CoreStatus::ENVIADO_AL_CLIENTE))
                                <div class="flex items-center gap-1.5 text-meta text-ink-2 bg-sunken border border-line px-2 py-1 rounded-control font-medium">
                                    <x-lucide-corner-down-left class="w-3 h-3 text-ink-2 shrink-0" />
                                    <span>{{ __('Upon completing today\'s subtasks, this order will automatically return to') }} <strong>{{ __('Sent to Client') }}</strong>.</span>
                                </div>
                            @elseif($order && ($order->core_status === \App\Enums\CoreStatus::ENVIADO_A_CAMILA || $order->origin_core_status === \App\Enums\CoreStatus::ENVIADO_A_CAMILA))
                                <div class="flex items-center gap-1.5 text-meta text-ink-2 bg-sunken border border-line px-2 py-1 rounded-control font-medium">
                                    <x-lucide-corner-down-left class="w-3 h-3 text-ink-2 shrink-0" />
                                    <span>{{ __('Upon completing today\'s subtasks, this order will automatically return to') }} <strong>{{ __('Sent to Camila') }}</strong>.</span>
                                </div>
                            @elseif($order && ($order->core_status === \App\Enums\CoreStatus::EN_PRODUCCION || $order->origin_core_status === \App\Enums\CoreStatus::EN_PRODUCCION))
                                <div class="flex items-center gap-1.5 text-meta text-ink-2 bg-sunken border border-line px-2 py-1 rounded-control font-medium">
                                    <x-lucide-corner-down-left class="w-3 h-3 text-ink-2 shrink-0" />
                                    <span>{{ __('Upon completing today\'s subtasks, this order will automatically return to') }} <strong>{{ __('In Production') }}</strong>.</span>
                                </div>
                            @endif

                            <div class="flex flex-col sm:flex-row items-center gap-2">
                                <div class="relative flex-1 w-full min-w-0">
                                    <input 
                                        type="text" 
                                        x-model="subtaskTitle"
                                        @focus="presetDropdownOpen = true"
                                        @input="presetDropdownOpen = true; presetHighlightedIndex = -1;"
                                        @keydown.arrow-down.prevent="if(!presetDropdownOpen) presetDropdownOpen = true; else navigatePreset(1);"
                                        @keydown.arrow-up.prevent="if(!presetDropdownOpen) presetDropdownOpen = true; else navigatePreset(-1);"
                                        @keydown.enter.prevent="if (presetDropdownOpen && presetHighlightedIndex >= 0 && getFilteredPresets()[presetHighlightedIndex]) { selectPreset(getFilteredPresets()[presetHighlightedIndex]); } else { $wire.addTask(); presetDropdownOpen = false; }"
                                        @keydown.escape.stop="presetDropdownOpen = false; presetHighlightedIndex = -1;"
                                        placeholder="{{ __('Subtask name (e.g., Client adjustments, Confirm measurements...)') }}" 
                                        class="ui-input">

                                    <!-- Dropdown Menu for Subtask Presets -->
                                    <div 
                                        x-ref="presetDropdownPanel"
                                        x-show="presetDropdownOpen && getFilteredPresets().length > 0"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                        class="absolute left-0 top-full mt-1 z-50 bg-surface/95 backdrop-blur-xs border border-line rounded-control shadow-menu max-h-48 overflow-y-auto p-1 text-meta min-w-[220px] w-full max-w-md space-y-0.5 divide-y divide-line"
                                        style="display: none;">
                                        <div class="px-2 py-1 text-meta font-semibold text-ink-3   flex items-center justify-between select-none pb-0.5">
                                            <span>{{ __('Templates') }}</span>
                                            <span class="ui-kbd">↑↓ Enter</span>
                                        </div>
                                        <div class="pt-0.5 space-y-0.5">
                                            <template x-for="(preset, idx) in getFilteredPresets()" :key="preset.id || idx">
                                                <button 
                                                    type="button" 
                                                    @click="selectPreset(preset)" 
                                                    @mouseenter="presetHighlightedIndex = idx"
                                                    :class="{ 'bg-sunken text-ink font-semibold': presetHighlightedIndex === idx, 'text-ink-2 hover:bg-sunken': presetHighlightedIndex !== idx }"
                                                    class="preset-item-btn w-full text-left px-2 py-1 rounded-control transition flex items-center justify-between gap-2 cursor-pointer group">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span 
                                                            class="text-body text-ink shrink-0 inline-flex items-center gap-1">
                                                            <span x-text="preset.title"></span>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <span 
                                                            class="text-meta text-ink-3">
                                                            <span x-text="preset.is_work_task ? '{{ __('Work') }}' : '{{ __('Admin') }}'"></span>
                                                        </span>
                                                        <span class="text-meta text-ink-3 opacity-0 group-hover:opacity-100 transition">
                                                            ↵
                                                        </span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 w-full sm:w-auto shrink-0">
                                    <input 
                                        type="date" 
                                        wire:model="newTaskDate" 
                                        class="ui-input w-auto shrink-0">
                                    <button 
                                        type="button" 
                                        wire:click="addTask" 
                                        @click="presetDropdownOpen = false"
                                        class="ui-btn ui-btn-secondary">
                                        <x-lucide-plus class="w-3.5 h-3.5" />
                                        <span>{{ __('Add') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- Closes TAB 1: PIEZAS+SUBTASKS -->

                <!-- TAB 3: TIMELINE -->
                <div x-show="activeTab === 'timeline'" class="space-y-4" x-cloak>
                    <livewire:orders.order-timeline :order-id="$order->id" :key="'order-timeline-'.$order->id" lazy="on-load" />
                    </div>

                <!-- TAB 2: COMMENTS + ADJUNTOS -->
                <div x-show="activeTab === 'comments'" class="space-y-6" x-cloak>
                    <!-- TRELLO INTEGRATION SECTION (DESCRIPTION, ATTACHMENTS & COMMENTS) -->
                    <div class="space-y-4">
                        <!-- Recycled Section Header -->
                        <x-orders.tab-section-header :title="__('Trello information & files')">
                            <x-slot:icon>
                                <x-lucide-message-square class="w-4.5 h-4.5 text-sky-600 shrink-0" />
                            </x-slot:icon>
                            @if($order->trello_card_id)
                                <x-slot:actions>
                                    <button wire:click="refreshTrelloData" type="button" class="text-xs text-sky-700 hover:text-sky-900 font-medium flex items-center gap-1 transition cursor-pointer" title="{{ __('Update Trello data') }}">
                                        <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refreshTrelloData,loadTrelloComments,loadTrelloDetails" class="w-3.5 h-3.5" />
                                        <span>{{ __('Update Trello') }}</span>
                                    </button>
                                </x-slot:actions>
                            @endif
                        </x-orders.tab-section-header>

                    @if(!$order->trello_card_id)
                        <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200/80 text-xs text-blue-900 flex items-center justify-between gap-3 shadow-2xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-lucide-info class="w-4 h-4 text-blue-500 shrink-0" />
                                <span class="truncate">{{ __('This order does not have a linked Trello card.') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button 
                                    wire:click="createCardOnTrello" 
                                    wire:loading.attr="disabled"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-2xs transition cursor-pointer disabled:opacity-50">
                                    <x-lucide-plus-circle wire:loading.class="hidden" wire:target="createCardOnTrello" class="w-3.5 h-3.5 shrink-0" />
                                    <x-lucide-loader-2 wire:loading wire:target="createCardOnTrello" class="w-3.5 h-3.5 animate-spin shrink-0" />
                                    <span>{{ __('Create Card in Trello') }}</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <!-- TRELLO DESCRIPTION BLOCK -->
                        <div class="bg-canvas border border-[#e9e9e7] rounded-xl p-4 space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                                <h5 class="font-bold text-[13px] text-zinc-900 flex items-center gap-1.5">
                                    <x-lucide-align-left class="w-4 h-4 text-sky-600 shrink-0" />
                                    <span>{{ __('Card Description') }}</span>
                                </h5>
                                @if($isLoadingTrelloDetails)
                                    <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin text-sky-600" />
                                @endif
                            </div>

                            @if($isLoadingTrelloDetails)
                                <div class="py-3 text-center text-xs text-zinc-400 flex items-center justify-center gap-2 font-medium">
                                    <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin text-sky-600" />
                                    <span>{{ __('Loading description from Trello...') }}</span>
                                </div>
                            @elseif($trelloDetailsError)
                                <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2">
                                    <x-lucide-alert-circle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                    <span>{{ $trelloDetailsError }}</span>
                                </div>
                            @elseif(!empty(trim($trelloDescription)))
                                @php
                                    $formattedDesc = preg_replace_callback('/!\[([^\]]*)\]\(([^\)]+)\)/', function($matches) {
                                        $url = trim($matches[2]);
                                        $alt = trim($matches[1]);
                                        $label = !empty($alt) ? $alt : (basename(parse_url($url, PHP_URL_PATH) ?: __('File')));
                                        return "[$label]($url)";
                                    }, $trelloDescription);

                                    $formattedDesc = preg_replace_callback('/^([ \t]*)(?:[-*+]|\d+[\.\)])?[ \t]*\[([ \t]*|[xX])\][ \t]*(.*)$/m', function ($matches) {
                                        $indent = $matches[1];
                                        $check = strtolower(trim($matches[2]));
                                        $marker = ($check === 'x') ? 'x' : ' ';
                                        $content = trim($matches[3]);
                                        if ($content === '') {
                                            $content = '&nbsp;';
                                        }

                                        return "{$indent}- [{$marker}] {$content}";
                                    }, $formattedDesc);
                                    $htmlDesc = \Illuminate\Support\Str::markdown($formattedDesc, ['html_input' => 'strip', 'allow_unsafe_links' => false], [new \League\CommonMark\Extension\TaskList\TaskListExtension()]);
                                    $htmlDesc = preg_replace_callback('/<li[^>]*>\s*(?:<p>)?\s*<input([^>]*?)type=[\'"]checkbox[\'"]([^>]*?)>(.*?)(?:<\/p>)?\s*<\/li>/is', function ($matches) {
                                        $isChecked = (bool) preg_match('/checked/i', $matches[1].' '.$matches[2]);
                                        $content = trim($matches[3]);
                                        $badge = $isChecked 
                                            ? '<span class="mt-0.5 w-4 h-4 rounded border border-emerald-600 bg-emerald-600 flex items-center justify-center shrink-0 shadow-2xs select-none"><svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></span>'
                                            : '<span class="mt-0.5 w-4 h-4 rounded border border-stone-300 bg-white flex items-center justify-center shrink-0 shadow-2xs select-none"></span>';
                                        $textClass = $isChecked ? 'text-zinc-400 line-through' : 'text-zinc-800';
                                        return '<li class="task-list-item not-prose list-none flex items-start gap-2.5 my-1.5 select-text">' . $badge . '<span class="' . $textClass . ' leading-snug break-words">' . $content . '</span></li>';
                                    }, $htmlDesc);
                                    $htmlDesc = preg_replace('/<ul>(?=\s*<li class="task-list-item)/is', '<ul class="task-list list-none pl-0 my-1.5 space-y-1.5">', $htmlDesc);
                                    $htmlDesc = preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=([\'"])(.*?)\1[^>]*>(.*?)<\/a>/i', function($matches) {
                                        $url = html_entity_decode($matches[2]);
                                        $rawLabel = strip_tags($matches[3]);
                                        $label = !empty(trim($rawLabel)) ? trim($rawLabel) : __('File');
                                        $escapedUrl = addslashes($url);
                                        $escapedLabel = addslashes($label);
                                        return '<button type="button" wire:click="openMediaPreview(\'' . $escapedUrl . '\', \'' . $escapedLabel . '\')" class="text-sky-600 font-semibold underline hover:text-sky-800 transition cursor-pointer inline-flex items-center gap-1 my-0.5">' . $matches[3] . ' <svg class="w-3 h-3 inline-block shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></button>';
                                    }, $htmlDesc);
                                @endphp
                                <div class="text-xs text-zinc-800 leading-relaxed bg-white border border-stone-200/80 rounded-lg p-3 max-h-60 overflow-y-auto prose prose-xs prose-stone max-w-none shadow-2xs [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-0.5 [&_ul.task-list]:list-none [&_ul.task-list]:pl-0 [&_li.task-list-item]:list-none">
                                    {!! $htmlDesc !!}
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-stone-100/70 border border-stone-200 text-xs text-zinc-400 italic">
                                    {{ __('No description on the Trello card.') }}
                                </div>
                            @endif
                        </div>

                        <!-- TRELLO ATTACHMENTS BLOCK -->
                        <div 
                            x-data="{ 
                                isDragging: false, 
                                dragCounter: 0,
                                handleDrop(event) {
                                    this.isDragging = false;
                                    this.dragCounter = 0;
                                    const dt = event.dataTransfer;
                                    if (!dt || !dt.files || dt.files.length === 0) return;

                                    let oversized = [];
                                    for (let i = 0; i < dt.files.length; i++) {
                                        if (dt.files[i].size > 10 * 1024 * 1024) {
                                            oversized.push(dt.files[i].name);
                                        }
                                    }
                                    if (oversized.length > 0) {
                                        $wire.dispatch('toast', { message: '{{ __('The file exceeds 10 MB (Trello limit): ') }}' + oversized.join(', ') });
                                        return;
                                    }

                                    const input = $refs.multiAttachmentInput;
                                    if (input) {
                                        input.files = dt.files;
                                        input.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            }"
                            x-on:dragenter.prevent="dragCounter++; isDragging = true"
                            x-on:dragleave.prevent="dragCounter--; if (dragCounter <= 0) { isDragging = false; dragCounter = 0; }"
                            x-on:dragover.prevent="isDragging = true"
                            x-on:drop.prevent="handleDrop($event)"
                            class="relative bg-canvas border border-[#e9e9e7] rounded-xl p-4 space-y-3 shadow-2xs transition-all duration-200"
                            :class="{ 'ring-2 ring-sky-500 border-sky-400 bg-sky-50/25': isDragging }"
                        >
                            {{-- Animated Drag & Drop Overlay --}}
                            <div 
                                x-show="isDragging" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute inset-0 z-20 rounded-xl bg-sky-50/95 backdrop-blur-[2px] border-2 border-dashed border-sky-500 flex flex-col items-center justify-center p-6 text-center shadow-lg pointer-events-none"
                                style="display: none;"
                            >
                                <div class="w-12 h-12 rounded-full bg-sky-100 border border-sky-300 flex items-center justify-center text-sky-600 mb-2 animate-bounce">
                                    <x-lucide-upload-cloud class="w-6 h-6" />
                                </div>
                                <p class="text-sm font-bold text-sky-950">{{ __('Drop files here to attach them') }}</p>
                                <p class="text-xs text-sky-700 mt-1 max-w-sm">{{ __('They will be attached automatically to the Trello card (you can drop several files, max. 10 MB each)') }}</p>
                            </div>

                            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                                <h5 class="font-bold text-[13px] text-zinc-900 flex items-center gap-1.5">
                                    <x-lucide-paperclip class="w-4 h-4 text-sky-600 shrink-0" />
                                    <span>{{ __('Attachments') }}</span>
                                    @if(!empty($trelloAttachments))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-100 text-sky-800 border border-sky-200">
                                            {{ count($trelloAttachments) }}
                                        </span>
                                    @endif
                                </h5>

                                <div class="flex items-center gap-2">
                                    @if($isLoadingTrelloDetails || $isUploadingAttachment)
                                        <div class="flex items-center gap-1.5 text-[11px] text-sky-600 font-medium">
                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin shrink-0" />
                                            <span class="hidden sm:inline">{{ $isUploadingAttachment ? __('Uploading to Trello...') : __('Loading...') }}</span>
                                        </div>
                                    @endif

                                    @if(!empty($trelloAttachments))
                                        <button 
                                            type="button"
                                            wire:click="downloadAllAttachments"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-stone-50 text-zinc-700 hover:text-zinc-900 border border-stone-200/90 shadow-2xs cursor-pointer transition select-none disabled:opacity-50"
                                            title="{{ __('Download all attachments as a ZIP file') }}"
                                        >
                                            <x-lucide-download class="w-3.5 h-3.5 text-zinc-500 shrink-0" wire:loading.remove wire:target="downloadAllAttachments" />
                                            <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin text-sky-600 shrink-0" wire:loading wire:target="downloadAllAttachments" />
                                            <span>{{ __('Download all') }}</span>
                                        </button>
                                    @endif

                                    @if($order && $order->trello_card_id)
                                        <button 
                                            type="button"
                                            @click="$refs.multiAttachmentInput.value = ''; $refs.multiAttachmentInput.click()"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-sky-50 text-sky-700 hover:text-sky-800 border border-sky-200/90 shadow-2xs cursor-pointer transition select-none disabled:opacity-50"
                                            @disabled($isUploadingAttachment)
                                        >
                                            <x-lucide-upload class="w-3.5 h-3.5 text-sky-600 shrink-0" />
                                            <span>{{ __('Upload files') }}</span>
                                        </button>
                                        <input 
                                            type="file" 
                                            x-ref="multiAttachmentInput"
                                            wire:model="attachmentFiles" 
                                            multiple
                                            class="hidden" 
                                            @disabled($isUploadingAttachment)
                                        >
                                    @endif
                                </div>
                            </div>

                            {{-- Livewire loading indicator during file upload --}}
                            <div wire:loading.flex wire:target="attachmentFiles, attachmentFile" class="flex flex-row items-center gap-2.5 py-2.5 px-3 rounded-lg bg-sky-50 border border-sky-200 text-xs text-sky-800 shadow-2xs" style="display: none;">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin text-sky-600 shrink-0 inline-block" />
                                <span class="leading-snug font-medium">{{ __('Uploading files and syncing with the Trello card...') }}</span>
                            </div>

                            @if($attachmentUploadError)
                                <div 
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-init="setTimeout(() => { show = false; $wire.clearAttachmentErrors(); }, 5000)"
                                    x-transition:leave="transition ease-in duration-300"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between gap-2 shadow-2xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-lucide-alert-circle class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span class="truncate whitespace-pre-line">{{ $attachmentUploadError }}</span>
                                    </div>
                                    <button type="button" @click="show = false; $wire.clearAttachmentErrors()" class="text-rose-500 hover:text-rose-800 text-xs font-semibold shrink-0 cursor-pointer">✕</button>
                                </div>
                            @endif

                            @error('attachmentFiles')
                                <div 
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-init="setTimeout(() => { show = false; $wire.clearAttachmentErrors(); }, 5000)"
                                    x-transition:leave="transition ease-in duration-300"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between gap-2 shadow-2xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-lucide-alert-circle class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span>{{ $message === 'The attachmentFiles failed to upload.' ? __('The files couldn\'t be uploaded. Check that they don\'t exceed the allowed limit.') : $message }}</span>
                                    </div>
                                    <button type="button" @click="show = false; $wire.clearAttachmentErrors()" class="text-rose-500 hover:text-rose-800 text-xs font-semibold shrink-0 cursor-pointer">✕</button>
                                </div>
                            @enderror

                            @error('attachmentFiles.*')
                                <div 
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-init="setTimeout(() => { show = false; $wire.clearAttachmentErrors(); }, 5000)"
                                    x-transition:leave="transition ease-in duration-300"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between gap-2 shadow-2xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-lucide-alert-circle class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span>{{ $message }}</span>
                                    </div>
                                    <button type="button" @click="show = false; $wire.clearAttachmentErrors()" class="text-rose-500 hover:text-rose-800 text-xs font-semibold shrink-0 cursor-pointer">✕</button>
                                </div>
                            @enderror

                            @error('attachmentFile')
                                <div 
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-init="setTimeout(() => { show = false; $wire.clearAttachmentErrors(); }, 5000)"
                                    x-transition:leave="transition ease-in duration-300"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between gap-2 shadow-2xs"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-lucide-alert-circle class="w-4 h-4 text-rose-600 shrink-0" />
                                        <span>{{ $message === 'The attachmentFile failed to upload.' ? __('The file couldn\'t be uploaded. Check that it doesn\'t exceed the allowed limit.') : $message }}</span>
                                    </div>
                                    <button type="button" @click="show = false; $wire.clearAttachmentErrors()" class="text-rose-500 hover:text-rose-800 text-xs font-semibold shrink-0 cursor-pointer">✕</button>
                                </div>
                            @enderror

                            @if($isLoadingTrelloDetails)
                                <div class="py-3 text-center text-xs text-zinc-400 flex items-center justify-center gap-2 font-medium">
                                    <x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin text-sky-600" />
                                    <span>{{ __('Loading attachments from Trello...') }}</span>
                                </div>
                            @elseif(empty($trelloAttachments))
                                @if($order && $order->trello_card_id)
                                    <div 
                                        @click="$refs.multiAttachmentInput.value = ''; $refs.multiAttachmentInput.click()"
                                        class="group p-6 rounded-xl bg-white/70 hover:bg-sky-50/40 border-2 border-dashed border-stone-200 hover:border-sky-400 text-center space-y-2 cursor-pointer transition shadow-2xs"
                                    >
                                        <div class="w-10 h-10 rounded-full bg-stone-100 group-hover:bg-sky-100 text-zinc-400 group-hover:text-sky-600 flex items-center justify-center mx-auto transition">
                                            <x-lucide-upload-cloud class="w-5 h-5" />
                                        </div>
                                        <p class="text-xs font-semibold text-zinc-700 group-hover:text-sky-800 transition">
                                            {{ __('Drag and drop your files here') }}
                                            <span class="text-sky-600 underline ml-1 font-medium">{{ __('or click to browse') }}</span>
                                        </p>
                                        <p class="text-[11px] text-zinc-400">
                                            {{ __('You can drop multiple files (images, plans, PDFs, etc. — max. 10 MB each)') }}
                                        </p>
                                @else
                                    <div class="p-4 rounded-xl bg-stone-50/80 border border-dashed border-stone-300 text-center">
                                        <p class="text-xs text-zinc-500 italic">{{ __('No attachments on the Trello card.') }}</p>
                                    </div>
                                @endif
                            @else
                                <div class="space-y-2.5">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                                        @foreach($trelloAttachments as $attachment)
                                            @if(!is_array($attachment))
                                                @continue
                                            @endif
                                            @php
                                                $isImage = !empty($attachment['previews']) || (isset($attachment['mimeType']) && str_starts_with($attachment['mimeType'], 'image/')) || preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $attachment['name'] ?? '');
                                                $previewUrl = null;
                                                if ($isImage && !empty($attachment['previews'])) {
                                                    $previewUrl = $attachment['previews'][min(2, count($attachment['previews']) - 1)]['url'] ?? $attachment['url'];
                                                } elseif ($isImage) {
                                                    $previewUrl = $attachment['url'];
                                                }

                                                $bytes = $attachment['bytes'] ?? null;
                                                $sizeFormatted = null;
                                                if ($bytes && $bytes > 0) {
                                                    $units = ['B', 'KB', 'MB', 'GB'];
                                                    $i = (int) floor(log($bytes, 1024));
                                                    $sizeFormatted = round($bytes / pow(1024, $i), 1) . ' ' . ($units[$i] ?? 'B');
                                                }

                                                $directDownloadUrl = route('trello.attachment-proxy', ['url' => $attachment['url'], 'download' => 1, 'filename' => $attachment['name'] ?? __('file')]);
                                            @endphp
                                            
                                            <div 
                                                wire:click="openMediaPreview('{{ addslashes($attachment['url']) }}', '{{ addslashes($attachment['name'] ?? __('File')) }}')" 
                                                x-data="{ imgError: false }" 
                                                class="group bg-white hover:bg-stone-50/60 border border-stone-200/90 hover:border-sky-300 rounded-lg overflow-hidden flex flex-col transition-all duration-200 shadow-2xs hover:shadow-xs cursor-pointer relative"
                                            >
                                                {{-- Thumbnail on top --}}
                                                <div class="relative w-full aspect-[4/3] bg-stone-100/90 border-b border-stone-100 overflow-hidden flex items-center justify-center group/thumb">
                                                    @if($isImage && $previewUrl)
                                                        @php
                                                            $proxiedUrl = route('trello.attachment-proxy', ['url' => $previewUrl]);
                                                        @endphp
                                                        <template x-if="!imgError">
                                                            <img src="{{ $proxiedUrl }}" x-on:error="imgError = true" alt="{{ $attachment['name'] ?? __('Attachment') }}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                                                        </template>
                                                        <template x-if="imgError">
                                                            <div class="w-full h-full bg-stone-100 text-stone-400 flex flex-col items-center justify-center gap-1">
                                                                <x-lucide-image class="w-6 h-6 stroke-[1.5]" />
                                                                <span class="text-[9px] text-stone-400 font-medium">{{ __('Image') }}</span>
                            </div>
                                                        </template>
                                                        <div class="absolute inset-0 bg-sky-950/20 backdrop-blur-[0.5px] opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center pointer-events-none">
                                                            <div class="w-6 h-6 rounded-full bg-white/95 text-sky-700 flex items-center justify-center shadow-xs">
                                                                <x-lucide-maximize-2 class="w-3 h-3" />
                                                            </div>
                                                        </div>
                                                    @elseif(isset($attachment['mimeType']) && str_contains($attachment['mimeType'], 'pdf'))
                                                        <div class="w-full h-full bg-rose-50/60 text-rose-500 flex flex-col items-center justify-center gap-1 p-2">
                                                            <x-lucide-file-text class="w-6 h-6 stroke-[1.5]" />
                                                            <span class="text-[9px] font-bold uppercase tracking-wider text-rose-600 bg-rose-100/80 px-1.5 py-0.2 rounded">PDF</span>
                                                        </div>
                                                    @elseif($isImage)
                                                        <div class="w-full h-full bg-sky-50/60 text-sky-600 flex flex-col items-center justify-center gap-1 p-2">
                                                            <x-lucide-image class="w-6 h-6 stroke-[1.5]" />
                                                            <span class="text-[9px] font-bold uppercase tracking-wider text-sky-700 bg-sky-100/80 px-1.5 py-0.2 rounded">IMG</span>
                                                        </div>
                                                    @else
                                                        <div class="w-full h-full bg-stone-50 text-zinc-400 flex flex-col items-center justify-center gap-1 p-2">
                                                            <x-lucide-file class="w-6 h-6 stroke-[1.5]" />
                                                            <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-500 bg-stone-200/70 px-1.5 py-0.2 rounded">DOC</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Ribbon below with subtle details and smaller buttons --}}
                                                <div class="p-2 bg-white flex flex-col gap-1 flex-1 justify-between">
                                                    <p class="text-[11px] font-semibold text-zinc-800 group-hover:text-sky-700 truncate leading-snug transition-colors" title="{{ $attachment['name'] ?? __('File') }}">
                                                        {{ $attachment['name'] ?? __('File') }}
                                                    </p>

                                                    <div class="flex items-center justify-between gap-1 pt-1 border-t border-stone-100">
                                                        <div class="flex items-center gap-1 text-[9px] text-zinc-400 min-w-0">
                                                            @if($sizeFormatted)
                                                                <span class="font-mono text-[9px] text-zinc-500 bg-stone-100/90 border border-stone-200/60 px-1 py-0.2 rounded font-medium shrink-0">{{ $sizeFormatted }}</span>
                                                            @endif
                                                            @if(!empty($attachment['date']))
                                                                <span class="truncate">{{ \Carbon\Carbon::parse($attachment['date'])->translatedFormat('d M') }}</span>
                                                            @endif
                                                        </div>

                                                        <div class="flex items-center gap-0.5 shrink-0" @click.stop>
                                                            <a 
                                                                href="{{ $directDownloadUrl }}" 
                                                                download="{{ $attachment['name'] ?? __('file') }}" 
                                                                class="p-0.5 rounded text-zinc-400 hover:text-sky-600 hover:bg-sky-50 transition cursor-pointer" 
                                                                title="{{ __('Download') }}"
                                                            >
                                                                <x-lucide-download class="w-3 h-3" />
                                                            </a>
                                                            <button 
                                                                type="button" 
                                                                wire:click="openMediaPreview('{{ addslashes($attachment['url']) }}', '{{ addslashes($attachment['name'] ?? __('File')) }}')" 
                                                                class="p-0.5 rounded text-zinc-400 hover:text-sky-600 hover:bg-sky-50 transition cursor-pointer" 
                                                                title="{{ __('Preview') }}"
                                                            >
                                                                <x-lucide-maximize-2 class="w-3 h-3" />
                                                            </button>
                                                            @if(!empty($attachment['id']))
                                                                <button 
                                                                    type="button" 
                                                                    wire:click="deleteAttachment('{{ $attachment['id'] }}', '{{ addslashes($attachment['name'] ?? __('File')) }}')" 
                                                                    wire:confirm="{{ __('Are you sure you want to delete this attachment from the Trello card?') }}" 
                                                                    class="p-0.5 rounded text-zinc-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer opacity-70 group-hover:opacity-100" 
                                                                    title="{{ __('Delete') }}"
                                                                >
                                                                    <x-lucide-trash-2 class="w-3 h-3" />
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if($order && $order->trello_card_id)
                                        <div 
                                            @click="$refs.multiAttachmentInput.click()"
                                            class="group border border-dashed border-stone-300 hover:border-sky-400 bg-stone-50/60 hover:bg-sky-50/50 rounded-lg py-2 px-3 text-center cursor-pointer transition flex items-center justify-center gap-2 text-xs text-zinc-500 hover:text-sky-700 font-medium"
                                        >
                                            <x-lucide-upload class="w-3.5 h-3.5 text-zinc-400 group-hover:text-sky-600 transition" />
                                            <span>{{ __('Drag more files here or click to add') }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- TRELLO COMMENTS BLOCK -->
                        <div class="bg-canvas border border-[#e9e9e7] rounded-xl p-4 space-y-4 shadow-2xs">
                            <div class="flex items-center justify-between border-b border-[#e9e9e7] pb-2">
                                <h5 class="font-bold text-[13px] text-zinc-900 flex items-center gap-1.5">
                                    <x-lucide-message-square class="w-4 h-4 text-sky-600 shrink-0" />
                                    <span>{{ __('Comments') }}</span>
                                    @if(!empty($trelloComments))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-100 text-sky-800 border border-sky-200">
                                            {{ count($trelloComments) }}
                                        </span>
                                    @endif
                                </h5>
                            </div>
                            <!-- Add Comment Form with Modern Rich Editor -->
                            <div 
                                wire:ignore
                                class="relative bg-white border border-[#e9e9e7] focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/20 rounded-xl transition-all duration-150 shadow-2xs overflow-hidden" 
                                x-on:livewire-upload-start="isUploadingPhotos = true"
                                x-on:livewire-upload-finish="isUploadingPhotos = false"
                                x-on:livewire-upload-error="isUploadingPhotos = false"
                                x-data="{
                                    isBold: false,
                                    isItalic: false,
                                    isChecklist: false,
                                    isOrderedList: false,
                                    isUnorderedList: false,
                                    isH1: false,
                                    isH2: false,
                                    isEmpty: true,
                                    isSubmitting: false,
                                    isDraggingPhotos: false,
                                    isUploadingPhotos: false,
                                    activePhotoUploads: 0,
                                    stagedPhotos: [],

                                    init() {
                                        this.syncFromLivewire();
                                        this.updateEmptyState();

                                        this.$watch('$wire.newTrelloComment', (val) => {
                                            if (!val || val.trim() === '') {
                                                if (this.$refs.editor && this.$refs.editor.innerHTML !== '' && document.activeElement !== this.$refs.editor) {
                                                    this.$refs.editor.innerHTML = '';
                                                    this.updateEmptyState();
                                                    this.updateActiveFormatting();
                                                }
                                            }
                                        });

                                        this.$watch('$wire.orderId', () => {
                                            if (this.$refs.editor) {
                                                this.$refs.editor.innerHTML = '';
                                                this.updateEmptyState();
                                                this.updateActiveFormatting();
                                            }
                                            this.clearStagedPhotos(false);
                                        });

                                        if (window.ResizeObserver) {
                                            const ro = new ResizeObserver(() => {
                                                if (this.$el.contains(document.activeElement)) {
                                                    this.keepBottomInView();
                                                }
                                            });
                                            ro.observe(this.$el);
                                        }
                                    },

                                    focusEditor() {
                                        if (this.$refs.editor) {
                                            this.$refs.editor.focus();
                                            this.$nextTick(() => {
                                                this.keepBottomInView();
                                            });
                                        }
                                    },

                                    syncFromLivewire() {
                                        if (!this.$refs.editor) return;
                                        if (!$wire.newTrelloComment) {
                                            this.$refs.editor.innerHTML = '';
                                        }
                                    },

                                    updateEmptyState() {
                                        const editor = this.$refs.editor;
                                        if (!editor) return;
                                        const text = (editor.textContent || '').replace(/\u200B/g, '').trim();
                                        const hasHr = Boolean(editor.querySelector('hr'));
                                        this.isEmpty = text.length === 0 && !hasHr;
                                        editor.setAttribute('data-empty', this.isEmpty ? 'true' : 'false');
                                    },

                                    updateActiveFormatting() {
                                        try {
                                            const sel = window.getSelection();
                                            const li = this.getClosestLi(sel ? sel.anchorNode : null);
                                            const list = li ? li.closest('ul, ol') : null;
                                            const currentBlock = this.getCurrentBlock();
                                            const blockTag = currentBlock ? currentBlock.tagName.toLowerCase() : '';

                                            const liText = li ? (li.textContent || '').trim() : '';
                                            const isLiChecklist = Boolean(li && /^\[[ xX]\]/.test(liText));

                                            this.isBold = document.queryCommandState('bold');
                                            this.isItalic = document.queryCommandState('italic');
                                            this.isOrderedList = Boolean(list && list.tagName.toLowerCase() === 'ol');
                                            this.isChecklist = isLiChecklist;
                                            this.isUnorderedList = Boolean(list && list.tagName.toLowerCase() === 'ul' && !isLiChecklist);
                                            this.isH1 = blockTag === 'h1';
                                            this.isH2 = blockTag === 'h2';
                                        } catch (e) {}
                                    },

                                    getClosestLi(node) {
                                        let curr = node;
                                        while (curr && curr !== this.$refs.editor) {
                                            if (curr.nodeName === 'LI') return curr;
                                            curr = curr.parentNode;
                                        }
                                        return null;
                                    },

                                    isInList(node) {
                                        return Boolean(this.getClosestLi(node));
                                    },

                                    getCurrentBlock() {
                                        const editor = this.$refs.editor;
                                        if (!editor) return null;

                                        const sel = window.getSelection();
                                        if (!sel || sel.rangeCount === 0) {
                                            return editor.lastElementChild || null;
                                        }

                                        let node = sel.anchorNode;
                                        if (!node || !editor.contains(node)) {
                                            return editor.lastElementChild || null;
                                        }

                                        if (node === editor) {
                                            const offset = sel.anchorOffset;
                                            if (editor.childNodes[offset]) {
                                                return editor.childNodes[offset];
                                            }
                                            return editor.lastElementChild || null;
                                        }

                                        while (node && node.parentNode !== editor) {
                                            node = node.parentNode;
                                        }

                                        return node;
                                    },

                                    setCursorTo(el, offset = 0) {
                                        if (!el) return;
                                        el.focus();
                                        const sel = window.getSelection();
                                        if (!sel) return;
                                        const range = document.createRange();
                                        if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
                                            const textLen = el.firstChild.length;
                                            range.setStart(el.firstChild, Math.min(offset, textLen));
                                        } else {
                                            range.setStart(el, Math.min(offset, el.childNodes.length));
                                        }
                                        range.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(range);
                                    },

                                    setCursorToStart(el) {
                                        if (!el) return;
                                        el.focus();
                                        const sel = window.getSelection();
                                        if (!sel) return;
                                        const range = document.createRange();
                                        range.setStart(el, 0);
                                        range.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(range);
                                    },

                                    setCursorToEnd(el) {
                                        if (!el) return;
                                        el.focus();
                                        const sel = window.getSelection();
                                        if (!sel) return;
                                        const range = document.createRange();
                                        if (el.lastChild && el.lastChild.nodeType === Node.TEXT_NODE) {
                                            range.setStart(el.lastChild, el.lastChild.length);
                                            range.collapse(true);
                                        } else if (el.childNodes.length === 1 && el.firstChild.nodeName === 'BR') {
                                            range.setStart(el, 0);
                                            range.collapse(true);
                                        } else {
                                            range.selectNodeContents(el);
                                            range.collapse(false);
                                        }
                                        sel.removeAllRanges();
                                        sel.addRange(range);
                                    },

                                    exitList(li) {
                                        const list = li.parentElement;
                                        if (!list) return;

                                        li.remove();

                                        const newBlock = document.createElement('div');
                                        newBlock.innerHTML = '<br>';

                                        if (list.nextSibling) {
                                            list.parentNode.insertBefore(newBlock, list.nextSibling);
                                        } else {
                                            list.parentNode.appendChild(newBlock);
                                        }

                                        if (list.children.length === 0) {
                                            list.remove();
                                        }

                                        this.setCursorToStart(newBlock);
                                        this.handleAfterChange();
                                    },

                                    normalizeBrBlocks() {
                                        const editor = this.$refs.editor;
                                        if (!editor) return;

                                        const children = Array.from(editor.childNodes);
                                        for (const child of children) {
                                            if (child.nodeType === Node.TEXT_NODE) {
                                                const text = child.nodeValue;
                                                if (text && text.trim().length > 0) {
                                                    const div = document.createElement('div');
                                                    div.textContent = text;
                                                    editor.replaceChild(div, child);
                                                }
                                                continue;
                                            }

                                            if (child.nodeType !== Node.ELEMENT_NODE) continue;

                                            const tag = child.tagName.toLowerCase();
                                            if (tag === 'ul' || tag === 'ol' || tag === 'hr') continue;

                                            const brs = child.querySelectorAll('br');
                                            if (brs.length > 0 && (child.childNodes.length > 1 || child.textContent.trim().length > 0)) {
                                                const lines = [];
                                                let currentDiv = document.createElement('div');
                                                for (const n of Array.from(child.childNodes)) {
                                                    if (n.nodeName === 'BR') {
                                                        if (currentDiv.childNodes.length === 0) currentDiv.innerHTML = '<br>';
                                                        lines.push(currentDiv);
                                                        currentDiv = document.createElement('div');
                                                    } else {
                                                        currentDiv.appendChild(n.cloneNode(true));
                                                    }
                                                }
                                                if (currentDiv.childNodes.length > 0) {
                                                    lines.push(currentDiv);
                                                }
                                                if (lines.length > 1) {
                                                    const frag = document.createDocumentFragment();
                                                    lines.forEach(l => frag.appendChild(l));
                                                    editor.replaceChild(frag, child);
                                                }
                                            }
                                        }
                                    },

                                    getBlocksInRange(range) {
                                        const editor = this.$refs.editor;
                                        if (!editor || !range) return [];

                                        const candidates = [];
                                        for (const child of Array.from(editor.children)) {
                                            if (child.tagName === 'UL' || child.tagName === 'OL') {
                                                for (const li of Array.from(child.children)) {
                                                    if (li.tagName === 'LI') candidates.push(li);
                                                }
                                            } else {
                                                candidates.push(child);
                                            }
                                        }

                                        if (range.collapsed) {
                                            const sel = window.getSelection();
                                            const node = sel ? sel.anchorNode : null;
                                            if (!node) return [];
                                            for (const c of candidates) {
                                                if (c === node || c.contains(node)) return [c];
                                            }
                                            return [];
                                        }

                                        const selected = [];
                                        for (const c of candidates) {
                                            try {
                                                if (range.intersectsNode(c)) {
                                                    selected.push(c);
                                                }
                                            } catch (e) {
                                                if (c.contains(range.startContainer) || c.contains(range.endContainer)) {
                                                    selected.push(c);
                                                }
                                            }
                                        }
                                        return selected;
                                    },

                                    selectBlocks(blocks) {
                                        if (!blocks || blocks.length === 0) return;
                                        try {
                                            const sel = window.getSelection();
                                            if (!sel) return;
                                            const newRange = document.createRange();
                                            const first = blocks[0];
                                            const last = blocks[blocks.length - 1];
                                            newRange.setStart(first, 0);
                                            newRange.setEnd(last, last.childNodes.length);
                                            sel.removeAllRanges();
                                            sel.addRange(newRange);
                                        } catch (e) {}
                                    },

                                    formatBlocksAsList(type) {
                                        this.$refs.editor.focus();
                                        const editor = this.$refs.editor;
                                        const sel = window.getSelection();
                                        if (!sel || sel.rangeCount === 0) return;

                                        if (!editor.firstChild) {
                                            editor.innerHTML = '<div><br></div>';
                                        }

                                        this.normalizeBrBlocks();

                                        const range = sel.getRangeAt(0);
                                        const wasCollapsed = range.collapsed;

                                        let selectedBlocks = this.getBlocksInRange(range);

                                        if (selectedBlocks.length === 0) {
                                            let block = this.getCurrentBlock();
                                            if (block && block !== editor) {
                                                selectedBlocks = [block];
                                            } else if (editor.firstElementChild) {
                                                selectedBlocks = [editor.firstElementChild];
                                            }
                                        }

                                        if (selectedBlocks.length === 0) return;

                                        // Check if ALL selected blocks are already of the exact requested list type
                                        let allAlreadyType = true;
                                        for (const b of selectedBlocks) {
                                            const li = b.tagName === 'LI' ? b : this.getClosestLi(b);
                                            if (!li) {
                                                allAlreadyType = false;
                                                break;
                                            }
                                            const parentList = li.closest('ul, ol');
                                            if (!parentList) {
                                                allAlreadyType = false;
                                                break;
                                            }

                                            const isCheck = /^\[[ xX]\]/.test((li.textContent || '').trim());
                                            if (type === 'checklist') {
                                                if (!isCheck) { allAlreadyType = false; break; }
                                            } else if (type === 'ul') {
                                                if (parentList.tagName.toLowerCase() !== 'ul' || isCheck) { allAlreadyType = false; break; }
                                            } else if (type === 'ol') {
                                                if (parentList.tagName.toLowerCase() !== 'ol') { allAlreadyType = false; break; }
                                            }
                                        }

                                        if (allAlreadyType) {
                                            // TOGGLE OFF: revert selected items to normal div lines
                                            const newDivs = [];
                                            for (const b of selectedBlocks) {
                                                const li = b.tagName === 'LI' ? b : this.getClosestLi(b);
                                                if (!li) continue;

                                                const div = document.createElement('div');
                                                let html = li.innerHTML || '';
                                                if (type === 'checklist') {
                                                    html = html.replace(/^(\s*\[[ xX]\]\s*|\[[ xX]\]&nbsp;)/i, '');
                                                }
                                                div.innerHTML = html.trim() ? html : '<br>';

                                                const list = li.closest('ul, ol');
                                                if (list) {
                                                    list.parentNode.insertBefore(div, list.nextSibling);
                                                } else {
                                                    editor.appendChild(div);
                                                }
                                                li.remove();
                                                if (list && list.children.length === 0) {
                                                    list.remove();
                                                }
                                                newDivs.push(div);
                                            }

                                            if (newDivs.length > 0) {
                                                if (wasCollapsed) {
                                                    this.setCursorToEnd(newDivs[0]);
                                                } else {
                                                    this.selectBlocks(newDivs);
                                                }
                                            }
                                            this.handleAfterChange();
                                            return;
                                        }

                                        // CONVERT TO LIST: create list items for all selected blocks
                                        const listTag = (type === 'ol') ? 'ol' : 'ul';
                                        const newList = document.createElement(listTag);

                                        const firstBlock = selectedBlocks[0];
                                        const insertRef = firstBlock.tagName === 'LI' ? (firstBlock.closest('ul, ol') || firstBlock) : firstBlock;

                                        insertRef.parentNode.insertBefore(newList, insertRef);

                                        const newLis = [];
                                        for (const b of selectedBlocks) {
                                            const newLi = document.createElement('li');
                                            let html = b.innerHTML ? b.innerHTML.trim() : '';

                                            // Clean up previous list / numbering / checklist prefixes
                                            html = html.replace(/^(\s*[-*]|\s*\d+[.)]|\s*\[[ xX]\])\s*/i, '');
                                            html = html.replace(/^\[[ xX]\]&nbsp;/i, '');

                                            if (type === 'checklist') {
                                                newLi.innerHTML = '[ ] &nbsp;' + (html === '<br>' || !html ? '' : html);
                                            } else {
                                                newLi.innerHTML = html && html !== '<br>' ? html : '<br>';
                                            }

                                            newList.appendChild(newLi);
                                            newLis.push(newLi);

                                            if (b.tagName === 'LI') {
                                                const oldList = b.closest('ul, ol');
                                                b.remove();
                                                if (oldList && oldList.children.length === 0 && oldList !== newList) {
                                                    oldList.remove();
                                                }
                                            } else {
                                                b.remove();
                                            }
                                        }

                                        if (insertRef && insertRef !== newList && insertRef.children && insertRef.children.length === 0 && (insertRef.tagName === 'UL' || insertRef.tagName === 'OL')) {
                                            insertRef.remove();
                                        }

                                        if (newLis.length > 0) {
                                            if (wasCollapsed) {
                                                this.setCursorToEnd(newLis[0]);
                                            } else {
                                                this.selectBlocks(newLis);
                                            }
                                        }
                                        this.handleAfterChange();
                                    },

                                    toggleList(type) {
                                        this.formatBlocksAsList(type);
                                    },

                                    toggleChecklist() {
                                        this.formatBlocksAsList('checklist');
                                    },

                                    convertLineToHeading(level, remainingText) {
                                        let currentBlock = this.getCurrentBlock();
                                        const editor = this.$refs.editor;
                                        if (!currentBlock || currentBlock === editor) {
                                            currentBlock = document.createElement('div');
                                            currentBlock.innerHTML = '<br>';
                                            editor.appendChild(currentBlock);
                                        }

                                        const heading = document.createElement(level);
                                        const cleanText = (remainingText || '').replace(/^[\s\u00A0]+/, '');

                                        if (!cleanText) {
                                            heading.innerHTML = '<br>';
                                            currentBlock.parentNode.replaceChild(heading, currentBlock);
                                            this.setCursorToStart(heading);
                                        } else {
                                            heading.textContent = cleanText;
                                            currentBlock.parentNode.replaceChild(heading, currentBlock);
                                            this.setCursorToEnd(heading);
                                        }

                                        this.handleAfterChange();
                                    },

                                    toggleHeading(level) {
                                        this.$refs.editor.focus();
                                        const editor = this.$refs.editor;
                                        let currentBlock = this.getCurrentBlock();

                                        if (!currentBlock || currentBlock === editor) {
                                            const heading = document.createElement(level);
                                            heading.innerHTML = '<br>';
                                            editor.appendChild(heading);
                                            this.setCursorToStart(heading);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        // If currently in a list item, detach and convert
                                        const sel = window.getSelection();
                                        const li = this.getClosestLi(sel ? sel.anchorNode : null);
                                        if (li) {
                                            const list = li.closest('ul, ol');
                                            const heading = document.createElement(level);
                                            heading.innerHTML = li.innerHTML || '<br>';

                                            li.remove();
                                            if (list && list.nextSibling) {
                                                list.parentNode.insertBefore(heading, list.nextSibling);
                                            } else if (list) {
                                                list.parentNode.appendChild(heading);
                                            }
                                            if (list && list.children.length === 0) {
                                                list.remove();
                                            }
                                            this.setCursorToEnd(heading);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        if (currentBlock.tagName === 'UL' || currentBlock.tagName === 'OL') {
                                            return;
                                        }

                                        const currentTag = currentBlock.tagName.toLowerCase();
                                        if (currentTag === level) {
                                            // Toggle off back to normal div
                                            const div = document.createElement('div');
                                            div.innerHTML = currentBlock.innerHTML && currentBlock.innerHTML !== '<br>' ? currentBlock.innerHTML : '<br>';
                                            currentBlock.parentNode.replaceChild(div, currentBlock);
                                            this.setCursorToEnd(div);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        // Split by <br> if needed
                                        if (currentBlock.innerHTML && currentBlock.innerHTML.includes('<br>') && currentBlock.childNodes.length > 1) {
                                            this.splitBlockByBr(currentBlock);
                                            currentBlock = this.getCurrentBlock();
                                        }

                                        const heading = document.createElement(level);
                                        let html = currentBlock.innerHTML ? currentBlock.innerHTML.trim() : '';
                                        html = html.replace(/^#{1,3}\s*/, '');

                                        if (!html || html === '<br>') {
                                            heading.innerHTML = '<br>';
                                            currentBlock.parentNode.replaceChild(heading, currentBlock);
                                            this.setCursorToStart(heading);
                                        } else {
                                            heading.innerHTML = html;
                                            currentBlock.parentNode.replaceChild(heading, currentBlock);
                                            this.setCursorToEnd(heading);
                                        }

                                        this.handleAfterChange();
                                    },

                                    insertDivider() {
                                        this.$refs.editor.focus();
                                        const editor = this.$refs.editor;
                                        let currentBlock = this.getCurrentBlock();

                                        const sel = window.getSelection();
                                        if (sel) {
                                            const li = this.getClosestLi(sel.anchorNode);
                                            if (li) {
                                                const list = li.closest('ul, ol');
                                                if (list) currentBlock = list;
                                            }
                                        }

                                        const hr = document.createElement('hr');
                                        const newDiv = document.createElement('div');
                                        newDiv.innerHTML = '<br>';

                                        if (!currentBlock || currentBlock === editor) {
                                            editor.appendChild(hr);
                                            editor.appendChild(newDiv);
                                            this.setCursorToStart(newDiv);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        const text = (currentBlock.textContent || '').replace(/\u200B/g, '').trim();
                                        if (text === '' || text === '---' || text === '***' || text === '___') {
                                            currentBlock.parentNode.insertBefore(hr, currentBlock);
                                            currentBlock.parentNode.insertBefore(newDiv, currentBlock);
                                            currentBlock.remove();
                                        } else {
                                            if (currentBlock.nextSibling) {
                                                editor.insertBefore(hr, currentBlock.nextSibling);
                                                editor.insertBefore(newDiv, hr.nextSibling);
                                            } else {
                                                editor.appendChild(hr);
                                                editor.appendChild(newDiv);
                                            }
                                        }

                                        this.setCursorToStart(newDiv);
                                        this.handleAfterChange();
                                    },

                                    splitBlockByBr(block) {
                                        const sel = window.getSelection();
                                        const anchorNode = sel ? sel.anchorNode : null;

                                        const lines = [];
                                        let currentDiv = document.createElement('div');
                                        let targetDiv = null;

                                        Array.from(block.childNodes).forEach(child => {
                                            if (child.nodeName === 'BR') {
                                                if (currentDiv.childNodes.length === 0) currentDiv.innerHTML = '<br>';
                                                lines.push(currentDiv);
                                                currentDiv = document.createElement('div');
                                            } else {
                                                if (child === anchorNode || (child.contains && child.contains(anchorNode))) {
                                                    targetDiv = currentDiv;
                                                }
                                                currentDiv.appendChild(child.cloneNode(true));
                                            }
                                        });

                                        if (currentDiv.childNodes.length > 0 || lines.length === 0) {
                                            if (currentDiv.childNodes.length === 0) currentDiv.innerHTML = '<br>';
                                            lines.push(currentDiv);
                                        }

                                        if (!targetDiv) {
                                            targetDiv = lines[lines.length - 1];
                                        }

                                        const frag = document.createDocumentFragment();
                                        lines.forEach(lineDiv => frag.appendChild(lineDiv));
                                        block.parentNode.replaceChild(frag, block);

                                        this.setCursorToEnd(targetDiv);
                                    },

                                    insertNewline() {
                                        const sel = window.getSelection();
                                        if (!sel || sel.rangeCount === 0) return;

                                        const li = this.getClosestLi(sel.anchorNode);
                                        if (li) {
                                            const rawText = (li.textContent || '').replace(/\u200B/g, '').trim();
                                            const isChecklist = /^\[[ xX]\]/.test(rawText);
                                            const isOnlyMarker = rawText === '[ ]' || rawText === '[x]' || rawText === '[X]' || rawText === '[]' || !rawText;

                                            if (isOnlyMarker) {
                                                this.exitList(li);
                                                return;
                                            }

                                            // In list: insert a new <li> directly inside this list
                                            const newLi = document.createElement('li');
                                            newLi.innerHTML = isChecklist ? '[ ] &nbsp;' : '<br>';
                                            if (li.nextSibling) {
                                                li.parentNode.insertBefore(newLi, li.nextSibling);
                                            } else {
                                                li.parentNode.appendChild(newLi);
                                            }
                                            this.setCursorToEnd(newLi);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        // Outside list: create an isolated <div> for the next line
                                        const editor = this.$refs.editor;
                                        let currentBlock = this.getCurrentBlock();

                                        if (!currentBlock || currentBlock === editor) {
                                            const div1 = document.createElement('div');
                                            div1.innerHTML = '<br>';
                                            const div2 = document.createElement('div');
                                            div2.innerHTML = '<br>';
                                            editor.innerHTML = '';
                                            editor.appendChild(div1);
                                            editor.appendChild(div2);
                                            this.setCursorToStart(div2);
                                            this.handleAfterChange();
                                            return;
                                        }

                                        if (currentBlock.nodeType === Node.TEXT_NODE) {
                                            const div = document.createElement('div');
                                            currentBlock.parentNode.insertBefore(div, currentBlock);
                                            div.appendChild(currentBlock);
                                            currentBlock = div;
                                        }

                                        const newDiv = document.createElement('div');
                                        newDiv.innerHTML = '<br>';

                                        const range = sel.getRangeAt(0);
                                        const afterRange = document.createRange();
                                        afterRange.setStart(range.endContainer, range.endOffset);
                                        afterRange.setEnd(currentBlock, currentBlock.childNodes.length);
                                        const afterContent = afterRange.extractContents();

                                        if (afterContent.textContent.length > 0 || afterContent.querySelector('img, a, br')) {
                                            newDiv.innerHTML = '';
                                            newDiv.appendChild(afterContent);
                                        }

                                        if (!currentBlock.textContent && !currentBlock.querySelector('br, img')) {
                                            currentBlock.innerHTML = '<br>';
                                        }

                                        if (currentBlock.nextSibling) {
                                            editor.insertBefore(newDiv, currentBlock.nextSibling);
                                        } else {
                                            editor.appendChild(newDiv);
                                        }

                                        this.setCursorToStart(newDiv);
                                        this.handleAfterChange();
                                    },

                                    insertLineBreak() {
                                        const sel = window.getSelection();
                                        if (!sel || sel.rangeCount === 0) return;

                                        const success = document.execCommand('insertLineBreak', false, null);
                                        if (!success) {
                                            const range = sel.getRangeAt(0);
                                            const br = document.createElement('br');
                                            range.deleteContents();
                                            range.insertNode(br);
                                            range.setStartAfter(br);
                                            range.setEndAfter(br);
                                            sel.removeAllRanges();
                                            sel.addRange(range);
                                        }

                                        this.handleAfterChange();
                                    },

                                    handleKeydown(e) {
                                        const isCmdOrCtrl = e.metaKey || e.ctrlKey;

                                        // Cmd+Enter / Ctrl+Enter: Submit
                                        if (isCmdOrCtrl && e.key === 'Enter') {
                                            e.preventDefault();
                                            this.submit();
                                            return;
                                        }

                                        // Cmd+B / Ctrl+B: Bold
                                        if (isCmdOrCtrl && (e.key === 'b' || e.key === 'B')) {
                                            e.preventDefault();
                                            this.format('bold');
                                            return;
                                        }

                                        // Cmd+I / Ctrl+I: Italic
                                        if (isCmdOrCtrl && (e.key === 'i' || e.key === 'I')) {
                                            e.preventDefault();
                                            this.format('italic');
                                            return;
                                        }

                                        // Cmd+K / Ctrl+K: Link
                                        if (isCmdOrCtrl && (e.key === 'k' || e.key === 'K')) {
                                            e.preventDefault();
                                            this.promptLink();
                                            return;
                                        }

                                        // Shift+Enter: Soft line break within line/item
                                        if (e.key === 'Enter' && e.shiftKey) {
                                            e.preventDefault();
                                            this.insertLineBreak();
                                            return;
                                        }

                                        // Plain Enter: JUMP to next line, divider conversion or next list item
                                        if (e.key === 'Enter' && !e.shiftKey && !isCmdOrCtrl) {
                                            const sel = window.getSelection();
                                            const currentBlock = this.getCurrentBlock();
                                            if (currentBlock && !this.isInList(sel ? sel.anchorNode : null)) {
                                                const text = (currentBlock.textContent || '').replace(/\u200B/g, '').trim();
                                                if (text === '---' || text === '***' || text === '___') {
                                                    e.preventDefault();
                                                    this.insertDivider();
                                                    return;
                                                }
                                            }

                                            e.preventDefault();
                                            this.insertNewline();
                                            return;
                                        }

                                        // Backspace on empty list item exits list; on empty heading reverts to div
                                        if (e.key === 'Backspace') {
                                            const sel = window.getSelection();
                                            const li = this.getClosestLi(sel ? sel.anchorNode : null);
                                            if (li) {
                                                const text = (li.textContent || '').replace(/\u200B/g, '').trim();
                                                if (!text) {
                                                    e.preventDefault();
                                                    this.exitList(li);
                                                    return;
                                                }
                                            }

                                            const currentBlock = this.getCurrentBlock();
                                            if (currentBlock && (currentBlock.tagName === 'H1' || currentBlock.tagName === 'H2' || currentBlock.tagName === 'H3')) {
                                                const text = (currentBlock.textContent || '').replace(/\u200B/g, '').trim();
                                                if (!text) {
                                                    e.preventDefault();
                                                    const div = document.createElement('div');
                                                    div.innerHTML = '<br>';
                                                    currentBlock.parentNode.replaceChild(div, currentBlock);
                                                    this.setCursorToStart(div);
                                                    this.handleAfterChange();
                                                    return;
                                                }
                                            }
                                        }
                                    },

                                    handleKeyup(e) {
                                        this.updateActiveFormatting();
                                        this.updateEmptyState();
                                        if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'PageUp' || e.key === 'PageDown') {
                                            this.keepBottomInView();
                                        }
                                    },

                                    handleInput() {
                                        const sel = window.getSelection();
                                        if (sel && sel.rangeCount > 0) {
                                            const node = sel.anchorNode;
                                            if (node && node.nodeType === Node.TEXT_NODE) {
                                                const rawText = node.nodeValue || '';
                                                const text = rawText.replace(/\u200B/g, '');

                                                // Check for divider: '---', '***', '___'
                                                if (/^(---|---|\*\*\*|___)[\s\u00A0]?$/.test(text.trim()) && !this.isInList(node)) {
                                                    this.insertDivider();
                                                    return;
                                                }

                                                // Check for headings: '# ', '## ', '### '
                                                if (/^#[\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    const rem = text.replace(/^#[\s\u00A0]/, '');
                                                    this.convertLineToHeading('h1', rem);
                                                    return;
                                                } else if (/^##[\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    const rem = text.replace(/^##[\s\u00A0]/, '');
                                                    this.convertLineToHeading('h2', rem);
                                                    return;
                                                } else if (/^###[\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    const rem = text.replace(/^###[\s\u00A0]/, '');
                                                    this.convertLineToHeading('h2', rem);
                                                    return;
                                                }

                                                // Check for checklist: '[] ', '[ ] ', '- [ ] ', '* [ ] '
                                                if (/^(?:[\-\*]\s*)?\[\s*\][\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    const rem = text.replace(/^(?:[\-\*]\s*)?\[\s*\][\s\u00A0]/, '');
                                                    node.nodeValue = '[ ] ' + rem;
                                                    this.toggleList('ul');
                                                    return;
                                                }

                                                // Check for lists: '- ', '* ', '1. ', '1) '
                                                if (/^[\-\*][\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    node.nodeValue = text.replace(/^[\-\*][\s\u00A0]/, '');
                                                    this.toggleList('ul');
                                                    return;
                                                } else if (/^\d+[\.\)][\s\u00A0]/.test(text) && !this.isInList(node)) {
                                                    node.nodeValue = text.replace(/^\d+[\.\)][\s\u00A0]/, '');
                                                    this.toggleList('ol');
                                                    return;
                                                }
                                            }
                                        }
                                        this.handleAfterChange();
                                    },

                                    handleBlur() {
                                        this.syncToLivewire();
                                    },

                                    handleAfterChange() {
                                        this.updateEmptyState();
                                        this.updateActiveFormatting();
                                        this.syncToLivewire();
                                        this.$nextTick(() => {
                                            this.keepBottomInView();
                                        });
                                    },

                                    keepBottomInView() {
                                        const scrollContainer = this.$el.closest('.overflow-y-auto');
                                        if (!scrollContainer) return;

                                        const containerRect = scrollContainer.getBoundingClientRect();
                                        const boxRect = this.$el.getBoundingClientRect();

                                        // Margen inferior deseado para que la caja quede pegada al fondo visible,
                                        // dejando espacio para la barra de acciones y el botón flotante de Cerrar (Esc).
                                        const desiredBottom = containerRect.bottom - 75;

                                        // Si la caja sobrepasa o toca el fondo deseado del viewport:
                                        const overflowBottom = boxRect.bottom - desiredBottom;
                                        if (overflowBottom > 0) {
                                            scrollContainer.scrollTop += overflowBottom;
                                            return;
                                        }

                                        // Si el usuario navega hacia arriba con las flechas o selecciona texto en los párrafos de arriba,
                                        // evitar que quede tapado por las pestañas fijas de arriba (top + 50px).
                                        const sel = window.getSelection();
                                        if (sel && sel.rangeCount > 0 && sel.anchorNode) {
                                            try {
                                                const range = sel.getRangeAt(0);
                                                const rRect = range.getBoundingClientRect();
                                                if (rRect && rRect.top > 0) {
                                                    const overflowTop = (containerRect.top + 50) - rRect.top;
                                                    if (overflowTop > 0) {
                                                        scrollContainer.scrollTop -= overflowTop;
                                                    }
                                                }
                                            } catch (e) {}
                                        }
                                    },

                                    format(cmd, arg = null) {
                                        this.$refs.editor.focus();
                                        document.execCommand(cmd, false, arg);
                                        this.handleAfterChange();
                                    },

                                    promptLink() {
                                        this.$refs.editor.focus();
                                        const url = prompt('{{ __('Enter link URL:') }}', 'https://');
                                        if (url && url.trim() && url !== 'https://') {
                                            document.execCommand('createLink', false, url.trim());
                                            this.handleAfterChange();
                                        }
                                    },

                                    syncToLivewire() {
                                        const editor = this.$refs.editor;
                                        if (!editor) return;
                                        const md = this.getMarkdownFromDOM(editor);
                                        if (window.Livewire) {
                                            $wire.set('newTrelloComment', md, false);
                                        }
                                    },

                                    submit() {
                                        const editor = this.$refs.editor;
                                        if (!editor) return;
                                        const md = this.getMarkdownFromDOM(editor).trim();
                                        if (!md && this.stagedPhotos.length === 0) return;

                                        if (this.isUploadingPhotos) {
                                            return;
                                        }

                                        this.isSubmitting = true;
                                        $wire.set('newTrelloComment', md);
                                        $wire.addTrelloComment().then(() => {
                                            this.isSubmitting = false;
                                            if (this.$refs.editor) {
                                                this.$refs.editor.innerHTML = '';
                                                this.updateEmptyState();
                                                this.updateActiveFormatting();
                                            }
                                            this.clearStagedPhotos(false);
                                        }).catch(() => {
                                            this.isSubmitting = false;
                                        });
                                    },

                                    addFiles(fileList) {
                                        if (!fileList || fileList.length === 0) return;
                                        const maxBytes = 10 * 1024 * 1024;
                                        let oversized = [];
                                        let validFiles = [];

                                        for (let i = 0; i < fileList.length; i++) {
                                            const file = fileList[i];
                                            if (!file.type || !file.type.startsWith('image/')) {
                                                continue;
                                            }
                                            if (file.size > maxBytes) {
                                                oversized.push(file.name);
                                                continue;
                                            }

                                            let sizeFormatted = '';
                                            if (file.size < 1024 * 1024) {
                                                sizeFormatted = (file.size / 1024).toFixed(1) + ' KB';
                                            } else {
                                                sizeFormatted = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
                                            }

                                            this.stagedPhotos.push({
                                                file: file,
                                                url: URL.createObjectURL(file),
                                                name: file.name,
                                                size: sizeFormatted
                                            });
                                            validFiles.push(file);
                                        }

                                        if (oversized.length > 0) {
                                            $wire.dispatch('toast', { message: '{{ __('One or more photos exceed 10 MB (Trello limit): ') }}' + oversized.join(', ') });
                                        }

                                        if (validFiles.length > 0) {
                                            this.activePhotoUploads++;
                                            this.isUploadingPhotos = true;
                                            $wire.uploadMultiple(
                                                'commentPhotos',
                                                validFiles,
                                                () => {
                                                    this.activePhotoUploads = Math.max(0, this.activePhotoUploads - 1);
                                                    this.isUploadingPhotos = this.activePhotoUploads > 0;
                                                },
                                                () => {
                                                    this.activePhotoUploads = Math.max(0, this.activePhotoUploads - 1);
                                                    this.isUploadingPhotos = this.activePhotoUploads > 0;
                                                    $wire.dispatch('toast', { message: '{{ __('Error uploading one or more photos. Please try again.') }}' });
                                                }
                                            );
                                        }
                                    },

                                    removePhoto(index) {
                                        if (this.stagedPhotos[index]) {
                                            try { URL.revokeObjectURL(this.stagedPhotos[index].url); } catch(e) {}
                                            this.stagedPhotos.splice(index, 1);
                                            $wire.call('removeCommentPhoto', index);
                                        }
                                    },

                                    clearStagedPhotos(syncServer = true) {
                                        this.stagedPhotos.forEach(p => {
                                            try { URL.revokeObjectURL(p.url); } catch(e) {}
                                        });
                                        this.stagedPhotos = [];
                                        this.activePhotoUploads = 0;
                                        this.isUploadingPhotos = false;
                                        const input = this.$refs.photoInput;
                                        if (input) {
                                            input.value = '';
                                        }
                                        if (syncServer) {
                                            $wire.call('clearCommentPhotos');
                                        }
                                    },

                                    handleFileSelect(event) {
                                        const files = Array.from(event.target.files || []);
                                        if (files.length > 0) {
                                            this.addFiles(files);
                                        }
                                        event.target.value = '';
                                    },

                                    handlePaste(event) {
                                        const clipboard = event.clipboardData || window.clipboardData;
                                        if (!clipboard) return;

                                        const items = clipboard.items;
                                        let imageFiles = [];

                                        if (items) {
                                            for (let i = 0; i < items.length; i++) {
                                                if (items[i].type && items[i].type.startsWith('image/')) {
                                                    const file = items[i].getAsFile();
                                                    if (file) {
                                                        const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                                                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
                                                        const namedFile = new File([file], 'captura_' + timestamp + '_' + (i + 1) + '.' + ext, { type: file.type });
                                                        imageFiles.push(namedFile);
                                                    }
                                                }
                                            }
                                        }

                                        if (imageFiles.length === 0 && clipboard.files && clipboard.files.length > 0) {
                                            for (let i = 0; i < clipboard.files.length; i++) {
                                                const file = clipboard.files[i];
                                                if (file.type && file.type.startsWith('image/')) {
                                                    imageFiles.push(file);
                                                }
                                            }
                                        }

                                        if (imageFiles.length > 0) {
                                            event.preventDefault();
                                            this.addFiles(imageFiles);
                                        }
                                    },

                                    handleEditorDrop(event) {
                                        this.isDraggingPhotos = false;
                                        const dt = event.dataTransfer;
                                        if (!dt || !dt.files || dt.files.length === 0) return;

                                        let imageFiles = [];
                                        for (let i = 0; i < dt.files.length; i++) {
                                            if (dt.files[i].type && dt.files[i].type.startsWith('image/')) {
                                                imageFiles.push(dt.files[i]);
                                            }
                                        }

                                        if (imageFiles.length > 0) {
                                            this.addFiles(imageFiles);
                                        }
                                    },

                                    getMarkdownFromDOM(node) {
                                        let text = '';
                                        for (let child of node.childNodes) {
                                            if (child.nodeType === Node.TEXT_NODE) {
                                                text += child.nodeValue;
                                            } else if (child.nodeType === Node.ELEMENT_NODE) {
                                                const tag = child.tagName.toLowerCase();
                                                const childContent = this.getMarkdownFromDOM(child);

                                                if (tag === 'b' || tag === 'strong') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        const leading = childContent.match(/^\s*/)[0];
                                                        const trailing = childContent.match(/\s*$/)[0];
                                                        text += leading + '**' + trimmed + '**' + trailing;
                                                    } else {
                                                        text += childContent;
                                                    }
                                                } else if (tag === 'i' || tag === 'em') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        const leading = childContent.match(/^\s*/)[0];
                                                        const trailing = childContent.match(/\s*$/)[0];
                                                        text += leading + '*' + trimmed + '*' + trailing;
                                                    } else {
                                                        text += childContent;
                                                    }
                                                } else if (tag === 'code') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        const leading = childContent.match(/^\s*/)[0];
                                                        const trailing = childContent.match(/\s*$/)[0];
                                                        text += leading + '`' + trimmed + '`' + trailing;
                                                    } else {
                                                        text += childContent;
                                                    }
                                                } else if (tag === 'a') {
                                                    const href = child.getAttribute('href') || childContent;
                                                    text += '[' + childContent + '](' + href + ')';
                                                } else if (tag === 'h1') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        if (text && !text.endsWith('\n')) text += '\n';
                                                        text += '# ' + trimmed + '\n\n';
                                                    }
                                                } else if (tag === 'h2') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        if (text && !text.endsWith('\n')) text += '\n';
                                                        text += '## ' + trimmed + '\n\n';
                                                    }
                                                } else if (tag === 'h3') {
                                                    const trimmed = childContent.trim();
                                                    if (trimmed) {
                                                        if (text && !text.endsWith('\n')) text += '\n';
                                                        text += '### ' + trimmed + '\n\n';
                                                    }
                                                } else if (tag === 'hr') {
                                                    if (text) {
                                                        if (!text.endsWith('\n\n')) {
                                                            text += text.endsWith('\n') ? '\n' : '\n\n';
                                                        }
                                                    }
                                                    text += '---\n\n';
                                                } else if (tag === 'li') {
                                                    const parentTag = child.parentElement ? child.parentElement.tagName.toLowerCase() : '';
                                                    if (parentTag === 'ol') {
                                                        const siblings = Array.from(child.parentElement.children).filter(c => c.tagName.toLowerCase() === 'li');
                                                        const idx = siblings.indexOf(child) + 1;
                                                        text += idx + '. ' + childContent.trim() + '\n';
                                                    } else {
                                                        const trimmed = childContent.trim();
                                                        if (/^\[([ \t]*|[xX])\]/.test(trimmed)) {
                                                            const normalized = trimmed.replace(/^\[([ \t]*|[xX])\][ \t]*/, (m, p1) => {
                                                                const mark = p1.trim().toLowerCase() === 'x' ? 'x' : ' ';
                                                                return '- [' + mark + '] ';
                                                            });
                                                            text += normalized + '\n';
                                                        } else {
                                                            text += '- ' + trimmed + '\n';
                                                        }
                                                    }
                                                } else if (tag === 'ul' || tag === 'ol') {
                                                    text += (text && !text.endsWith('\n') ? '\n' : '') + childContent + (childContent.endsWith('\n') ? '' : '\n');
                                                } else if (tag === 'div' || tag === 'p') {
                                                    if (childContent === '\n' || childContent === '') {
                                                        text += '\n';
                                                    } else {
                                                        if (text && !text.endsWith('\n')) {
                                                            text += '\n';
                                                        }
                                                        let line = childContent;
                                                        if (/^[ \t]*(?:[-*+]|\d+[\.\)])?[ \t]*\[([ \t]*|[xX])\]/.test(line)) {
                                                            line = line.replace(/^[ \t]*(?:[-*+]|\d+[\.\)])?[ \t]*\[([ \t]*|[xX])\][ \t]*/, (m, p1) => {
                                                                const mark = p1.trim().toLowerCase() === 'x' ? 'x' : ' ';
                                                                return '- [' + mark + '] ';
                                                            });
                                                        }
                                                        text += line + '\n';
                                                    }
                                                } else if (tag === 'br') {
                                                    text += '\n';
                                                } else {
                                                    text += childContent;
                                                }
                                            }
                                        }
                                        return text;
                                    }
                                }">
                                <style>
                                    .comment-editor-box h1 {
                                        font-size: 1.1rem !important;
                                        font-weight: 700 !important;
                                        color: #18181b !important;
                                        margin-top: 0.4rem !important;
                                        margin-bottom: 0.25rem !important;
                                        line-height: 1.35 !important;
                                    }
                                    .comment-editor-box h2 {
                                        font-size: 0.975rem !important;
                                        font-weight: 600 !important;
                                        color: #27272a !important;
                                        margin-top: 0.35rem !important;
                                        margin-bottom: 0.2rem !important;
                                        line-height: 1.35 !important;
                                    }
                                    .comment-editor-box h3 {
                                        font-size: 0.85rem !important;
                                        font-weight: 600 !important;
                                        color: #3f3f46 !important;
                                        margin-top: 0.25rem !important;
                                        margin-bottom: 0.15rem !important;
                                        line-height: 1.4 !important;
                                    }
                                    .comment-editor-box hr {
                                        border: none !important;
                                        border-top: 2px solid #e4e4e7 !important;
                                        margin-top: 0.625rem !important;
                                        margin-bottom: 0.625rem !important;
                                        cursor: default;
                                    }
                                    .comment-editor-box ul {
                                        list-style-type: disc !important;
                                        padding-left: 1.25rem !important;
                                        margin-top: 0.25rem !important;
                                        margin-bottom: 0.25rem !important;
                                    }
                                    .comment-editor-box ol {
                                        list-style-type: decimal !important;
                                        padding-left: 1.25rem !important;
                                        margin-top: 0.25rem !important;
                                        margin-bottom: 0.25rem !important;
                                    }
                                    .comment-editor-box li {
                                        display: list-item !important;
                                        margin-top: 0.125rem !important;
                                        margin-bottom: 0.125rem !important;
                                    }
                                    .comment-editor-box a {
                                        color: #0284c7 !important;
                                        text-decoration: underline !important;
                                        font-weight: 500 !important;
                                    }
                                    .comment-editor-box a:hover {
                                        color: #0369a1 !important;
                                    }
                                    .comment-editor-box:empty:before,
                                    .comment-editor-box[data-empty="true"]:before {
                                        content: attr(data-placeholder);
                                        color: #a1a1aa;
                                        pointer-events: none;
                                        display: block;
                                    }
                                </style>

                                <!-- Formatting Toolbar (Top, Clean Notion/Linear Style) -->
                                <div class="border-b border-[#f0f0ee] bg-canvas/90 px-3 py-1.5 flex items-center justify-between gap-1 select-none flex-wrap">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <!-- Bold -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="format('bold')" 
                                            :class="isBold ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="p-1.5 rounded-md border text-xs font-bold transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Bold (⌘B / Ctrl+B)') }}"
                                        >
                                            <x-lucide-bold class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Italic -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="format('italic')" 
                                            :class="isItalic ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="p-1.5 rounded-md border text-xs italic transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Italic (⌘I / Ctrl+I)') }}"
                                        >
                                            <x-lucide-italic class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Link -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="promptLink()" 
                                            class="p-1.5 rounded-md border border-transparent text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 text-xs transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Insert link (⌘K / Ctrl+K)') }}"
                                        >
                                            <x-lucide-link class="w-3.5 h-3.5" />
                                        </button>

                                        <div class="h-3.5 w-px bg-stone-200 mx-1"></div>

                                        <!-- Heading 1 -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="toggleHeading('h1')" 
                                            :class="isH1 ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="p-1.5 rounded-md border text-xs font-semibold transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Heading (# )') }}"
                                        >
                                            <x-lucide-heading-1 class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Heading 2 -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="toggleHeading('h2')" 
                                            :class="isH2 ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="p-1.5 rounded-md border text-xs font-semibold transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Subheading (## )') }}"
                                        >
                                            <x-lucide-heading-2 class="w-3.5 h-3.5" />
                                        </button>

                                        <div class="h-3.5 w-px bg-stone-200 mx-1"></div>

                                        <!-- Bullet List -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="toggleList('ul')" 
                                            :class="isUnorderedList ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="px-2 py-1 rounded-md border text-[11px] font-medium transition flex items-center gap-1.5 cursor-pointer" 
                                            title="{{ __('Bulleted list (- or *)') }}"
                                        >
                                            <x-lucide-list class="w-3.5 h-3.5" />
                                            <span class="hidden sm:inline">{{ __('Bullets') }}</span>
                                        </button>

                                        <!-- Ordered List -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="toggleList('ol')" 
                                            :class="isOrderedList ? 'bg-sky-100 text-sky-700 border-sky-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="px-2 py-1 rounded-md border text-[11px] font-medium transition flex items-center gap-1.5 cursor-pointer" 
                                            title="{{ __('Numbered list (1.)') }}"
                                        >
                                            <x-lucide-list-ordered class="w-3.5 h-3.5" />
                                            <span class="hidden sm:inline">{{ __('Numbered') }}</span>
                                        </button>

                                        <!-- Checklist -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="toggleChecklist()" 
                                            :class="isChecklist ? 'bg-emerald-100 text-emerald-800 border-emerald-300 shadow-2xs' : 'text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 border-transparent'"
                                            class="px-2 py-1 rounded-md border text-[11px] font-medium transition flex items-center gap-1.5 cursor-pointer" 
                                            title="{{ __('Checklist (- [ ])') }}"
                                        >
                                            <x-lucide-check-square class="w-3.5 h-3.5 text-emerald-600" />
                                            <span class="hidden sm:inline">{{ __('Checklist') }}</span>
                                        </button>

                                        <div class="h-3.5 w-px bg-stone-200 mx-1"></div>

                                        <!-- Divider Line -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="insertDivider()" 
                                            class="p-1.5 rounded-md border border-transparent text-zinc-600 hover:bg-stone-200/70 hover:text-zinc-900 text-xs transition flex items-center justify-center cursor-pointer" 
                                            title="{{ __('Divider (---)') }}"
                                        >
                                            <x-lucide-separator-horizontal class="w-3.5 h-3.5" />
                                        </button>

                                        <div class="h-3.5 w-px bg-stone-200 mx-1"></div>

                                        <!-- Attach Photo Button in Toolbar -->
                                        <button 
                                            type="button" 
                                            @mousedown.prevent 
                                            @click="$refs.photoInput.click()" 
                                            class="p-1.5 rounded-md border border-transparent text-zinc-600 hover:bg-stone-200/70 hover:text-sky-600 text-xs transition flex items-center justify-center cursor-pointer group" 
                                            title="{{ __('Attach photo(s)') }}"
                                        >
                                            <x-lucide-image class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" />
                                        </button>
                                    </div>

                                    <div class="text-[10px] text-zinc-400 hidden md:flex items-center gap-1 font-mono">
                                        <span>Markdown</span>
                                    </div>
                                </div>

                                <!-- Animated Drag & Drop Overlay for Comment Photos -->
                                <div 
                                    x-show="isDraggingPhotos" 
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute inset-0 z-30 rounded-xl bg-sky-50/95 backdrop-blur-xs border-2 border-dashed border-sky-500 flex flex-col items-center justify-center p-4 text-center pointer-events-none"
                                    style="display: none;"
                                >
                                    <div class="w-10 h-10 rounded-full bg-sky-100 border border-sky-300 flex items-center justify-center text-sky-600 mb-1.5 animate-bounce">
                                        <x-lucide-image class="w-5 h-5" />
                                    </div>
                                    <p class="text-xs font-bold text-sky-950">{{ __('Drop photos here') }}</p>
                                    <p class="text-[11px] text-sky-700 mt-0.5">{{ __('They will be attached to this comment (max 10 MB each)') }}</p>
                                </div>

                                <!-- Contenteditable Input Area -->
                                <div 
                                    @click="focusEditor()"
                                    @dragover.prevent="isDraggingPhotos = true"
                                    @dragleave.prevent="isDraggingPhotos = false"
                                    @drop.prevent="handleEditorDrop($event)"
                                    class="p-3 min-h-[90px] cursor-text bg-white"
                                >
                                    <div 
                                        x-ref="editor"
                                        contenteditable="true"
                                        @input="handleInput()"
                                        @blur="handleBlur()"
                                        @keydown="handleKeydown($event)"
                                        @keyup="handleKeyup($event)"
                                        @paste="handlePaste($event)"
                                        @pointerup="updateActiveFormatting()"
                                        data-placeholder="{{ __('Write a comment (# Heading, - bullets, --- divider, paste or drag photos)...') }}"
                                        class="comment-editor-box w-full text-xs text-zinc-900 font-sans leading-relaxed outline-none break-words min-h-[64px]"
                                    ></div>
                                </div>

                                <!-- Staged Photos Tray (When photos are selected / dropped / pasted) -->
                                <div 
                                    x-show="stagedPhotos.length > 0" 
                                    x-cloak
                                    class="border-t border-[#f0f0ee] bg-stone-50/80 p-2.5 space-y-2 select-none"
                                >
                                    <div class="flex items-center justify-between text-[11px] px-0.5">
                                        <span class="font-medium flex items-center gap-1.5 text-zinc-700">
                                            <x-lucide-image class="w-3.5 h-3.5 text-sky-600" />
                                            <span>{{ __('Photos to attach:') }}</span>
                                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 border border-sky-200" x-text="stagedPhotos.length"></span>
                                        </span>
                                        <button 
                                            type="button" 
                                            @click="clearStagedPhotos()" 
                                            class="text-[10px] text-zinc-400 hover:text-rose-600 transition cursor-pointer font-medium"
                                        >
                                            {{ __('Remove all') }}
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
                                        <template x-for="(photo, index) in stagedPhotos" :key="index">
                                            <div class="relative group/preview shrink-0 w-20 h-20 rounded-lg overflow-hidden border border-stone-200 bg-white shadow-2xs">
                                                <img :src="photo.url" :alt="photo.name" class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-stone-900/40 opacity-0 group-hover/preview:opacity-100 transition-opacity flex items-center justify-center">
                                                    <button 
                                                        type="button" 
                                                        @click="removePhoto(index)" 
                                                        class="w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center shadow-xs hover:bg-rose-700 transition cursor-pointer"
                                                        title="{{ __('Remove this photo') }}"
                                                    >
                                                        <x-lucide-x class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                                <div class="absolute bottom-0 inset-x-0 bg-stone-900/75 backdrop-blur-[1px] text-white text-[8px] px-1 py-0.5 truncate font-mono" x-text="photo.name"></div>
                                            </div>
                                        </template>

                                        {{-- Add more photos button --}}
                                        <button 
                                            type="button" 
                                            @click="$refs.photoInput.click()" 
                                            class="shrink-0 w-20 h-20 rounded-lg border-2 border-dashed border-stone-300 hover:border-sky-400 bg-white hover:bg-sky-50/50 flex flex-col items-center justify-center gap-1 text-zinc-400 hover:text-sky-600 transition cursor-pointer"
                                            title="{{ __('Add more photos') }}"
                                        >
                                            <x-lucide-plus class="w-5 h-5" />
                                            <span class="text-[9px] font-medium">{{ __('Add') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Action Bar (Bottom) -->
                                <div class="border-t border-[#f0f0ee] bg-canvas px-3 py-2 flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-3">
                                        <!-- Attach Photo Button in Action Bar -->
                                        <button 
                                            type="button" 
                                            @click="$refs.photoInput.click()" 
                                            class="px-2 py-1 rounded-md text-[11px] font-medium text-zinc-600 hover:text-sky-700 hover:bg-stone-200/60 transition flex items-center gap-1.5 cursor-pointer border border-transparent hover:border-stone-200"
                                            title="{{ __('Attach photos to this comment') }}"
                                        >
                                            <x-lucide-camera class="w-3.5 h-3.5 text-zinc-500" />
                                            <span>{{ __('Attach photo') }}</span>
                                            <template x-if="stagedPhotos.length > 0">
                                                <span class="px-1.5 py-0.2 rounded-full text-[9.5px] font-bold bg-sky-100 text-sky-700 border border-sky-300" x-text="stagedPhotos.length"></span>
                                            </template>
                                        </button>

                                        <div class="hidden sm:flex items-center gap-2 text-[10.5px] text-zinc-400 font-sans select-none">
                                            <span class="flex items-center gap-1">
                                                <kbd class="px-1.5 py-0.5 rounded bg-white border border-stone-200 text-zinc-600 font-mono text-[9.5px] shadow-2xs">Enter</kbd>
                                                <span>{{ __('new line') }}</span>
                                            </span>
                                            <span class="text-zinc-300">•</span>
                                            <span class="flex items-center gap-1">
                                                <kbd class="px-1.5 py-0.5 rounded bg-white border border-stone-200 text-zinc-600 font-mono text-[9.5px] shadow-2xs">⌘ / Ctrl + Enter</kbd>
                                                <span>{{ __('post') }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <span class="text-[11px] text-zinc-400 hidden md:inline">
                                            {{ __('As:') }} <strong class="text-zinc-700 font-medium">{{ auth()->user()?->name ?? __('User') }}</strong>
                                        </span>
                                        <button 
                                            @click="submit()"
                                            :disabled="isSubmitting || isUploadingPhotos || (isEmpty && stagedPhotos.length === 0)"
                                            type="button" 
                                            class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium text-xs transition flex items-center gap-1.5 shadow-2xs cursor-pointer shrink-0"
                                        >
                                            <x-lucide-send x-show="!isSubmitting && !isUploadingPhotos" class="w-3.5 h-3.5" />
                                            <x-lucide-loader-2 x-show="isSubmitting || isUploadingPhotos" x-cloak class="w-3.5 h-3.5 animate-spin" />
                                            <span x-text="isSubmitting ? '{{ __('Posting...') }}' : (isUploadingPhotos ? '{{ __('Uploading photos...') }}' : '{{ __('Post to Trello') }}')">{{ __('Post to Trello') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Hidden File Input for Comment Photos -->
                                <input 
                                    type="file" 
                                    x-ref="photoInput" 
                                    @change="handleFileSelect($event)" 
                                    accept="image/*" 
                                    multiple 
                                    class="hidden"
                                >
                            </div>

                            <!-- Comments List -->
                            @if($isLoadingTrelloComments)
                                <div class="py-4 text-center text-xs text-zinc-500 flex items-center justify-center gap-2">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin text-sky-600" />
                                    <span>{{ __('Loading comments from Trello...') }}</span>
                                </div>
                            @elseif($trelloCommentError)
                                <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2">
                                    <x-lucide-alert-circle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                    <div>
                                        <strong>{{ __('Comments note:') }}</strong> {{ $trelloCommentError }}
                                    </div>
                                </div>
                            @elseif(empty($trelloComments))
                                <div class="py-3 text-center text-xs text-zinc-400">
                                    {{ __('No comments recorded on the Trello card yet.') }}
                                </div>
                            @else
                                <div class="space-y-3 max-h-72 overflow-y-auto pr-1 scrollbar-thin divide-y divide-[#e9e9e7]">
                                    @foreach($trelloComments as $comment)
                                        <div class="pt-3 first:pt-0 space-y-1.5 group/comment">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    @if(!empty($comment['author_avatar']))
                                                        <div x-data="{ imgError: false }" class="shrink-0">
                                                            <img src="{{ $comment['author_avatar'] }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $comment['author_name'] }}" class="w-6 h-6 rounded-full object-cover shadow-2xs border border-stone-200">
                                                            <div x-show="imgError" x-cloak class="w-6 h-6 rounded-full {{ !empty($comment['is_kudos']) ? 'bg-stone-900 text-white' : 'bg-sky-100 text-sky-700' }} font-bold text-[10px] flex items-center justify-center shadow-2xs">
                                                                {{ !empty($comment['author_initials']) ? $comment['author_initials'] : strtoupper(substr($comment['author_name'] ?? 'U', 0, 2)) }}
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="w-6 h-6 rounded-full {{ !empty($comment['is_kudos']) ? 'bg-stone-900 text-white' : 'bg-sky-100 text-sky-700' }} font-bold text-[10px] flex items-center justify-center shrink-0 shadow-2xs">
                                                            {{ !empty($comment['author_initials']) ? $comment['author_initials'] : strtoupper(substr($comment['author_name'] ?? 'U', 0, 2)) }}
                                                        </div>
                                                    @endif
                                                    <span class="text-xs font-semibold text-zinc-900 truncate">
                                                        {{ $comment['author_name'] }}
                                                    </span>
                                                    @if(!empty($comment['is_kudos']))
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium bg-stone-100 text-stone-700 border border-stone-200 shrink-0">
                                                            Kudos DOES
                                                        </span>
                                                    @elseif(!empty($comment['author_username']))
                                                        <span class="text-[10px] text-zinc-400 truncate">
                                                            @({{ $comment['author_username'] }})
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    @if(!empty($comment['date']))
                                                        <span class="text-[10px] text-zinc-400 font-mono">
                                                            {{ \Carbon\Carbon::parse($comment['date'])->diffForHumans() }}
                                                        </span>
                                                    @endif
                                                    @if(!empty($comment['id']))
                                                        <button 
                                                            type="button"
                                                            wire:click="deleteTrelloComment('{{ $comment['id'] }}')"
                                                            wire:confirm="{{ __('Are you sure you want to delete this comment from Trello?') }}"
                                                            wire:loading.attr="disabled"
                                                            class="p-1 rounded text-stone-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                                                            title="{{ __('Delete comment from Trello') }}">
                                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="text-xs text-zinc-700 leading-relaxed pl-7 break-words prose prose-xs prose-stone max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-0.5 [&_ul.task-list]:list-none [&_ul.task-list]:pl-0 [&_li.task-list-item]:list-none [&_h1]:text-sm [&_h1]:font-bold [&_h1]:text-zinc-900 [&_h1]:mt-2.5 [&_h1]:mb-1 [&_h2]:text-xs [&_h2]:font-bold [&_h2]:text-zinc-900 [&_h2]:mt-2 [&_h2]:mb-0.5 [&_h3]:text-xs [&_h3]:font-semibold [&_h3]:text-zinc-800 [&_h3]:mt-1.5 [&_h3]:mb-0.5 [&_hr]:my-2.5 [&_hr]:border-stone-200">
                                                {!! $this->renderCommentMarkdown(!empty($comment['clean_text']) ? $comment['clean_text'] : ($comment['text'] ?? ''), $comment['id'] ?? null) !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                    @endif
                </div>
            </div> <!-- Closes Tab 2: COMMENTS + ADJUNTOS -->
        </div> <!-- Closes Recycled Tab Panels Container -->
    </div> <!-- Closes 3-Tab System -->
    @endif

    </div>


            @else
                @include('livewire.orders.partials.order-detail-skeleton')
            @endif
        </div>

    </div>

    <!-- SUBTASK IMPACT & BLOCKING WARNING MODAL -->
    @if($showSubtaskWarningModal)
        <div 
            class="fixed inset-0 z-[360] bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.cancelSubtaskWarning()">
            <div class="bg-surface border border-line rounded-card w-full max-w-lg p-5 sm:p-6 space-y-4 shadow-panel max-h-[92vh] flex flex-col">
                <div class="shrink-0 flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-card bg-warn-soft text-warn flex items-center justify-center shrink-0">
                            <x-lucide-alert-triangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-ink">
                                {{ __('Subtask check') }}
                            </h3>
                            <p class="text-xs text-ink-3 mt-0.5">
                                {{ __('New task: ":title"', ['title' => $pendingSubtaskData['title'] ?? '']) }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="cancelSubtaskWarning" class="p-1 rounded-control text-ink-3 hover:text-ink-2 hover:bg-sunken transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="overflow-y-auto pr-1 space-y-4 text-xs scrollbar-thin flex-1">
                    @if(!empty($pendingSubtaskData['isOrderBlocked']))
                        <div class="p-4 rounded-card bg-danger-soft border border-danger/30 text-danger space-y-3">
                            <div class="flex items-start gap-2">
                                <x-lucide-ban class="w-4 h-4 text-danger shrink-0 mt-0.5" />
                                <div>
                                    <span class="font-semibold text-danger block">{{ __('Blocked order') }}</span>
                                    <p class="text-danger text-xs mt-0.5">
                                        {{ __('Currently blocked by:') }} <span class="font-semibold">{{ $pendingSubtaskData['blockingReason'] ?? __('Missing Measurements') }}</span>.
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 pt-1 border-t border-danger/30">
                                <label class="font-semibold text-danger block text-xs">
                                    {{ __('Does this subtask resolve the block?') }}
                                </label>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-control border cursor-pointer transition {{ $subtaskBlockResolution === 'unblock' ? 'bg-surface border-danger/30 ring-1 ring-line-strong  font-medium text-ink' : 'bg-danger-soft border-danger/30 text-ink-2 hover:bg-surface' }}">
                                        <input type="radio" wire:model.live="subtaskBlockResolution" value="unblock" class="mt-0.5 text-danger focus:ring-line-strong">
                                        <div>
                                            <span class="block font-semibold">{{ __('Yes, details confirmed (unblock the order)') }}</span>
                                            <span class="block text-meta text-ink-3">{{ __('Marks the details as confirmed and unblocks the order.') }}</span>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-control border cursor-pointer transition {{ $subtaskBlockResolution === 'keep_blocked' ? 'bg-surface border-danger/30 ring-1 ring-line-strong  font-medium text-ink' : 'bg-danger-soft border-danger/30 text-ink-2 hover:bg-surface' }}">
                                        <input type="radio" wire:model.live="subtaskBlockResolution" value="keep_blocked" class="mt-0.5 text-danger focus:ring-line-strong">
                                        <div>
                                            <span class="block font-semibold">{{ __('No, just a follow-up (keep it blocked)') }}</span>
                                            <span class="block text-meta text-ink-3">{{ __('Creates the follow-up task without changing the block.') }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(!empty($pendingSubtaskData['isOrderApproved']))
                        <div class="p-4 rounded-card bg-warn-soft border border-warn/30 text-warn space-y-3">
                            <div class="flex items-start gap-2">
                                <x-lucide-check-circle-2 class="w-4 h-4 text-warn shrink-0 mt-0.5" />
                                <div>
                                    <span class="font-semibold text-warn block">{{ __('Order already approved') }}</span>
                                    <p class="text-warn text-xs mt-0.5">
                                        {{ __('Approved by') }} <span class="font-semibold">{{ $pendingSubtaskData['approvalType'] ?? __('Client') }}</span> ({{ $pendingSubtaskData['approvedAt'] ?? '' }}).
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 pt-1 border-t border-warn/30">
                                <label class="font-semibold text-warn block text-xs">
                                    {{ __('Does this subtask change the artwork or need re-approval?') }}
                                </label>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-control border cursor-pointer transition {{ $subtaskApprovalImpact === 'invalidate' ? 'bg-surface border-warn/30 ring-1 ring-line-strong  font-medium text-ink' : 'bg-warn-soft border-warn/30 text-ink-2 hover:bg-surface' }}">
                                        <input type="radio" wire:model.live="subtaskApprovalImpact" value="invalidate" class="mt-0.5 text-warn focus:ring-line-strong">
                                        <div>
                                            <span class="block font-semibold text-danger">{{ __('Cancel the previous approval (needs new sign-off)') }}</span>
                                            <span class="block text-meta text-ink-3">{{ __('The design changed. The approval is cancelled so it isn\'t sent to production by mistake.') }}</span>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-control border cursor-pointer transition {{ $subtaskApprovalImpact === 'keep' ? 'bg-surface border-warn/30 ring-1 ring-line-strong  font-medium text-ink' : 'bg-warn-soft border-warn/30 text-ink-2 hover:bg-surface' }}">
                                        <input type="radio" wire:model.live="subtaskApprovalImpact" value="keep" class="mt-0.5 text-warn focus:ring-line-strong">
                                        <div>
                                            <span class="block font-semibold text-ok">{{ __('Keep the approval (technical or internal tweak only)') }}</span>
                                            <span class="block text-meta text-ink-3">{{ __('The approved artwork doesn\'t change visually, so no extra client approval is needed.') }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2.5 pt-3 border-t border-line">
                    <button 
                        type="button" 
                        wire:click="cancelSubtaskWarning" 
                        class="px-4 py-2 rounded-card border border-line-strong text-ink-2 hover:bg-sunken text-xs font-semibold transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="button" 
                        wire:click="confirmSubtaskWarning" 
                        class="px-5 py-2 rounded-card bg-ink hover:bg-black text-white text-xs font-semibold  transition cursor-pointer">
                        {{ __('Confirm and create subtask') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- APPROVAL ACTION MODAL -->
    @if($showApprovalModal)
        <div 
            class="fixed inset-0 z-[350] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.closeApprovalModal()">
            <div class="bg-surface border border-line rounded-card w-full max-w-2xl p-5 sm:p-6 space-y-4 shadow-panel max-h-[92vh] flex flex-col">
                <div class="shrink-0 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-ink">
                            @if($pendingProductionStatus === \App\Enums\CoreStatus::EN_PRODUCCION->value || $pendingProductionStatus === 'EN PRODUCCIÓN')
                                {{ __('Approval Required for Production') }}
                            @else
                                {{ __('Approval Confirmation') }}
                            @endif
                        </h3>
                        <p class="text-xs text-ink-3 mt-0.5">{{ __('Validate approval and record approval proof to continue.') }}</p>
                    </div>
                    <button type="button" wire:click="closeApprovalModal" class="p-1 rounded-control text-ink-3 hover:text-ink-2 hover:bg-sunken transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                @if($pendingProductionStatus === \App\Enums\CoreStatus::EN_PRODUCCION->value || $pendingProductionStatus === 'EN PRODUCCIÓN')
                    <div class="p-3 rounded-card bg-sunken border border-line text-ink-2 text-xs flex items-start gap-2.5 shrink-0">
                        <x-lucide-alert-circle class="w-4 h-4 text-ink-2 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold block">{{ __('Move to Production Approval Required') }}</span>
                            {{ __('To send this order to Production, it must be approved first. Record who approved it and enter the comment or approval proof.') }}
                        </div>
                    </div>
                @endif

                <div class="overflow-y-auto pr-1 space-y-4 text-xs scrollbar-thin flex-1">
                    <!-- Validation Type Selection -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-ink">
                            {{ __('Who validated the approval?') }} <span class="text-danger">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button 
                                type="button" 
                                wire:click="$set('approvalType', 'camila')"
                                class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $approvalType === 'camila' ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                <div class="flex items-start justify-between w-full gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs {{ $approvalType === 'camila' ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                        <span class="text-sm sm:text-base font-semibold {{ $approvalType === 'camila' ? 'text-ok' : 'text-ink' }} block  mt-0.5 leading-snug">{{ __('Camila') }}</span>
                                    </div>
                                    @if($approvalType === 'camila')
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </span>
                                    @endif
                                </div>
                                <div class="text-meta {{ $approvalType === 'camila' ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                    {{ __('Internal validation') }}
                                </div>
                            </button>

                            <button 
                                type="button" 
                                wire:click="$set('approvalType', 'manager')"
                                class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ ($approvalType === 'manager' || $approvalType === 'gabriela') ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                <div class="flex items-start justify-between w-full gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs {{ ($approvalType === 'manager' || $approvalType === 'gabriela') ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                        <div class="flex items-baseline gap-1 mt-0.5 flex-wrap">
                                            <span class="text-sm sm:text-base font-semibold {{ ($approvalType === 'manager' || $approvalType === 'gabriela') ? 'text-ok' : 'text-ink' }}  leading-snug">{{ $managerName ?? \App\Models\Setting::getManagerName() }}</span>
                                            <span class="text-xs font-normal {{ ($approvalType === 'manager' || $approvalType === 'gabriela') ? 'text-ok' : 'text-ink-3' }}">({{ __('Manager') }})</span>
                                        </div>
                                    </div>
                                    @if($approvalType === 'manager' || $approvalType === 'gabriela')
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </span>
                                    @endif
                                </div>
                                <div class="text-meta {{ ($approvalType === 'manager' || $approvalType === 'gabriela') ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                    {{ __('Manager Validation') }}
                                </div>
                            </button>

                            <button 
                                type="button" 
                                wire:click="$set('approvalType', 'cliente')"
                                class="relative p-3.5 sm:p-4 rounded-card border text-left transition-all flex flex-col justify-between cursor-pointer select-none {{ $approvalType === 'cliente' ? 'border-ok/30 bg-ok-soft ring-2 ring-line-strong ' : 'border-line bg-canvas hover:bg-sunken hover:border-line-strong text-ink-2' }}">
                                <div class="flex items-start justify-between w-full gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs {{ $approvalType === 'cliente' ? 'text-ok font-medium' : 'text-ink-3 font-normal' }} block">{{ __('Approved by') }}</span>
                                        <span class="text-sm sm:text-base font-semibold {{ $approvalType === 'cliente' ? 'text-ok' : 'text-ink' }} block  mt-0.5 leading-snug">{{ __('Client') }}</span>
                                    </div>
                                    @if($approvalType === 'cliente')
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-ok text-white shrink-0  mt-0.5">
                                            <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        </span>
                                    @endif
                                </div>
                                <div class="text-meta {{ $approvalType === 'cliente' ? 'text-ok font-medium' : 'text-ink-3' }} leading-tight mt-2.5">
                                    {{ __('Direct confirmation') }}
                                </div>
                            </button>
                        </div>
                    </div>

                    @if($order && $order->hasElements())
                        <!-- Scope of Approval (Uno / Varios / Todos) -->
                        <div class="space-y-3 p-3.5 bg-canvas border border-line rounded-card">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-ink flex items-center gap-1.5">
                                    <x-lucide-check-square class="w-4 h-4 text-ink-2" />
                                    <span>{{ __('What do you want to approve?') }}</span>
                                </label>
                                <span class="text-meta text-ink-3 font-medium">{{ __('Select the scope') }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <button 
                                    type="button" 
                                    wire:click="$set('approvalScope', 'all')" 
                                    class="flex items-center gap-2.5 p-2.5 rounded-card border text-xs font-medium transition cursor-pointer select-none text-left {{ $approvalScope === 'all' ? 'border-line bg-sunken text-ink-2 font-semibold ring-2 ring-line-strong ' : 'border-line bg-surface hover:bg-sunken text-ink-2' }}">
                                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 {{ $approvalScope === 'all' ? 'border-line bg-ink' : 'border-line-strong bg-surface' }}">
                                        @if($approvalScope === 'all')
                                            <div class="w-1.5 h-1.5 rounded-full bg-surface"></div>
                                        @endif
                                    </div>
                                    <span>{{ __('Whole order') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('approvalScope', 'stage')" 
                                    class="flex items-center gap-2.5 p-2.5 rounded-card border text-xs font-medium transition cursor-pointer select-none text-left {{ $approvalScope === 'stage' ? 'border-line bg-sunken text-ink-2 font-semibold ring-2 ring-line-strong ' : 'border-line bg-surface hover:bg-sunken text-ink-2' }}">
                                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 {{ $approvalScope === 'stage' ? 'border-line bg-ink' : 'border-line-strong bg-surface' }}">
                                        @if($approvalScope === 'stage')
                                            <div class="w-1.5 h-1.5 rounded-full bg-surface"></div>
                                        @endif
                                    </div>
                                    <span>{{ __('By stage') }}</span>
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('approvalScope', 'elements')" 
                                    class="flex items-center gap-2.5 p-2.5 rounded-card border text-xs font-medium transition cursor-pointer select-none text-left {{ $approvalScope === 'elements' ? 'border-line bg-sunken text-ink-2 font-semibold ring-2 ring-line-strong ' : 'border-line bg-surface hover:bg-sunken text-ink-2' }}">
                                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 {{ $approvalScope === 'elements' ? 'border-line bg-ink' : 'border-line-strong bg-surface' }}">
                                        @if($approvalScope === 'elements')
                                            <div class="w-1.5 h-1.5 rounded-full bg-surface"></div>
                                        @endif
                                    </div>
                                    <span>{{ __('Specific items') }}</span>
                                </button>
                            </div>

                            @if($approvalScope === 'stage')
                                <div class="pt-1 space-y-1">
                                    <label class="block text-meta font-semibold text-ink-2">{{ __('Select the stage to approve:') }}</label>
                                    <select wire:model.live="selectedApprovalStageId" class="w-full bg-surface border border-line rounded-card p-2.5 text-xs text-ink focus:outline-none focus:border-line-strong ">
                                        <option value="">{{ __('Select a stage...') }}</option>
                                        @foreach($order->stages as $stg)
                                            <option value="{{ $stg->id }}">{{ $stg->name }} ({{ __(':count pieces', ['count' => $stg->elements->count()]) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($approvalScope === 'elements')
                                <div class="pt-1 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-meta font-semibold text-ink-2">{{ __('Select the approved items:') }}</label>
                                        <div class="flex items-center gap-2 text-meta">
                                            <button type="button" wire:click="selectAllApprovalElements" class="text-ink-2 hover:text-ink-2 font-semibold hover:underline cursor-pointer">
                                                {{ __('Select all') }}
                                            </button>
                                            <span class="text-ink-3">•</span>
                                            <button type="button" wire:click="deselectAllApprovalElements" class="text-ink-3 hover:text-ink-2 font-medium hover:underline cursor-pointer">
                                                {{ __('Uncheck') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="border border-line rounded-card bg-surface divide-y divide-line max-h-52 overflow-y-auto ">
                                        @forelse($order->elements as $el)
                                            @php
                                                $isElSelected = in_array((string)$el->id, array_map('strval', $selectedApprovalElementIds), true) || in_array($el->id, $selectedApprovalElementIds, true);
                                            @endphp
                                            <label class="flex items-center gap-2.5 p-2.5 cursor-pointer hover:bg-sunken transition text-xs select-none {{ $isElSelected ? 'bg-sunken' : '' }}">
                                                <input 
                                                    type="checkbox" 
                                                    wire:model.live="selectedApprovalElementIds" 
                                                    value="{{ $el->id }}" 
                                                    class="w-4 h-4 rounded border-line-strong text-ink-2 focus:ring-line-strong cursor-pointer" 
                                                />
                                                <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
                                                    <span class="font-medium text-ink {{ $el->is_approved ? 'text-ink-2' : '' }}">{{ $el->name }}</span>
                                                    @if($el->dimensions)
                                                        <span class="text-meta font-mono text-ink-3 bg-sunken px-1.5 py-0.5 rounded border border-line">
                                                            {{ $el->dimensions }}
                                                        </span>
                                                    @endif
                                                    @if($el->is_approved)
                                                        <span class="inline-flex items-center gap-0.5 text-meta font-semibold text-ok bg-ok-soft border border-ok/30 px-1.5 py-0.5 rounded">
                                                            <x-lucide-check class="w-2.5 h-2.5" />
                                                            <span>{{ __('Approved') }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($el->stage)
                                                    <span class="text-meta font-semibold text-ink-2 bg-sunken px-2 py-0.5 rounded-full border border-line shrink-0">
                                                        {{ $el->stage->name }}
                                                    </span>
                                                @endif
                                            </label>
                                        @empty
                                            <div class="p-4 text-center text-xs text-ink-3">
                                                {{ __('No items registered on this order.') }}
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Soporte de Aprobación Field (Texto o Imagen con Paste y Drag&Drop) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-semibold text-ink">
                                {{ __('Approval proof and additional notes') }} <span class="text-danger">*</span>
                            </label>
                            <span class="text-meta text-ink-3">{{ __('Text or Image (at least one)') }}</span>
                        </div>

                        <div 
                            x-data="{
                                isDragging: false,
                                clientPreviewUrl: null,
                                setPreview(file) {
                                    if (!file || !(file instanceof Blob) || (file.type && !file.type.startsWith('image/'))) return;
                                    if (this.clientPreviewUrl) {
                                        URL.revokeObjectURL(this.clientPreviewUrl);
                                    }
                                    this.clientPreviewUrl = URL.createObjectURL(file);
                                },
                                clearPreview() {
                                    if (this.clientPreviewUrl) {
                                        URL.revokeObjectURL(this.clientPreviewUrl);
                                        this.clientPreviewUrl = null;
                                    }
                                },
                                handlePaste(e) {
                                    const items = (e.clipboardData || window.clipboardData)?.items;
                                    if (!items) return;
                                    for (let i = 0; i < items.length; i++) {
                                        if (items[i].type.indexOf('image') !== -1) {
                                            e.preventDefault();
                                            const file = items[i].getAsFile();
                                            if (file) {
                                                this.setPreview(file);
                                                $wire.upload('approvalImage', file);
                                            }
                                            return;
                                        }
                                    }
                                },
                                handleDrop(e) {
                                    this.isDragging = false;
                                    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                                        const file = e.dataTransfer.files[0];
                                        if (file && file.type.startsWith('image/')) {
                                            this.setPreview(file);
                                            $wire.upload('approvalImage', file);
                                        }
                                    }
                                }
                            }"
                            x-init="$watch('$wire.approvalImage', value => { if (!value) clearPreview(); })"
                            @dragover.prevent="isDragging = true"
                            @dragenter.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)"
                            @paste="handlePaste($event)"
                            tabindex="0"
                            class="relative border rounded-card p-3 transition-all bg-surface flex flex-col justify-between min-h-[115px]"
                            :class="isDragging ? 'border-ok/30 ring-2 ring-line-strong bg-ok-soft' : 'border-line focus-within:border-ok/30 focus-within:ring-2 focus-within:ring-line-strong'">
                            
                            <textarea 
                                wire:model="approvalComment"
                                rows="3"
                                placeholder="{{ __('Type a comment here or paste a screenshot with Ctrl+V / Cmd+V (e.g. approval via WhatsApp, email, etc.)...') }}"
                                class="w-full text-xs text-ink placeholder:text-ink-3 bg-transparent resize-none border-0 p-0 focus:outline-none focus:ring-0 leading-relaxed outline-none"></textarea>

                            <!-- Uploading loader -->
                            <div wire:loading.flex wire:target="approvalImage" class="flex flex-row items-center gap-2 mt-2 p-2 rounded-control bg-ok-soft border border-ok/30 text-xs text-ok" style="display: none;">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin text-ok shrink-0 inline-block" />
                                <span>{{ __('Loading support image...') }}</span>
                            </div>

                            <!-- Uploaded image preview -->
                            @if($approvalImage && !$errors->has('approvalImage'))
                                @php
                                    $approvalPreviewUrl = null;
                                    if (method_exists($approvalImage, 'temporaryUrl')) {
                                        try {
                                            $approvalPreviewUrl = $approvalImage->temporaryUrl();
                                        } catch (\Throwable $e) {
                                            $approvalPreviewUrl = null;
                                        }
                                    }
                                @endphp
                                <div wire:loading.remove wire:target="approvalImage" class="mt-2.5 p-2 rounded-control bg-canvas border border-line flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-12 h-12 rounded-control border border-line-strong  shrink-0 overflow-hidden bg-sunken flex items-center justify-center relative">
                                            <template x-if="clientPreviewUrl">
                                                <img :src="clientPreviewUrl" alt="{{ __('Proof') }}" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!clientPreviewUrl">
                                                @if($approvalPreviewUrl)
                                                    <img src="{{ $approvalPreviewUrl }}" alt="{{ __('Proof') }}" class="w-full h-full object-cover" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                    <div class="hidden w-full h-full items-center justify-center bg-sunken text-ink-3">
                                                        <x-lucide-image class="w-5 h-5" />
                                                    </div>
                                                @else
                                                    <x-lucide-image class="w-5 h-5 text-ink-3" />
                                                @endif
                                            </template>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="text-xs font-semibold text-ink truncate block">{{ __('Attached proof image') }}</span>
                                            <span class="text-meta text-ink-3 block">{{ number_format($approvalImage->getSize() / 1024, 1) }} KB</span>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="removeApprovalImage" @click="clearPreview()" class="p-1.5 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer" title="{{ __('Remove image') }}">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                </div>
                            @endif

                            <!-- Bottom Action Bar / Dropzone hints -->
                            <div class="flex items-center justify-between pt-2 border-t border-line mt-2 text-meta text-ink-3">
                                <label for="approval-image-file" class="inline-flex items-center gap-1.5 font-medium hover:text-ok cursor-pointer transition select-none">
                                    <x-lucide-paperclip class="w-3.5 h-3.5 text-ink-3" />
                                    <span>{{ __('Attach image') }}</span>
                                    <input type="file" id="approval-image-file" wire:model="approvalImage" accept="image/*" class="hidden" @change="if ($event.target.files && $event.target.files[0]) { setPreview($event.target.files[0]); }">
                                </label>

                                <span class="text-meta text-ink-3 flex items-center gap-1 select-none">
                                    <x-lucide-image class="w-3 h-3 text-ink-3" />
                                    <span>{{ __('Paste with Cmd+V or drag here') }}</span>
                                </span>
                            </div>
                        </div>

                        @error('approvalSupport')
                            <p class="text-xs text-danger font-medium flex items-center gap-1 mt-1">
                                <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                        @error('approvalImage')
                            <p class="text-xs text-danger font-medium flex items-center gap-1 mt-1">
                                <x-lucide-alert-circle class="w-3.5 h-3.5 shrink-0" />
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <!-- Medidas Confirmadas Checkbox -->
                    <label class="flex items-start gap-3 p-3.5 rounded-card border border-line bg-canvas hover:bg-sunken transition cursor-pointer select-none">
                        <input type="checkbox" wire:model="measuresConfirmed" class="w-4 h-4 mt-0.5 rounded border-line-strong text-ink focus:ring-stone-400">
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <span class="font-semibold text-ink text-xs block">{{ __('Measurements 100% confirmed') }}</span>
                            @if(!$measuresConfirmed)
                                <span class="text-meta text-warn font-medium block">{{ __('If not checked, the order will be sent to RESOLVE to confirm measurements.') }}</span>
                            @else
                                <span class="text-meta text-ok font-medium block">{{ __('Measurements ready for production.') }}</span>
                            @endif
                        </div>
                    </label>

                    <!-- Estimado Aprobado Checkbox -->
                    <label class="flex items-start gap-3 p-3.5 rounded-card border border-line bg-canvas hover:bg-sunken transition cursor-pointer select-none">
                        <input type="checkbox" wire:model="estimateApproved" class="w-4 h-4 mt-0.5 rounded border-line-strong text-ink focus:ring-stone-400">
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <span class="font-semibold text-ink text-xs block">{{ __('Quote / Estimate approved') }}</span>
                            @if(!$estimateApproved)
                                <span class="text-meta text-warn font-medium block">{{ __('If not checked, a pending estimate warning will be logged.') }}</span>
                            @else
                                <span class="text-meta text-ok font-medium block">{{ __('Estimate verified by client.') }}</span>
                            @endif
                        </div>
                    </label>

                    <!-- Live Dynamic Outcome Preview Banner -->
                    <div class="p-3 rounded-card border text-xs leading-relaxed transition-all {{ !$measuresConfirmed ? 'bg-warn-soft border-warn/30 text-warn' : (!$estimateApproved ? 'bg-warn-soft border-warn/30 text-warn' : 'bg-ok-soft border-ok/30 text-ok') }}">
                        <div class="flex items-start gap-2">
                            @if(!$measuresConfirmed)
                                <x-lucide-alert-triangle class="w-4 h-4 text-warn shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-semibold block text-meta   text-warn">{{ __('Result: Resolve (Blocked)') }}</strong>
                                    {{ __('The order will move to') }} <span class="font-semibold">{{ __('INCOMING') }}</span> {{ __('with substatus') }} <span class="font-semibold">{{ __('BLOCKED') }}</span> {{ __('and a high-priority measurement resolution task will be created') }} (SLA {{ $order?->isUrgente() ? __('same day') : '24h' }}).
                                </div>
                            @elseif(!$estimateApproved)
                                <x-lucide-info class="w-4 h-4 text-warn shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-semibold block text-meta   text-warn">{{ __('Result: Pending Estimate') }}</strong>
                                    {{ __('The order will move to the designer\'s inbox with substatus') }} <span class="font-semibold">{{ __('AWAITING ESTIMATE APPROVAL') }}</span> (SLA {{ $order?->isUrgente() ? __('same day') : '24h' }}).
                                </div>
                            @else
                                <x-lucide-check-circle-2 class="w-4 h-4 text-ok shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-semibold block text-meta   text-ok">{{ __('Result: Ready for Print') }}</strong>
                                    {{ __('The order will move to the designer\'s inbox with substatus') }} <span class="font-semibold">{{ __('READY FOR ALTA') }}</span> @if($order?->isUrgente()) (<span class="font-semibold text-danger">{{ __('SAME DAY SLA') }}</span> {{ __('due to urgent order') }}). @else ({{ __('24h SLA for high-resolution prepress') }}). @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-line shrink-0">
                    <button type="button" wire:click="closeApprovalModal" class="px-3.5 py-1.5 rounded-control bg-sunken hover:bg-line text-ink-2 text-xs font-medium transition cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="button" 
                        wire:click="submitApproval" 
                        wire:loading.attr="disabled"
                        class="px-4 py-1.5 rounded-control bg-ok hover:bg-ok disabled:opacity-50 text-white font-medium text-xs  transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-loader-2 wire:loading wire:target="submitApproval" class="w-3.5 h-3.5 animate-spin" />
                        <span wire:loading.remove wire:target="submitApproval">{{ __('Process Approval') }}</span>
                        <span wire:loading wire:target="submitApproval">{{ __('Processing...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- DELAY RESOLUTION MODAL -->
    @if($showDelayModal)
        <div 
            class="fixed inset-0 z-[350] bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.set('showDelayModal', false)"
            @keydown.window.enter.prevent="if($event.target.tagName !== 'TEXTAREA') $wire.submitDelayResolution()">
            <div class="bg-surface border border-line rounded-card w-full max-w-md p-5 space-y-4 shadow-panel">
                <div>
                    <h3 class="text-base font-semibold text-ink">{{ __('Resolve Delay') }}</h3>
                    <p class="text-xs text-ink-3 mt-0.5">{{ __('Enter the new promised date to the client to resolve the Overdue status.') }}</p>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-medium text-ink-2 block mb-1">{{ __('New Promised Date to Client:') }}</label>
                        <input type="date" wire:model="clientPromisedDate" class="bg-canvas border border-line rounded-control px-3 py-1.5 text-xs text-ink focus:outline-none w-full font-mono">
                    </div>

                    <div>
                        <label class="font-medium text-ink-2 block mb-1">{{ __('Reason / Delay Explanation:') }}</label>
                        <textarea wire:model="delayReason" rows="3" class="bg-canvas border border-line rounded-control p-2.5 text-xs text-ink focus:outline-none w-full font-normal"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button wire:click="$set('showDelayModal', false)" class="px-3 py-1.5 rounded-control bg-sunken text-ink-2 text-xs font-medium">
                        {{ __('Cancel') }}
                    </button>
                    <button wire:click="submitDelayResolution" class="px-3.5 py-1.5 rounded-control bg-danger hover:bg-danger text-white font-medium text-xs ">
                        {{ __('Save & Resolve Delay') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- UNBLOCK MODAL -->
    <x-orders.reason-sheet
        :show="$showUnblockModal"
        :title="__('Unblock order')"
        :description="trim(($order->company_name ?? '') . ' — ' . ($order->task_name ?? ''), ' —')"
        confirm="confirmUnblock"
        cancel="closeUnblockModal"
        :confirm-label="__('Confirm unblock')"
        icon="unlock"
        :presets="[
            'Medidas confirmadas y recibidas' => __('Measurements confirmed'),
            'Cliente aprobó información' => __('Client approved information'),
            'Estimado aprobado' => __('Estimate approved'),
            'Resuelto por Atención a Clientes' => __('Resolved by Customer Service'),
        ]"
        preset-model="unblockReason"
        :selected="$unblockReason"
        :preset-label="__('How was the block resolved or what action was taken?')"
        reason-model="unblockReason"
        :reason-placeholder="__('Describe how the block was resolved...')"
        :reason-rows="3"
    />

    <!-- BLOCK MODAL -->
    <x-orders.reason-sheet
        :show="$showBlockModal"
        :title="__('Block order')"
        :description="trim(($order->company_name ?? '') . ' — ' . ($order->task_name ?? ''), ' —')"
        confirm="confirmBlock"
        cancel="closeBlockModal"
        :confirm-label="__('Block order')"
        tone="danger"
        icon="alert-octagon"
        :presets="[
            'FALTAN MEDIDAS' => __('Missing Measurements'),
            'FALTA LOGO' => __('Missing Logo / Artwork'),
            'FALTA APROBACIÓN DE ESTIMADO' => __('Awaiting Estimate Approval'),
            'ESPERANDO CLIENTE' => __('Waiting for Client'),
            'OTROS' => __('Other Reason'),
        ]"
        preset-model="blockReason"
        :selected="$blockReason"
        :preset-label="__('Block reason')"
        reason-model="blockReasonOther"
        reason-type="input"
        :reason-visible="$blockReason === 'OTROS'"
        :reason-label="__('Specify other reason')"
        :reason-placeholder="__('e.g. Waiting for special material from supplier...')"
    >
        <div class="space-y-1">
            <label class="text-meta font-medium text-ink-2 block">{{ __('Additional details or comments (optional)') }}</label>
            <textarea wire:model="blockComment" rows="2" placeholder="{{ __('Briefly explain the situation...') }}" class="ui-input !h-auto py-1.5"></textarea>
        </div>
        <div class="pt-1 border-t border-line">
            <label class="flex items-center gap-2 cursor-pointer text-ink font-medium">
                <input type="checkbox" wire:model="requireCustomerService" class="rounded border-line-strong w-4 h-4">
                <span>{{ __('Requires attention / follow-up by client or account rep') }}</span>
            </label>
            <p class="text-meta text-ink-3 pl-6 mt-0.5">{{ __('Creates a pending task to follow up with the client contact or account rep.') }}</p>
        </div>
    </x-orders.reason-sheet>

    <!-- ON HOLD REASON MODAL -->
    <x-orders.reason-sheet
        :show="$showOnHoldModal"
        :title="__('Reason for putting on hold')"
        :description="trim(($order->company_name ?? '') . ' — ' . ($order->task_name ?? ''), ' —')"
        confirm="confirmOnHold"
        cancel="closeOnHoldModal"
        :confirm-label="__('Put on On Hold')"
        icon="pause-circle"
        reason-model="onHoldReason"
        :reason-label="__('Reason or comment')"
        :reason-placeholder="__('e.g. Waiting for quote confirmation from client...')"
    />

    <!-- RESUME ORDER MODAL -->
    <x-orders.reason-sheet
        :show="$showResumeModal"
        :title="__('Reason for resuming the order')"
        :description="trim(($order->company_name ?? '') . ' — ' . ($order->task_name ?? ''), ' —')"
        confirm="confirmResume"
        cancel="closeResumeModal"
        :confirm-label="__('Resume order')"
        icon="play-circle"
        reason-model="resumeReason"
        :reason-label="__('Reason or resume note')"
        :reason-placeholder="__('e.g. Client approved quote / Received client measurements...')"
    />

    <!-- ARCHIVE ORDER SUBSTATUS SELECTION MODAL -->
    @include('livewire.orders.partials.archive-modal', [
        'targetOrder' => $order ?? null,
        'archivedSubstatuses' => $archivedSubstatuses ?? null,
    ])

    <!-- IN-APP MEDIA PREVIEW LIGHTBOX MODAL -->
    @if($showMediaPreviewModal)
        <div 
            class="fixed inset-0 z-[450] bg-zinc-950/80 backdrop-blur-xl flex flex-col items-center justify-center p-3 sm:p-6 transition-all animate-in fade-in duration-150"
            @keydown.window.escape.prevent="$wire.closeMediaPreview()">
            
            <!-- Header Bar -->
            <div class="w-full max-w-5xl bg-zinc-900/90 border border-white/10 rounded-t-2xl p-3 sm:px-5 flex items-center justify-between gap-3 text-white shadow-2xl backdrop-blur-md">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-medium tracking-wide bg-sky-500/15 text-sky-300 border border-sky-400/25">
                        {{ strtoupper($previewMediaType) }}
                    </span>
                    <h3 class="font-medium text-xs sm:text-sm text-zinc-100 truncate" title="{{ $previewMediaTitle }}">
                        {{ $previewMediaTitle }}
                    </h3>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @php
                        $lightboxDownloadUrl = str_contains($previewMediaUrl, '?') 
                            ? $previewMediaUrl . '&download=1&filename=' . urlencode($previewMediaTitle) 
                            : $previewMediaUrl . '?download=1&filename=' . urlencode($previewMediaTitle);
                    @endphp
                    <a href="{{ $lightboxDownloadUrl }}" download="{{ $previewMediaTitle }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-zinc-200 text-xs font-medium border border-white/10 transition flex items-center gap-1.5 shadow-2xs">
                        <x-lucide-download class="w-3.5 h-3.5 text-sky-400" />
                        <span class="hidden sm:inline">{{ __('Download') }}</span>
                    </a>
                    <button wire:click="closeMediaPreview" type="button" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/15 text-zinc-300 hover:text-white flex items-center justify-center border border-white/10 transition cursor-pointer" title="{{ __('Close (Esc)') }}">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="w-full max-w-5xl bg-zinc-950/90 border-x border-b border-white/10 rounded-b-2xl p-3 sm:p-5 flex items-center justify-center min-h-[50vh] max-h-[82vh] overflow-auto shadow-2xl relative">
                @if($previewMediaType === 'image')
                    <div x-data="{ imgError: false }" class="flex flex-col items-center justify-center p-2">
                        <img src="{{ $previewMediaUrl }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $previewMediaTitle }}" class="max-w-full max-h-[76vh] object-contain rounded-xl shadow-2xl border border-white/10">
                        <div x-show="imgError" x-cloak class="p-8 text-center text-zinc-400 flex flex-col items-center gap-2">
                            <x-lucide-image-off class="w-12 h-12 stroke-[1.5] text-zinc-500" />
                            <p class="text-sm font-medium">{{ __('The image preview couldn\'t be loaded') }}</p>
                        </div>
                    </div>
                @elseif($previewMediaType === 'pdf')
                    <iframe src="{{ $previewMediaUrl }}" class="w-full h-[76vh] rounded-xl border-0 bg-white shadow-xl"></iframe>
                @else
                    <iframe src="{{ $previewMediaUrl }}" class="w-full h-[76vh] rounded-xl border-0 bg-white shadow-xl"></iframe>
                @endif
            </div>
        </div>
    @endif
</div>

