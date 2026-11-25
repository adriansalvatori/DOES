<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 w-full max-w-4xl">
    <x-ui.page-header :title="__('Idioma / Language')" :subtitle="__('El cambio se aplica al instante; no hay botón de guardar.')" />

    @if (session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line rounded-card px-3.5 py-2.5 text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ([
            ['code' => 'es', 'name' => 'Español', 'note' => 'Spanish (Default)', 'desc' => 'Interfaz nativa en español con términos y flujos adaptados al equipo de diseño.'],
            ['code' => 'en', 'name' => 'English', 'note' => 'Inglés', 'desc' => 'Full English interface translation for international team members and partners.'],
        ] as $lang)
            <button
                type="button"
                wire:click="setLocale('{{ $lang['code'] }}')"
                @click="localStorage.setItem('app_locale', '{{ $lang['code'] }}')"
                aria-pressed="{{ $currentLocale === $lang['code'] ? 'true' : 'false' }}"
                class="text-left bg-surface border rounded-card p-4 cursor-pointer transition-colors flex flex-col gap-2 {{ $currentLocale === $lang['code'] ? 'border-ink' : 'border-line hover:border-line-strong' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-row font-semibold text-ink">{{ $lang['name'] }}</h3>
                        <p class="text-meta text-ink-3">{{ $lang['note'] }}</p>
                    </div>
                    @if($currentLocale === $lang['code'])
                        <x-ui.tag tone="neutral" icon="check">{{ __('Activo') }}</x-ui.tag>
                    @endif
                </div>
                <p class="text-body text-ink-2">{{ $lang['desc'] }}</p>
            </button>
        @endforeach
    </div>
</div>
