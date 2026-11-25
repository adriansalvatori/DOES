<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 w-full max-w-5xl">

    <x-ui.page-header :title="__('Respaldos de Base de Datos')">
        <x-slot:actions>
            <button type="button" wire:click="createBackup" wire:loading.attr="disabled" class="ui-btn ui-btn-primary disabled:opacity-50">
                <span>{{ __('Crear Respaldo Ahora') }}</span>
                <kbd class="hidden sm:inline-block text-meta font-sans text-white/70">⌘S</kbd>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($successMessage || session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line rounded-card px-3.5 py-2.5 text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ $successMessage ?? session('message') }}</span>
        </div>
    @endif

    @if ($errorMessage || session()->has('error'))
        <div class="bg-danger-soft text-danger border border-line rounded-card px-3.5 py-2.5 text-body flex items-center gap-2 shrink-0">
            <x-lucide-circle-alert class="w-4 h-4 shrink-0" />
            <span>{{ $errorMessage ?? session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-surface border border-line rounded-card p-4 flex flex-col gap-1">
            <span class="text-meta text-ink-3">{{ __('Total Respaldos') }}</span>
            <div class="text-title font-semibold text-ink">{{ $totalBackups }}</div>
            <p class="text-meta text-ink-3">
                {{ __('Espacio ocupado:') }} <span class="text-ink-2">{{ $totalStorageSize >= 1048576 ? number_format($totalStorageSize / 1048576, 2) . ' MB' : number_format($totalStorageSize / 1024, 2) . ' KB' }}</span>
            </p>
        </div>

        <div x-data="backupCountdown('{{ $nextBackupTime }}')" class="bg-surface border border-line rounded-card p-4 flex flex-col gap-1">
            <span class="text-meta text-ink-3">{{ __('Siguiente Respaldo') }}</span>
            <div class="text-title font-mono font-semibold text-ink" x-text="timeRemaining">00h 00m</div>
            <p class="text-meta text-ink-3">{{ __('Programado diariamente a las 00:00') }}</p>
        </div>

        <div class="bg-surface border border-line rounded-card p-4 flex flex-col gap-1">
            <span class="text-meta text-ink-3">{{ __('Último Respaldo') }}</span>
            @if($latestBackup)
                <div class="text-section font-semibold text-ink truncate" title="{{ $latestBackup['filename'] }}">{{ $latestBackup['created_at_human'] }}</div>
                <p class="text-meta text-ink-3">{{ $latestBackup['created_at'] }}</p>
            @else
                <div class="text-section font-semibold text-ink-3">{{ __('Sin respaldos aún') }}</div>
                <p class="text-meta text-ink-3">{{ __('Crea uno manualmente arriba') }}</p>
            @endif
        </div>
    </div>

    <div class="bg-surface border border-line rounded-card overflow-hidden flex flex-col">
        <div class="px-4 py-3 border-b border-line flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h2 class="text-row font-semibold text-ink">{{ __('Archivos de Respaldo') }}</h2>
                <span class="text-meta text-ink-3">
                    @if($search !== '')
                        ({{ $filteredCount }} {{ __('de') }} {{ $totalBackups }})
                    @else
                        ({{ $totalBackups }} {{ __('disponible(s)') }})
                    @endif
                </span>
            </div>

            <div class="relative w-full sm:w-64">
                <x-lucide-search class="w-3.5 h-3.5 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar respaldo o fecha...') }}"
                    class="ui-input pl-8 pr-8"
                />
                @if($search !== '')
                    <button wire:click="$set('search', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer" title="{{ __('Limpiar búsqueda') }}">
                        <x-lucide-x class="w-3.5 h-3.5" />
                    </button>
                @endif
            </div>
        </div>

        @if($totalBackups > 0)
            @if(count($backups) > 0)
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>{{ __('Archivo') }}</th>
                                <th>{{ __('Motor') }}</th>
                                <th>{{ __('Tamaño') }}</th>
                                <th>{{ __('Fecha de Creación') }}</th>
                                <th class="text-right">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backups as $backup)
                                <tr wire:key="backup-{{ $backup['filename'] }}" >
                                    <td>
                                        <div class="font-medium text-ink truncate max-w-xs sm:max-w-md" title="{{ $backup['filename'] }}">{{ $backup['filename'] }}</div>
                                    </td>
                                    <td><x-ui.tag tone="neutral">{{ $backup['driver'] }}</x-ui.tag></td>
                                    <td class="font-mono text-meta text-ink-2">{{ $backup['size'] }}</td>
                                    <td>
                                        <div class="text-ink">{{ $backup['created_at'] }}</div>
                                        <div class="text-meta text-ink-3">{{ $backup['created_at_human'] }}</div>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                wire:click="downloadBackup('{{ $backup['filename'] }}')"
                                                title="{{ __('Descargar respaldo') }}"
                                                class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon" aria-label="{{ __('Descargar respaldo') }}"
                                            >
                                                <x-lucide-download class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="deleteBackup('{{ $backup['filename'] }}')"
                                                wire:confirm="{{ __('¿Estás seguro de que deseas eliminar este respaldo?') }}"
                                                title="{{ __('Eliminar respaldo') }}"
                                                class="ui-btn ui-btn-ghost ui-btn-sm ui-btn-icon hover:text-danger" aria-label="{{ __('Eliminar respaldo') }}"
                                            >
                                                <x-lucide-trash-2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="shrink-0 border-t border-line px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 text-meta text-ink-2">
                    <div class="flex items-center gap-3">
                        <span>
                            {{ __(":from–:to de :total respaldos", ["from" => $backups->firstItem() ?? 0, "to" => $backups->lastItem() ?? 0, "total" => $backups->total()]) }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-ink-3">{{ __('Ver:') }}</span>
                            <select wire:model.live="perPage" class="ui-select w-32">
                                <option value="5">{{ __(":count / pág", ["count" => 5]) }}</option>
                                <option value="10">{{ __(":count / pág", ["count" => 10]) }}</option>
                                <option value="25">{{ __(":count / pág", ["count" => 25]) }}</option>
                                <option value="50">{{ __(":count / pág", ["count" => 50]) }}</option>
                            </select>
                        </div>
                    </div>

                    @if($backups->hasPages())
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($backups->onFirstPage())
                                <span class="ui-btn ui-btn-secondary ui-btn-sm opacity-50 cursor-not-allowed">
                                    <x-lucide-chevron-left class="w-3.5 h-3.5" />
                                    <span>{{ __('Anterior') }}</span>
                                </span>
                            @else
                                <button wire:click="previousPage" class="ui-btn ui-btn-secondary ui-btn-sm">
                                    <x-lucide-chevron-left class="w-3.5 h-3.5" />
                                    <span>{{ __('Anterior') }}</span>
                                </button>
                            @endif

                            <span class="px-3 py-1 text-meta font-mono text-ink">{{ $backups->currentPage() }} / {{ $backups->lastPage() }}</span>

                            @if($backups->hasMorePages())
                                <button wire:click="nextPage" class="ui-btn ui-btn-secondary ui-btn-sm">
                                    <span>{{ __('Siguiente') }}</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5" />
                                </button>
                            @else
                                <span class="ui-btn ui-btn-secondary ui-btn-sm opacity-50 cursor-not-allowed">
                                    <span>{{ __('Siguiente') }}</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5" />
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <x-ui.empty icon="search-x" :title="__('No se encontraron respaldos que coincidan con la búsqueda.')">
                    <button type="button" wire:click="$set('search', '')" class="ui-btn ui-btn-secondary ui-btn-sm">{{ __('Limpiar búsqueda') }}</button>
                </x-ui.empty>
            @endif
        @else
            <x-ui.empty icon="hard-drive" :title="__('No hay respaldos disponibles')" :description="__('Aún no se ha generado ninguna copia de seguridad local. Puedes crear la primera haciendo clic a continuación.')">
                <button type="button" wire:click="createBackup" wire:loading.attr="disabled" class="ui-btn ui-btn-primary">{{ __('Crear Primer Respaldo') }}</button>
            </x-ui.empty>
        @endif
    </div>

    <!-- ⌘S / Ctrl+S creates a backup, only while this page is open -->
    <livewire:settings.database-backup-shortcut />
</div>

<!-- Alpine.js Countdown Script -->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('backupCountdown', (targetIsoDate) => ({
            targetTime: new Date(targetIsoDate).getTime(),
            timeRemaining: '00h 00m',
            timer: null,

            init() {
                this.updateTicker();
                this.timer = setInterval(() => {
                    this.updateTicker();
                }, 1000);
            },

            updateTicker() {
                const now = new Date().getTime();
                const diff = this.targetTime - now;

                if (diff <= 0) {
                    this.timeRemaining = '00h 00m';
                    return;
                }

                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

                const pad = (n) => String(n).padStart(2, '0');
                this.timeRemaining = `${pad(hours)}h ${pad(minutes)}m`;
            },

            destroy() {
                if (this.timer) {
                    clearInterval(this.timer);
                }
            }
        }));
    });
</script>
