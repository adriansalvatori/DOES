<div class="h-full flex flex-col gap-6 min-h-0 overflow-y-auto custom-vertical-scrollbar w-full max-w-4xl mx-auto">
    <x-ui.page-header :title="__('Ajustes')" :subtitle="__('Cómo está configurado el espacio de trabajo. Ves solo lo que tu rol puede cambiar.')" />

    @foreach($groups as $group)
        <section class="space-y-2" aria-labelledby="settings-group-{{ $group['key'] }}">
            <h2 id="settings-group-{{ $group['key'] }}" class="text-section font-semibold text-ink">{{ $group['label'] }}</h2>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($group['items'] as $item)
                    <a href="{{ $item['href'] }}" wire:navigate class="group flex items-start gap-3 rounded-card border border-line bg-surface p-3 transition hover:border-line-strong hover:shadow-menu focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-control bg-sunken text-ink-2 group-hover:text-ink">
                            {{ svg('lucide-' . $item['icon'], 'w-4 h-4', ['aria-hidden' => 'true']) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-row font-medium text-ink">{{ $item['label'] }}</span>
                            <span class="block text-body text-ink-3">{{ $item['description'] ?? '' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
