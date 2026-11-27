<div wire:poll.visible.30s="checkForChanges" class="h-full flex flex-col gap-3 min-h-0 overflow-hidden">
    <x-ui.page-header :title="__('Board')">
        <x-slot:toolbar>
            <div class="inline-flex items-center gap-0.5 p-0.5 rounded-control bg-sunken shrink-0" role="group" aria-label="{{ __('Area') }}">
                @foreach(\App\Domain\Delivery\Enums\Area::cases() as $areaOption)
                    <button type="button" wire:click="$set('area', '{{ $areaOption->value }}')" aria-pressed="{{ $area === $areaOption->value ? 'true' : 'false' }}"
                            class="px-3 h-7 rounded-control text-body font-medium transition shrink-0 {{ $area === $areaOption->value ? 'bg-surface text-ink border border-line' : 'text-ink-3 hover:text-ink border border-transparent' }}">
                        {{ $areaOption->label() }}
                    </button>
                @endforeach
            </div>

            <div class="relative flex-1 min-w-[220px] max-w-xs">
                <x-lucide-search class="w-3.5 h-3.5 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search company, task or WO...') }}" aria-label="{{ __('Search') }}" class="ui-input pl-8">
            </div>

            <select wire:model.live="designer" aria-label="{{ __('Designer') }}" class="ui-select w-auto max-w-[180px]">
                <option value="">{{ __('Designer: all') }}</option>
                @foreach($this->designers as $designerOption)
                    <option value="{{ $designerOption->id }}">{{ $designerOption->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="company" aria-label="{{ __('Company') }}" class="ui-select w-auto max-w-[200px]">
                <option value="">{{ __('Company: all') }}</option>
                @foreach($this->companies as $companyOption)
                    <option value="{{ $companyOption->id }}">{{ \App\Support\TitleCase::from($companyOption->name) }}</option>
                @endforeach
            </select>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" class="ui-btn ui-btn-secondary" @click="open = !open" :aria-expanded="open.toString()">
                    <x-lucide-flag aria-hidden="true" />
                    <span>{{ __('Flags') }}</span>
                    @if(count($flags) > 0)
                        <span class="rounded-full bg-sunken px-1.5 text-meta tabular-nums">{{ count($flags) }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak style="display: none;" class="absolute left-0 top-full mt-1 z-40 w-52 bg-surface border border-line rounded-card shadow-menu p-1.5 text-body">
                    @foreach($this->flagOptions as $flagOption)
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-control hover:bg-sunken cursor-pointer">
                            <input type="checkbox" value="{{ $flagOption['value'] }}" wire:model.live="flags" class="rounded-control border-line-strong text-ink w-4 h-4">
                            <span>{{ $flagOption['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @php($activeGrouping = $this->effectiveGroupBy())
            <div class="inline-flex items-center gap-0.5 p-0.5 rounded-control bg-sunken shrink-0" role="group" aria-label="{{ __('Group by') }}">
                @foreach($this->groupingOptions() as $groupValue => $groupLabel)
                    <button type="button" wire:click="$set('groupBy', '{{ $groupValue }}')" aria-pressed="{{ $activeGrouping === $groupValue ? 'true' : 'false' }}"
                            class="px-2.5 h-7 rounded-control text-meta font-medium transition shrink-0 {{ $activeGrouping === $groupValue ? 'bg-surface text-ink border border-line' : 'text-ink-3 hover:text-ink border border-transparent' }}">
                        {{ $groupLabel }}
                    </button>
                @endforeach
            </div>
        </x-slot:toolbar>
    </x-ui.page-header>

    <div class="flex gap-3 overflow-x-auto pb-3 pt-1 custom-horizontal-scrollbar flex-1 min-h-0 w-full"
         x-data="{
            frame: null,
            speed: 0,
            over(e) {
                const rect = $el.getBoundingClientRect();
                const left = e.clientX - rect.left;
                const right = rect.right - e.clientX;
                this.speed = left < 100 ? -Math.max(6, Math.round((100 - left) / 100 * 24)) : (right < 100 ? Math.max(6, Math.round((100 - right) / 100 * 24)) : 0);
                if (this.speed !== 0 && !this.frame) {
                    const step = () => { if (this.speed !== 0) { $el.scrollLeft += this.speed; this.frame = requestAnimationFrame(step); } else { this.frame = null; } };
                    this.frame = requestAnimationFrame(step);
                }
            },
            stop() { this.speed = 0; if (this.frame) { cancelAnimationFrame(this.frame); this.frame = null; } },
         }"
         @dragover="over($event)" @dragend="stop()" @drop="stop()" @dragleave.self="stop()">
        @foreach($this->columnStatuses as $columnStatus)
            <livewire:board.column
                :key="'board-column-'.$area.'-'.$columnStatus->value"
                :status="$columnStatus->value"
                :assignee-id="$this->assigneeId"
                :client-id="$this->clientId"
                :flags="$flags"
                :search="$search"
                :group-by="$this->effectiveGroupBy()" />
        @endforeach
    </div>

    <x-board.card-menu :abilities="$this->menuAbilities" />
</div>
