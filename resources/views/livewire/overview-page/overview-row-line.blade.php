@php
    $installed = $row?->workOrder->installation_types ?? [];
@endphp
<tbody x-data="{ open: false, types: @js($installed), pickingTypes: false }" class="border-b border-line align-top">
@if($row)
    @php
        $workOrder = $row->workOrder;
        $client = \App\Support\TitleCase::from($workOrder->client?->name ?? $row->deliverables->first()?->company_name);
        $location = \App\Support\TitleCase::from($workOrder->clientLocation?->name);
        $installed = $workOrder->installation_types ?? [];
        $name = $workOrder->description ?: ($row->deliverables->first()?->task_name ?? '');
        $healthTone = match ($row->health) {
            \App\Application\Delivery\Data\PromiseHealth::OVERDUE => 'danger',
            \App\Application\Delivery\Data\PromiseHealth::DUE_SOON => 'warn',
            default => 'neutral',
        };
    @endphp
        <tr class="hover:bg-sunken/40">
            <td class="px-2 py-1.5">
                <button type="button" class="ui-btn ui-btn-ghost ui-btn-icon" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="{{ __('Show deliverables') }}">
                    <x-lucide-chevron-right class="h-4 w-4 transition-transform" x-bind:class="open && 'rotate-90'" />
                </button>
            </td>
            <td class="px-2 py-1.5 w-32">@include('livewire.overview-page.partials.editable-cell', ['field' => 'production_processed_at', 'value' => $row->processedAt, 'text' => $row->processedAt, 'kind' => 'date', 'method' => 'saveLineField'])</td>
            <td class="px-2 py-1.5 w-32">@include('livewire.overview-page.partials.editable-cell', ['field' => 'promised_date', 'value' => $workOrder->promised_date?->toDateString(), 'text' => $workOrder->promised_date?->toDateString(), 'kind' => 'date', 'method' => 'saveWorkOrderField'])
                @if($healthTone !== 'neutral')
                    <x-ui.tag :tone="$healthTone" class="mt-0.5">{{ $row->health === \App\Application\Delivery\Data\PromiseHealth::OVERDUE ? __('Overdue') : __('Due soon') }}</x-ui.tag>
                @endif
            </td>
            <td class="px-2 py-1.5 w-28">@include('livewire.overview-page.partials.editable-cell', ['field' => 'number', 'value' => $workOrder->number, 'text' => $workOrder->number, 'method' => 'saveWorkOrderField', 'confirmLabel' => __('WO #'), 'isWorkOrder' => true])</td>
            <td class="px-2 py-1.5 min-w-44">
                <p class="truncate text-body font-medium text-ink" title="{{ $client }}">{{ $client ?: '—' }}</p>
                <x-orders.location :location="$location" />
            </td>
            <td class="px-2 py-1.5 min-w-44">@include('livewire.overview-page.partials.editable-cell', ['field' => 'description', 'value' => $workOrder->description, 'text' => $name, 'method' => 'saveWorkOrderField'])</td>
            <td class="px-2 py-1.5 min-w-32">
                <div class="flex flex-col gap-0.5">
                    @forelse($row->designers as $designer)
                        <x-ui.person :name="$designer" size="sm" show-name />
                    @empty
                        <x-ui.person size="sm" show-name />
                    @endforelse
                </div>
            </td>
            <td class="px-2 py-1.5 min-w-40">@include('livewire.overview-page.partials.editable-cell', ['field' => 'production_note', 'value' => $row->productionNote, 'text' => $row->productionNote, 'kind' => 'note', 'method' => 'saveLineField'])</td>
            <td class="px-2 py-1.5 w-28">@include('livewire.overview-page.partials.editable-cell', ['field' => 'estimate_invoice_number', 'value' => $workOrder->estimate_invoice_number, 'text' => $workOrder->estimate_invoice_number, 'method' => 'saveWorkOrderField', 'confirmLabel' => __('Estimate / Invoice')])</td>
            <td class="px-2 py-1.5 w-32">@include('livewire.overview-page.partials.editable-cell', ['field' => 'email_date', 'value' => $row->emailDate, 'text' => $row->emailDate, 'kind' => 'date', 'method' => 'saveLineField'])</td>
            <td class="px-2 py-1.5 w-40 relative">
                <button type="button" class="block w-full rounded-control px-1 py-0.5 text-left text-body hover:bg-sunken" x-on:click="pickingTypes = ! pickingTypes" x-on:click.outside="pickingTypes = false">
                    @if($installed !== [])
                        <span class="flex flex-wrap gap-1">@foreach($installed as $type)<x-ui.tag>{{ $type }}</x-ui.tag>@endforeach</span>
                    @else
                        <span class="text-ink-3">{{ __('No info') }}</span>
                    @endif
                </button>
                <div x-show="pickingTypes" x-cloak style="display: none;" class="absolute left-0 top-full z-30 mt-1 w-56 rounded-card border border-line bg-surface p-2 shadow-menu">
                    <template x-for="type in installationTypes" x-bind:key="type">
                        <label class="flex items-center gap-2 px-1 py-1 text-body text-ink">
                            <input type="checkbox" x-bind:value="type" x-model="types" class="rounded border-line">
                            <span x-text="type"></span>
                        </label>
                    </template>
                    <div class="mt-1 flex justify-end">
                        <button type="button" class="ui-btn ui-btn-primary ui-btn-sm" x-on:click="pickingTypes = false; $wire.saveWorkOrderField('installation_types', types)">{{ __('Save') }}</button>
                    </div>
                </div>
            </td>
            <td class="px-2 py-1.5 min-w-40">@include('livewire.overview-page.partials.editable-cell', ['field' => 'delivery_note', 'value' => $row->deliveryNote, 'text' => $row->deliveryNote, 'kind' => 'note', 'method' => 'saveLineField'])</td>
            <td class="px-2 py-1.5 min-w-40">
                <div class="flex flex-wrap gap-1">
                    @foreach($row->flags as $flag)
                        <x-ui.tag :tone="$flag === \App\Domain\Delivery\Enums\DeliverableFlag::URGENT ? 'danger' : 'neutral'">{{ $flag->label() }}</x-ui.tag>
                    @endforeach
                    @foreach($row->statusCounts as $status => $count)
                        <x-ui.tag>{{ \App\Domain\Delivery\Enums\DeliverableStatus::from($status)->label() }} {{ $count }}</x-ui.tag>
                    @endforeach
                </div>
            </td>
            <td class="px-2 py-1.5 w-10 text-center">
                <input type="checkbox" class="rounded border-line" aria-label="{{ __('Checked') }}" @checked($row->checked)
                    x-on:change="$wire.saveLineField('overview_checked', $event.target.checked ? '1' : '')">
            </td>
        </tr>
        <tr x-show="open" x-cloak style="display: none;" class="bg-canvas">
            <td></td>
            <td colspan="13" class="px-2 pb-3 pt-1">
                @if($row->deliverables->isEmpty())
                    <p class="text-meta text-ink-3">{{ __('No deliverables yet.') }}</p>
                @else
                    <table class="w-full text-body">
                        <thead>
                            <tr class="text-left text-meta text-ink-3">
                                <th class="py-1 pr-3 font-medium">{{ __('Deliverable') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ __('Status') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ __('Designer') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ __('Size') }}</th>
                                <th class="py-1 pr-3 font-medium">{{ __('Material') }}</th>
                                <th class="py-1 font-medium text-right">{{ __('Quantity') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($row->deliverables as $deliverable)
                                <tr wire:key="deliverable-{{ $deliverable->id }}">
                                    <td class="py-1 pr-3">
                                        <button type="button" class="text-left font-medium text-ink hover:underline" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })">{{ $deliverable->task_name }}</button>
                                    </td>
                                    <td class="py-1 pr-3">
                                        <span class="inline-flex flex-wrap gap-1">
                                            <x-ui.tag>{{ ($deliverable->status ?? \App\Domain\Delivery\Enums\DeliverableStatus::INTAKE)->label() }}</x-ui.tag>
                                            @if($deliverable->openBlock)<x-ui.tag tone="warn">{{ __('Blocked') }}</x-ui.tag>@endif
                                            @if($deliverable->openHold)<x-ui.tag tone="info">{{ __('On hold') }}</x-ui.tag>@endif
                                        </span>
                                    </td>
                                    <td class="py-1 pr-3"><x-ui.person :person="$deliverable->assignee" size="sm" show-name /></td>
                                    <td class="py-1 pr-3 text-ink-2">{{ $deliverable->size ?: '—' }}</td>
                                    <td class="py-1 pr-3 text-ink-2">{{ $deliverable->material ?: '—' }}</td>
                                    <td class="py-1 text-right tabular-nums text-ink-2">{{ $deliverable->quantity ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
@endif
</tbody>
