<tbody x-data="{ openLocal: null, get open() { return this.openLocal ?? expandAll }, toggle() { this.openLocal = ! this.open } }"
       x-on:overview-expand.window="openLocal = null">
@if($row)
    @php
        $workOrder = $row->workOrder;
        $client = \App\Support\TitleCase::from($workOrder->displayName() ?: $row->deliverables->first()?->company_name);
        $location = \App\Support\TitleCase::from($workOrder->clientLocation?->name);
        $installed = $workOrder->installation_types ?? [];
        $name = $workOrder->description ?: ($row->deliverables->first()?->task_name ?? '');
        $dueTint = match ($row->health) {
            \App\Application\Delivery\Data\PromiseHealth::OVERDUE => 'is-danger',
            \App\Application\Delivery\Data\PromiseHealth::DUE_SOON => 'is-warn',
            default => '',
        };
        $dueTitle = match ($row->health) {
            \App\Application\Delivery\Data\PromiseHealth::OVERDUE => __('Overdue'),
            \App\Application\Delivery\Data\PromiseHealth::DUE_SOON => __('Due soon'),
            default => null,
        };
        $workOrderUrl = route('work-orders.show', $workOrder);
        $hasOpenWork = $row->deliverables->contains(fn ($deliverable): bool => ! $deliverable->status?->isClosed());
        $byStatus = $row->deliverables->groupBy(fn ($deliverable): string => ($deliverable->status ?? \App\Domain\Delivery\Enums\DeliverableStatus::INTAKE)->value);
    @endphp
    <tr class="is-line">
        <td class="is-frozen" style="{{ \App\Livewire\Overview\OverviewPage::frozen('toggle') }}">
            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-ink-3 hover:bg-line hover:text-ink" x-on:click="toggle()" x-bind:aria-expanded="open" aria-label="{{ __('Show deliverables') }}" tabindex="-1">
                <x-lucide-chevron-right class="h-3.5 w-3.5 transition-transform" x-bind:class="open && 'rotate-90'" />
            </button>
        </td>
        <td class="is-frozen" style="{{ \App\Livewire\Overview\OverviewPage::frozen('processed') }}">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'production_processed_at', 'value' => $row->processedAt, 'kind' => 'date', 'method' => 'saveLineField', 'varies' => $row->varies('production_processed_at')])
        </td>
        <td class="is-frozen {{ $dueTint }}" style="{{ \App\Livewire\Overview\OverviewPage::frozen('due') }}" @if($dueTitle) title="{{ $dueTitle }}" @endif>
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'promised_date', 'value' => $workOrder->promised_date?->toDateString(), 'kind' => 'date', 'method' => 'saveWorkOrderField'])
        </td>
        <td class="is-frozen" style="{{ \App\Livewire\Overview\OverviewPage::frozen('number') }}">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'number', 'value' => $workOrder->number, 'method' => 'saveWorkOrderField', 'confirmLabel' => __('WO #'), 'isWorkOrder' => true])
        </td>
        <td class="is-frozen" style="{{ \App\Livewire\Overview\OverviewPage::frozen('client') }}">
            <a href="{{ $workOrderUrl }}" wire:navigate class="block min-w-0 rounded px-1 leading-tight hover:bg-line/60" title="{{ __('Open work order') }}: {{ $client }}">
                <span class="block truncate font-semibold text-ink">{{ $client ?: '—' }}</span>
                <x-orders.location :location="$location" class="max-w-full truncate text-[11px]!" />
            </a>
        </td>
        <td class="is-frozen is-frozen-last" style="{{ \App\Livewire\Overview\OverviewPage::frozen('name') }}">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'description', 'value' => $workOrder->description, 'method' => 'saveWorkOrderField', 'placeholder' => $name, 'placeholderClass' => 'placeholder:text-ink'])
        </td>
        <td class="w-36 min-w-36 max-w-36">
            @if($hasOpenWork)
                @include('livewire.overview-page.partials.designer-picker', ['people' => $row->designers])
            @else
                <div class="flex items-center gap-1 px-1">
                    @forelse($row->designers as $designer)
                        <x-ui.person :name="$designer" size="sm" :show-name="count($row->designers) === 1" />
                    @empty
                        <span class="text-ink-3">—</span>
                    @endforelse
                </div>
            @endif
        </td>
        <td class="w-52 min-w-52 max-w-52">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'production_note', 'value' => $row->productionNote, 'kind' => 'note', 'method' => 'saveLineField', 'varies' => $row->varies('production_note')])
        </td>
        <td class="w-36 min-w-36 max-w-36">
            <div class="flex items-center gap-0.5">
                <span class="shrink-0 text-[10px] font-semibold text-ink-3" title="{{ $row->reviewStatus ? __('Invoice') : __('Estimate') }}">{{ $row->reviewStatus ? 'INV' : 'EST' }}</span>
                <div class="min-w-0 flex-1">
                    @include('livewire.overview-page.partials.editable-cell', ['field' => 'estimate_invoice_number', 'value' => $workOrder->estimate_invoice_number, 'method' => 'saveWorkOrderField', 'confirmLabel' => __('Estimate / Invoice')])
                </div>
                @include('livewire.overview-page.partials.review-picker', ['reviewStatus' => $row->reviewStatus])
            </div>
        </td>
        <td class="w-28 min-w-28 max-w-28">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'email_date', 'value' => $row->emailDate, 'kind' => 'date', 'method' => 'saveLineField', 'varies' => $row->varies('email_date')])
        </td>
        <td class="w-36 min-w-36 max-w-36">
            @include('livewire.overview-page.partials.installation-picker', ['installed' => $installed])
        </td>
        <td class="w-10 min-w-10 text-center" @if($row->varies('overview_checked')) title="{{ __('Only some deliverables are checked.') }}" @endif>
            <input type="checkbox" class="rounded border-line" aria-label="{{ __('Checked') }}" @checked($row->checked)
                   wire:key="checked-{{ (int) $row->checked }}"
                   @if($row->varies('overview_checked')) x-init="$el.indeterminate = true" @endif
                   x-on:change="$wire.saveLineField('overview_checked', $event.target.checked ? '1' : '')">
        </td>
        <td class="w-52 min-w-52 max-w-52">
            @include('livewire.overview-page.partials.editable-cell', ['field' => 'delivery_note', 'value' => $row->deliveryNote, 'kind' => 'note', 'method' => 'saveLineField', 'varies' => $row->varies('delivery_note')])
        </td>
        <td class="min-w-48">
            <div class="flex flex-nowrap items-center gap-1">
                @foreach($row->flags as $flag)
                    <x-ui.tag :tone="$flag === \App\Domain\Delivery\Enums\DeliverableFlag::URGENT ? 'danger' : 'neutral'">{{ $flag->label() }}</x-ui.tag>
                @endforeach
                @foreach($byStatus as $status => $deliverables)
                    @php $single = $deliverables->count() === 1 ? $deliverables->first() : null; @endphp
                    <button type="button" class="shrink-0 rounded hover:ring-1 hover:ring-line-strong"
                            title="{{ $single ? __('Open deliverable') : __('Show deliverables') }}"
                            @if($single) x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $single->id }} })" @else x-on:click="openLocal = true" @endif>
                        <x-ui.tag>{{ \App\Domain\Delivery\Enums\DeliverableStatus::from($status)->label() }}@if(! $single) {{ $deliverables->count() }}@endif</x-ui.tag>
                    </button>
                @endforeach
            </div>
        </td>
    </tr>
    <tr class="is-detail" x-show="open" x-cloak style="display: none;">
        <td colspan="14" class="px-0! py-0!">
            <div class="px-2 py-2 lg:sticky lg:left-7 lg:max-w-[min(1100px,calc(100vw-20rem))]">
                @if($row->deliverables->isEmpty())
                    <p class="px-1 text-meta text-ink-3">{{ __('No deliverables yet.') }}</p>
                @else
                    <table class="w-full text-meta">
                        <thead>
                            <tr class="text-left text-ink-3">
                                <th class="px-1 py-1 font-medium">{{ __('Deliverable') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Status') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Designer') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Prod./inst. note') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Delivery') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Size') }}</th>
                                <th class="px-1 py-1 font-medium">{{ __('Material') }}</th>
                                <th class="px-1 py-1 text-right font-medium">{{ __('Quantity') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($row->deliverables as $deliverable)
                                <tr wire:key="deliverable-{{ $deliverable->id }}">
                                    <td class="max-w-56 px-1 py-0.5">
                                        <button type="button" class="block max-w-full truncate text-left font-medium text-ink hover:underline" x-on:click="$dispatch('open-deliverable', { deliverableId: {{ $deliverable->id }} })">{{ $deliverable->task_name ?: '—' }}</button>
                                    </td>
                                    <td class="px-1 py-0.5">
                                        <span class="inline-flex flex-nowrap gap-1">
                                            <x-ui.tag>{{ ($deliverable->status ?? \App\Domain\Delivery\Enums\DeliverableStatus::INTAKE)->label() }}</x-ui.tag>
                                            @if($deliverable->openBlock)<x-ui.tag tone="warn">{{ __('Blocked') }}</x-ui.tag>@endif
                                            @if($deliverable->openHold)<x-ui.tag tone="info">{{ __('On hold') }}</x-ui.tag>@endif
                                        </span>
                                    </td>
                                    <td class="w-36 px-1 py-0.5">
                                        @if($deliverable->status?->isClosed())
                                            <x-ui.person :person="$deliverable->assignee" size="sm" show-name />
                                        @else
                                            @include('livewire.overview-page.partials.designer-picker', ['people' => array_filter([$deliverable->assignee?->name]), 'deliverableId' => $deliverable->id])
                                        @endif
                                    </td>
                                    <td class="w-52 px-1 py-0.5">
                                        @include('livewire.overview-page.partials.editable-cell', ['field' => 'production_note', 'value' => $deliverable->production_note, 'kind' => 'note', 'method' => 'saveLineField', 'deliverableId' => $deliverable->id])
                                    </td>
                                    <td class="w-52 px-1 py-0.5">
                                        @include('livewire.overview-page.partials.editable-cell', ['field' => 'delivery_note', 'value' => $deliverable->delivery_note, 'kind' => 'note', 'method' => 'saveLineField', 'deliverableId' => $deliverable->id])
                                    </td>
                                    <td class="px-1 py-0.5 text-ink-2">{{ $deliverable->size ?: '—' }}</td>
                                    <td class="px-1 py-0.5 text-ink-2">{{ $deliverable->material ?: '—' }}</td>
                                    <td class="px-1 py-0.5 text-right tabular-nums text-ink-2">{{ $deliverable->quantity ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </td>
    </tr>
@endif
</tbody>
