<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pb-6">
    <x-ui.page-header :title="__('Today')" :subtitle="ucfirst($today->isoFormat('dddd, LL')).' · '.auth()->user()->name">
        @if($this->isManager)
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ __('Team') }}">
                    <button type="button" wire:click="viewDesigner(null)" class="ui-chip" aria-pressed="{{ $viewAs === null ? 'true' : 'false' }}">{{ __('Everyone') }}</button>
                    @foreach($this->designers as $designer)
                        <button type="button" wire:key="chip-{{ $designer->id }}" wire:click="viewDesigner({{ $designer->id }})" class="ui-chip" aria-pressed="{{ $viewAs === $designer->id ? 'true' : 'false' }}">{{ $designer->name }}</button>
                    @endforeach
                </div>
            </x-slot:toolbar>
        @endif
    </x-ui.page-header>

    @forelse($this->sections as $section)
        @switch($section)
            @case('designer')
                <livewire:today.designer-section :designer-id="$designerId" :key="'designer-'.$designerId" />
                @break
            @case('reviewer')
                <livewire:today.reviewer-section key="reviewer" />
                @break
            @case('manager')
                <livewire:today.manager-section key="manager" />
                @break
            @case('customer_service')
                <livewire:today.customer-service-section key="customer-service" />
                @break
        @endswitch
    @empty
        <div class="ui-card">
            <x-ui.empty icon="check-circle-2" :title="__('Nothing for you today')" :description="__('Your role has no daily list. Use the Board or Work orders to find work.')" />
        </div>
    @endforelse
</div>
