{{--
    Designer of an Overview line (all its open deliverables) or of one deliverable. Click, type to filter, Enter or
    click picks and saves at once. The design team list comes from the page (designers).
    Variables: $people (list of names shown), $deliverableId (null = whole line).
--}}
@php
    $deliverableId ??= null;
@endphp
<div class="min-w-0"
     x-data="{
        open: false,
        query: '',
        anchor: null,
        wire: null,
        init() { this.anchor = this.$refs.button; this.wire = $wire; },
        get matches() {
            const term = this.query.trim().toLowerCase();
            return term === '' ? designers : designers.filter((person) => person.name.toLowerCase().includes(term));
        },
        toggle() {
            this.open = ! this.open;
            this.query = '';
            if (this.open) { this.$nextTick(() => this.$refs.search?.focus()); }
        },
        pick(id) {
            this.open = false;
            this.wire.assignDesigner(id, {{ $deliverableId !== null ? (int) $deliverableId : 'null' }});
            this.anchor.focus();
        },
     }">
    <button type="button" x-ref="button" x-on:click="toggle()" class="ui-cell-input flex items-center gap-1" title="{{ __('Change designer') }}">
        @forelse($people as $name)
            <x-ui.person :name="$name" size="sm" :show-name="count($people) === 1" />
        @empty
            <span class="text-ink-3">{{ __('Unassigned') }}</span>
        @endforelse
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-anchor.bottom-start.offset.4="anchor" x-on:click.outside="open = false" x-on:keydown.escape.prevent.stop="open = false; anchor.focus()"
             class="z-[60] w-56 rounded-card border border-line bg-surface p-1.5 shadow-menu">
            <input type="text" x-ref="search" x-model="query" class="ui-input h-7 text-meta" placeholder="{{ __('Search designer...') }}"
                   x-on:keydown.enter.prevent="if (matches.length) { pick(matches[0].id) }">
            <div class="mt-1 max-h-60 overflow-y-auto">
                <template x-for="person in matches" x-bind:key="person.id">
                    <button type="button" class="flex w-full items-center rounded-control px-2 py-1 text-left text-meta text-ink hover:bg-sunken focus:bg-sunken focus:outline-none" x-on:click="pick(person.id)" x-text="person.name"></button>
                </template>
                <button type="button" class="flex w-full items-center rounded-control px-2 py-1 text-left text-meta text-ink-3 hover:bg-sunken focus:bg-sunken focus:outline-none" x-on:click="pick(null)">{{ __('Nobody') }}</button>
            </div>
        </div>
    </template>
</div>
