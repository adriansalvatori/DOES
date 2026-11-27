{{--
    Installation types of a work order. Type to filter; every tick saves at once (no Save button).
    Variables: $installed (list of type names). The active types come from the page (installationTypes).
--}}
<div class="min-w-0" wire:key="installation-{{ md5(json_encode($installed)) }}"
     x-data="{
        open: false,
        query: '',
        types: @js($installed),
        anchor: null,
        wire: null,
        init() { this.anchor = this.$refs.button; this.wire = $wire; },
        get matches() {
            const term = this.query.trim().toLowerCase();
            return term === '' ? installationTypes : installationTypes.filter((type) => type.toLowerCase().includes(term));
        },
        toggleType(type) {
            this.types = this.types.includes(type) ? this.types.filter((each) => each !== type) : [...this.types, type];
            this.wire.saveWorkOrderField('installation_types', this.types);
        },
     }">
    <button type="button" x-ref="button" class="ui-cell-input flex items-center gap-1 overflow-hidden" title="{{ __('Installation') }}"
            x-on:click="open = ! open; query = ''; if (open) { $nextTick(() => $refs.search?.focus()) }">
        <template x-for="type in types" x-bind:key="type">
            <span class="shrink-0 rounded bg-sunken px-1 text-meta text-ink-2" x-text="type"></span>
        </template>
        <span x-show="types.length === 0" class="text-ink-3">{{ __('No info') }}</span>
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-anchor.bottom-start.offset.4="anchor" x-on:click.outside="open = false" x-on:keydown.escape.prevent.stop="open = false; anchor.focus()"
             class="z-[60] w-56 rounded-card border border-line bg-surface p-1.5 shadow-menu">
            <input type="text" x-ref="search" x-model="query" class="ui-input h-7 text-meta" placeholder="{{ __('Search...') }}"
                   x-on:keydown.enter.prevent="if (matches.length) { toggleType(matches[0]); query = '' }">
            <div class="mt-1 max-h-60 overflow-y-auto">
                <template x-for="type in matches" x-bind:key="type">
                    <label class="flex cursor-pointer items-center gap-2 rounded-control px-2 py-1 text-meta text-ink hover:bg-sunken">
                        <input type="checkbox" class="rounded border-line" x-bind:checked="types.includes(type)" x-on:change="toggleType(type)">
                        <span x-text="type"></span>
                    </label>
                </template>
            </div>
        </div>
    </template>
</div>
