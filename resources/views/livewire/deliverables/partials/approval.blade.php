@php
    $standing = $this->standingApprovals;
    $reviewerApproval = $standing->first(fn ($approval) => in_array($approval->source, [\App\Domain\Delivery\Enums\ApprovalSource::REVIEWER, \App\Domain\Delivery\Enums\ApprovalSource::MANAGER], true));
    $clientApproval = $standing->first(fn ($approval) => in_array($approval->source, [\App\Domain\Delivery\Enums\ApprovalSource::CLIENT, \App\Domain\Delivery\Enums\ApprovalSource::MANAGER], true) && ($approval->measurements_confirmed || $approval->estimate_approved));
    $inInternalReview = $deliverable->status === \App\Domain\Delivery\Enums\DeliverableStatus::INTERNAL_REVIEW;
    $inClientReview = $deliverable->status === \App\Domain\Delivery\Enums\DeliverableStatus::CLIENT_REVIEW;
@endphp

<div class="space-y-5">
    <section aria-label="{{ __('Reviewer approval') }}">
        <h3 class="ui-section-title">{{ __('Reviewer approval') }}</h3>
        @if($reviewerApproval)
            <p class="mt-1 flex flex-wrap items-center gap-2 text-body text-ink-2" data-reviewer-approval>
                <x-ui.tag tone="ok" icon="check">{{ __('Approved') }}</x-ui.tag>
                <span>{{ __(':name · :date', ['name' => $reviewerApproval->approver?->name ?? $reviewerApproval->source->label(), 'date' => $reviewerApproval->approved_at?->translatedFormat('j M, H:i')]) }}</span>
            </p>
            @if($reviewerApproval->note)
                <p class="mt-1 text-body text-ink-2">{{ $reviewerApproval->note }}</p>
            @endif
        @else
            <p class="mt-1 text-body text-ink-3">{{ $inInternalReview ? __('Waiting for the reviewer.') : __('Not approved yet.') }}</p>
        @endif
        @if($inInternalReview && ! $reviewerApproval && ($can['approveByReviewer'] || $can['requestChangesByReviewer']))
            <div class="mt-2 flex items-center gap-2">
                @if($can['approveByReviewer'])
                    <button type="button" wire:click="approveByReviewer" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Approve') }}</button>
                @endif
                @if($can['requestChangesByReviewer'])
                    <button type="button" wire:click="openSheet('reviewerChanges')" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Send back to design') }}</button>
                @endif
            </div>
        @endif
    </section>

    <section aria-label="{{ __('Client approval') }}">
        <h3 class="ui-section-title">{{ __('Client approval') }}</h3>
        @if($clientApproval)
            <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-2 text-body" data-client-approval>
                <div>
                    <dt class="text-meta text-ink-3">{{ __('Source') }}</dt>
                    <dd class="text-ink">{{ $clientApproval->source->label() }}</dd>
                </div>
                <div>
                    <dt class="text-meta text-ink-3">{{ __('Recorded') }}</dt>
                    <dd class="text-ink">{{ __(':name · :date', ['name' => $clientApproval->approver?->name ?? '—', 'date' => $clientApproval->approved_at?->translatedFormat('j M, H:i')]) }}</dd>
                </div>
                <div>
                    <dt class="text-meta text-ink-3">{{ __('Measurements') }}</dt>
                    <dd><x-ui.tag :tone="$clientApproval->measurements_confirmed ? 'ok' : 'warn'">{{ $clientApproval->measurements_confirmed ? __('Confirmed') : __('Missing') }}</x-ui.tag></dd>
                </div>
                <div>
                    <dt class="text-meta text-ink-3">{{ __('Estimate') }}</dt>
                    <dd><x-ui.tag :tone="$clientApproval->estimate_approved ? 'ok' : 'warn'">{{ $clientApproval->estimate_approved ? __('Approved') : __('Missing') }}</x-ui.tag></dd>
                </div>
                @if($clientApproval->note)
                    <div class="col-span-2">
                        <dt class="text-meta text-ink-3">{{ __('Note') }}</dt>
                        <dd class="text-ink">{{ $clientApproval->note }}</dd>
                    </div>
                @endif
                @if($clientApproval->evidence_path)
                    <div class="col-span-2">
                        <dt class="text-meta text-ink-3">{{ __('Evidence') }}</dt>
                        <dd class="break-all text-ink">{{ $clientApproval->evidence_path }}</dd>
                    </div>
                @endif
            </dl>
        @else
            <p class="mt-1 text-body text-ink-3">{{ $inClientReview ? __('Waiting for the client.') : __('Not approved yet.') }}</p>
        @endif

        @if($inClientReview && $block === null && $hold === null && ($can['recordClientApproval'] || $can['recordClientChanges']))
            <form wire:submit="submitClientApproval" class="mt-3 space-y-3 rounded-card border border-line p-3" data-client-approval-form>
                <p class="text-body font-medium text-ink">{{ __('Record the client\'s answer') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="ui-label" for="dp-source">{{ __('Source') }}</label>
                        <select id="dp-source" wire:model="approvalSource" class="ui-select">
                            <option value="{{ \App\Domain\Delivery\Enums\ApprovalSource::CLIENT->value }}">{{ __('The client answered') }}</option>
                            <option value="{{ \App\Domain\Delivery\Enums\ApprovalSource::MANAGER->value }}">{{ __('A manager approved for the client') }}</option>
                        </select>
                        @error('approvalSource') <span class="mt-1 block text-meta text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-1.5 pt-6">
                        <label class="flex items-center gap-2 text-body text-ink-2"><input type="checkbox" wire:model="measurementsConfirmed" class="rounded border-line"><span>{{ __('Measurements confirmed') }}</span></label>
                        <label class="flex items-center gap-2 text-body text-ink-2"><input type="checkbox" wire:model="estimateApproved" class="rounded border-line"><span>{{ __('Estimate approved') }}</span></label>
                    </div>
                </div>
                <div>
                    <label class="ui-label" for="dp-approval-note">{{ __('Note') }}</label>
                    <textarea id="dp-approval-note" rows="2" wire:model="approvalNote" class="ui-input"></textarea>
                </div>
                <div>
                    <label class="ui-label" for="dp-evidence">{{ __('Evidence (link or file name)') }}</label>
                    <input id="dp-evidence" type="text" wire:model="approvalEvidence" class="ui-input">
                </div>
                <div class="flex items-center gap-2">
                    @if($can['recordClientApproval'])
                        <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm" wire:loading.attr="disabled" wire:target="submitClientApproval">{{ __('Record client approval') }}</button>
                    @endif
                    @if($can['recordClientChanges'])
                        <button type="button" wire:click="openSheet('clientChanges')" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('The client asked for changes') }}</button>
                    @endif
                </div>
            </form>
        @endif
    </section>
</div>
