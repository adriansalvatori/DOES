@php
    $workOrders = $this->result->workOrders;
@endphp
<div class="h-full flex flex-col gap-4 min-h-0">
    <x-ui.page-header :title="__('Backlog')" :count="$this->result->totalInBacklog" :subtitle="__('Imported cards waiting to be pulled into Intake.')">
        <x-slot:actions>
            @if(count($selected) > 0)
                <button type="button" wire:click="clearSelection" class="ui-btn ui-btn-ghost">{{ __('Clear selection') }}</button>
                <button type="button" wire:click="moveSelectedToIntake" wire:loading.attr="disabled" class="ui-btn ui-btn-primary">{{ __('Move to Intake') }} ({{ count($selected) }})</button>
            @else
                <button type="button" wire:click="selectPage" class="ui-btn ui-btn-secondary">{{ __('Select this page') }}</button>
            @endif
        </x-slot:actions>
        <x-slot:toolbar>
            <div class="relative w-64">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search in backlog...') }}" class="ui-input pl-8">
            </div>
            <x-lists.combo-filter model="company" :value="$company === '' ? 'all' : $company" :all-label="__('All companies')"
                :options="$this->result->companies->mapWithKeys(fn ($name) => [$name => \App\Support\TitleCase::from($name)])->all()" :search-placeholder="__('Search company...')" />
            <x-lists.combo-filter model="contactId" :value="$contactId ?? 'all'" :all-label="__('All responsible people')"
                :options="$this->result->contacts->mapWithKeys(fn ($contact) => [$contact->id => \App\Support\TitleCase::from($contact->name)])->all()" :search-placeholder="__('Search account rep...')" />
            <select wire:model.live="status" class="ui-select w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(\App\Domain\Delivery\Enums\DeliverableStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
            <x-lists.combo-filter model="assigneeId" :value="$assigneeId ?? 'all'" :all-label="__('All Designers')" :options="$this->designerOptions" :search-placeholder="__('Search designer...')" />
            <select wire:model.live="sortBy" class="ui-select w-52" aria-label="{{ __('Sort by') }}">
                <option value="created_desc">{{ __('Created (newest)') }}</option>
                <option value="created_asc">{{ __('Created (oldest)') }}</option>
                <option value="due_date_asc">{{ __('Due date (soonest)') }}</option>
                <option value="company_asc">{{ __('Company (A-Z)') }}</option>
            </select>
            @if(filled($search) || filled($status) || $assigneeId || filled($company) || $contactId)
                <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Clear') }}</button>
            @endif
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="ui-card min-h-0 flex-1 overflow-auto custom-vertical-scrollbar">
        <table class="ui-table">
            <thead>
                <tr>
                    <th class="w-10"><span class="sr-only">{{ __('Select') }}</span></th>
                    <th>{{ __('WO #') }}</th>
                    <th>{{ __('Client') }}</th>
                    <th>{{ __('Order name') }}</th>
                    <th>{{ __('Responsible') }}</th>
                    <th>{{ __('Designer') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Due date') }}</th>
                    <th class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($workOrders as $workOrder)
                    @php
                        $first = $workOrder->deliverables->first();
                        $statuses = $workOrder->deliverables->map(fn ($deliverable) => ($deliverable->status ?? \App\Domain\Delivery\Enums\DeliverableStatus::INTAKE)->label())->unique();
                        $designers = $workOrder->deliverables->pluck('assignee')->filter()->unique('id');
                    @endphp
                    <tr wire:key="backlog-{{ $workOrder->id }}">
                        <td><input type="checkbox" class="rounded border-line" value="{{ $workOrder->id }}" wire:model="selected" aria-label="{{ __('Select') }}"></td>
                        <td><x-wo-badge :number="$workOrder->number" /></td>
                        <td class="min-w-48">
                            <p class="truncate font-medium text-ink">{{ \App\Support\TitleCase::from($workOrder->client?->name ?? $first?->company_name) }}</p>
                            <x-orders.location :location="\App\Support\TitleCase::from($workOrder->clientLocation?->name)" />
                        </td>
                        <td class="min-w-48 text-ink-2"><span class="line-clamp-2">{{ $workOrder->description ?: $first?->task_name }}</span></td>
                        <td class="text-ink-2">{{ \App\Support\TitleCase::from($workOrder->contact?->name) ?: '—' }}</td>
                        <td>
                            <div class="flex flex-col gap-0.5">
                                @forelse($designers as $designer)<x-ui.person :person="$designer" size="sm" show-name />@empty<x-ui.person size="sm" show-name />@endforelse
                            </div>
                        </td>
                        <td><div class="flex flex-wrap gap-1">@foreach($statuses as $label)<x-ui.tag>{{ $label }}</x-ui.tag>@endforeach</div></td>
                        <td><x-orders.due :date="$workOrder->promised_date" :done="false" /></td>
                        <td class="text-right">
                            <button type="button" wire:click="moveToIntake({{ $workOrder->id }})" wire:loading.attr="disabled" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Move to Intake') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-ui.empty icon="inbox" :title="__('The backlog is empty')" :description="__('No imported cards match these filters.')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="shrink-0">{{ $workOrders->links() }}</div>
</div>
