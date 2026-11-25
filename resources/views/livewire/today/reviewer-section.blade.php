@php
    $groups = $this->groups;
    $deliverableCount = collect($groups)->sum(fn ($group) => $group->readiness->total);
@endphp

<div class="space-y-3">
    <div>
        <h2 class="text-heading font-semibold text-ink">{{ __('Internal review') }}</h2>
        <p class="text-body text-ink-3">{{ trans_choice('{1} :count deliverable in :orders work orders|[2,*] :count deliverables in :orders work orders', $deliverableCount, ['orders' => collect($groups)->pluck('deliverables')->flatten(1)->pluck('work_order_id')->unique()->count()]) }}</p>
    </div>

    @forelse($groups as $group)
        @php
            $workOrder = $group->deliverables->first()->workOrder;
            $readiness = $group->readiness;
            $key = $workOrder->id.'-'.($group->phase?->id ?? 0);
        @endphp
        <section class="ui-card" wire:key="review-{{ $key }}">
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
                <x-wo-badge :number="$workOrder->number" />
                <span class="truncate text-row font-medium text-ink">{{ \App\Support\TitleCase::from($workOrder->displayName() ?: $group->deliverables->first()->company_name) }}</span>
                @if($group->phase)<x-ui.tag>{{ $group->phase->name }}</x-ui.tag>@endif
                <span class="flex-1"></span>
                <span class="text-meta tabular-nums text-ink-3">{{ __(':approved / :total approved', ['approved' => $readiness->approved, 'total' => $readiness->total]) }}</span>
                <button type="button" wire:click="sendBatch({{ $workOrder->id }}, {{ $group->phase?->id ?? 'null' }})" wire:loading.attr="disabled"
                    class="ui-btn ui-btn-primary ui-btn-sm" @disabled(! $readiness->ready)>{{ __('Send to client') }}</button>
            </div>
            <ul class="divide-y divide-line">
                @foreach($group->deliverables->filter(fn ($each) => $each->status === \App\Domain\Delivery\Enums\DeliverableStatus::INTERNAL_REVIEW) as $deliverable)
                    @php
                        $stopped = \App\Application\Delivery\Data\BatchReadiness::isStopped($deliverable);
                        $approved = \App\Application\Delivery\Data\BatchReadiness::hasReviewerApproval($deliverable);
                    @endphp
                    <li class="px-4 py-2.5" wire:key="review-d-{{ $deliverable->id }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })" class="min-w-0 flex-1 truncate text-left text-body font-medium text-ink hover:underline">{{ $deliverable->task_name }}</button>
                            @if($deliverable->assignee)<span class="text-meta text-ink-3">{{ $deliverable->assignee->name }}</span>@endif
                            @if($stopped)
                                <x-ui.tag tone="warn">{{ $deliverable->openBlock ? __('Blocked') : __('On hold') }}</x-ui.tag>
                            @elseif($approved)
                                <x-ui.tag tone="ok" icon="lucide-check">{{ __('Approved') }}</x-ui.tag>
                            @else
                                <button type="button" wire:click="startRequestChanges({{ $deliverable->id }})" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Request changes') }}</button>
                                <button type="button" wire:click="approve({{ $deliverable->id }})" class="ui-btn ui-btn-primary ui-btn-sm">{{ __('Approve') }}</button>
                            @endif
                        </div>
                        @if($changesFor === $deliverable->id)
                            <form wire:submit="requestChanges" class="mt-2 space-y-2">
                                <textarea wire:model="changesNote" rows="2" class="ui-input w-full" placeholder="{{ __('What needs to change?') }}" aria-label="{{ __('Note for the designer') }}"></textarea>
                                @error('changesNote')<p class="text-meta text-danger">{{ $message }}</p>@enderror
                                <div class="flex gap-2">
                                    <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">{{ __('Send back to design') }}</button>
                                    <button type="button" wire:click="cancelRequestChanges" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Cancel') }}</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($readiness->leftBehind->isNotEmpty())
                <div class="border-t border-line bg-sunken/40 px-4 py-2 text-meta text-ink-3">
                    <span class="font-medium text-ink-2">{{ __('Left behind') }}:</span>
                    {{ $readiness->leftBehind->map(fn ($left) => $left->task_name.' ('.$left->status->label().')')->implode(', ') }}
                </div>
            @endif
        </section>
    @empty
        <div class="ui-card"><x-ui.empty icon="check-circle-2" :title="__('Nothing waiting for review')" :description="__('Work sent to internal review will show up here.')" /></div>
    @endforelse
</div>
