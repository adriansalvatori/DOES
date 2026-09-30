<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 max-w-5xl mx-auto w-full">
    
    <!-- Notion Header & Actions -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <x-lucide-database class="w-5 h-5 text-stone-100" />
            </div>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">{{ __('Respaldos de Base de Datos') }}</h1>
                <p class="text-xs text-zinc-500 mt-0.5">{{ __('Administra respaldos locales y monitorea la ejecución programada.') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button 
                wire:click="createBackup"
                wire:loading.attr="disabled"
                class="px-3.5 py-2 bg-stone-900 hover:bg-stone-800 disabled:opacity-50 text-white text-xs font-semibold rounded-xl transition cursor-pointer flex items-center gap-2 shadow-2xs"
            >
                <x-lucide-refresh-cw wire:loading wire:target="createBackup" class="w-3.5 h-3.5 animate-spin" />
                <x-lucide-play wire:loading.remove wire:target="createBackup" class="w-3.5 h-3.5" />
                <span>{{ __('Crear Respaldo Ahora') }}</span>
            </button>
        </div>
    </div>

    <!-- Notifications -->
    @if ($successMessage || session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3.5 rounded-xl text-xs font-medium flex items-center gap-2 shrink-0 shadow-2xs">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{{ $successMessage ?? session('message') }}</span>
        </div>
    @endif

    @if ($errorMessage || session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 p-3.5 rounded-xl text-xs font-medium flex items-center gap-2 shrink-0 shadow-2xs">
            <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" />
            <span>{{ $errorMessage ?? session('error') }}</span>
        </div>
    @endif

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Card 1: Total Backups & Storage -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4.5 shadow-2xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ __('Total Respaldos') }}</span>
                <div class="w-8 h-8 rounded-lg bg-stone-100 flex items-center justify-center text-stone-700">
                    <x-lucide-hard-drive class="w-4 h-4" />
                </div>
            </div>
            <div>
                <div class="text-2xl font-bold text-zinc-900 tracking-tight">
                    {{ $totalBackups }}
                </div>
                <p class="text-xs text-zinc-500 mt-0.5">
                    {{ __('Espacio ocupado:') }} <span class="font-medium text-zinc-700">{{ $totalStorageSize >= 1048576 ? number_format($totalStorageSize / 1048576, 2) . ' MB' : number_format($totalStorageSize / 1024, 2) . ' KB' }}</span>
                </p>
            </div>
        </div>

        <!-- Card 2: Countdown Timer to Next Backup -->
        <div 
            x-data="backupCountdown('{{ $nextBackupTime }}')" 
            class="bg-white border border-[#e9e9e7] rounded-2xl p-4.5 shadow-2xs flex flex-col justify-between space-y-2"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ __('Siguiente Respaldo') }}</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
                    <x-lucide-clock class="w-4 h-4" />
                </div>
            </div>
            <div>
                <div class="text-2xl font-mono font-bold text-zinc-900 tracking-tight" x-text="timeRemaining">
                    00h 00m
                </div>
                <p class="text-xs text-zinc-500 mt-0.5">
                    {{ __('Programado diariamente a las 00:00') }}
                </p>
            </div>
        </div>

        <!-- Card 3: Last Backup Status -->
        <div class="bg-white border border-[#e9e9e7] rounded-2xl p-4.5 shadow-2xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ __('Último Respaldo') }}</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <x-lucide-database class="w-4 h-4" />
                </div>
            </div>
            <div>
                @if($latestBackup)
                    <div class="text-sm font-bold text-zinc-900 truncate" title="{{ $latestBackup['filename'] }}">
                        {{ $latestBackup['created_at_human'] }}
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        {{ $latestBackup['created_at'] }}
                    </p>
                @else
                    <div class="text-sm font-semibold text-zinc-400">
                        {{ __('Sin respaldos aún') }}
                    </div>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Crea uno manualmente arriba') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Backups List Table -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl shadow-2xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#e9e9e7] flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-stone-50/50">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-zinc-900 flex items-center gap-2">
                    <x-lucide-hard-drive class="w-4 h-4 text-stone-600" />
                    <span>{{ __('Archivos de Respaldo') }}</span>
                </h2>
                <span class="text-xs text-zinc-500 font-medium">
                    @if($search !== '')
                        ({{ $filteredCount }} {{ __('de') }} {{ $totalBackups }})
                    @else
                        ({{ $totalBackups }} {{ __('disponible(s)') }})
                    @endif
                </span>
            </div>

            <!-- Search and Controls -->
            <div class="flex items-center gap-2.5">
                <div class="relative w-full sm:w-64">
                    <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="{{ __('Buscar respaldo o fecha...') }}" 
                        class="w-full pl-8.5 pr-8 py-1.5 text-xs bg-white border border-[#e9e9e7] rounded-xl text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-stone-400 focus:ring-1 focus:ring-stone-400 transition shadow-2xs"
                    />
                    @if($search !== '')
                        <button 
                            wire:click="$set('search', '')" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 cursor-pointer"
                            title="{{ __('Limpiar búsqueda') }}"
                        >
                            <x-lucide-x class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if($totalBackups > 0)
            @if(count($backups) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-[#e9e9e7] text-[11px] font-bold text-zinc-400 uppercase tracking-wider bg-white">
                                <th class="py-3 px-5">{{ __('Archivo') }}</th>
                                <th class="py-3 px-4">{{ __('Motor') }}</th>
                                <th class="py-3 px-4">{{ __('Tamaño') }}</th>
                                <th class="py-3 px-4">{{ __('Fecha de Creación') }}</th>
                                <th class="py-3 px-5 text-right">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e9e9e7] text-xs font-medium text-zinc-700">
                            @foreach($backups as $backup)
                                <tr wire:key="backup-{{ $backup['filename'] }}" class="hover:bg-stone-50/60 transition duration-150">
                                    <td class="py-3.5 px-5 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-stone-100 flex items-center justify-center text-stone-600 shrink-0">
                                            <x-lucide-database class="w-4 h-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-zinc-900 truncate max-w-xs sm:max-w-md" title="{{ $backup['filename'] }}">
                                                {{ $backup['filename'] }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-stone-100 text-stone-800 text-[10px] font-bold tracking-wide">
                                            {{ $backup['driver'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-zinc-600 font-mono text-[11px]">
                                        {{ $backup['size'] }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="text-zinc-900 font-medium">{{ $backup['created_at'] }}</div>
                                        <div class="text-[11px] text-zinc-400">{{ $backup['created_at_human'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button 
                                                wire:click="downloadBackup('{{ $backup['filename'] }}')"
                                                title="{{ __('Descargar respaldo') }}"
                                                class="p-1.5 rounded-lg text-zinc-500 hover:text-stone-900 hover:bg-stone-100 transition cursor-pointer"
                                            >
                                                <x-lucide-download class="w-4 h-4" />
                                            </button>
                                            <button 
                                                wire:click="deleteBackup('{{ $backup['filename'] }}')"
                                                wire:confirm="{{ __('¿Estás seguro de que deseas eliminar este respaldo?') }}"
                                                title="{{ __('Eliminar respaldo') }}"
                                                class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
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

                <!-- Integrated Bottom Pagination Footer -->
                <div class="shrink-0 bg-white border-t border-[#e9e9e7] p-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-zinc-600 font-medium">
                    <div class="flex items-center gap-3">
                        <span>
                            {{ __('Mostrando') }} <strong class="text-zinc-900 font-semibold">{{ $backups->firstItem() ?? 0 }}</strong> – <strong class="text-zinc-900 font-semibold">{{ $backups->lastItem() ?? 0 }}</strong> {{ __('de') }} <strong class="text-zinc-900 font-semibold">{{ $backups->total() }}</strong> {{ __('respaldo(s)') }}
                        </span>
                        <div class="h-3 w-px bg-zinc-300"></div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-zinc-400 text-[11px]">{{ __('Ver:') }}</span>
                            <select wire:model.live="perPage" class="bg-[#fbfbfa] border border-[#e9e9e7] rounded-lg px-2 py-0.5 text-xs text-zinc-800 font-medium focus:outline-none">
                                <option value="5">5 / pág</option>
                                <option value="10">10 / pág</option>
                                <option value="25">25 / pág</option>
                                <option value="50">50 / pág</option>
                            </select>
                        </div>
                    </div>

                    @if($backups->hasPages())
                        <div class="flex items-center gap-1.5 shrink-0">
                            <!-- Previous Button -->
                            @if($backups->onFirstPage())
                                <span class="px-2.5 py-1 rounded-lg bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                                    <x-lucide-chevron-left class="w-3.5 h-3.5" />
                                    <span>{{ __('Anterior') }}</span>
                                </span>
                            @else
                                <button wire:click="previousPage" class="px-2.5 py-1 rounded-lg bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1 cursor-pointer">
                                    <x-lucide-chevron-left class="w-3.5 h-3.5 text-zinc-600" />
                                    <span>{{ __('Anterior') }}</span>
                                </button>
                            @endif

                            <!-- Page Indicator -->
                            <span class="px-3 py-1 rounded-lg bg-stone-100 border border-stone-200 text-xs font-semibold font-mono text-zinc-800">
                                {{ $backups->currentPage() }} / {{ $backups->lastPage() }}
                            </span>

                            <!-- Next Button -->
                            @if($backups->hasMorePages())
                                <button wire:click="nextPage" class="px-2.5 py-1 rounded-lg bg-white hover:bg-stone-100 text-zinc-800 border border-stone-200 text-xs font-medium transition shadow-2xs flex items-center gap-1 cursor-pointer">
                                    <span>{{ __('Siguiente') }}</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 text-zinc-600" />
                                </button>
                            @else
                                <span class="px-2.5 py-1 rounded-lg bg-stone-100 text-zinc-400 border border-stone-200 text-xs font-medium cursor-not-allowed flex items-center gap-1">
                                    <span>{{ __('Siguiente') }}</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5" />
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="p-8 text-center flex flex-col items-center justify-center space-y-2">
                    <x-lucide-search-x class="w-8 h-8 text-zinc-400" />
                    <p class="text-xs font-medium text-zinc-600">{{ __('No se encontraron respaldos que coincidan con la búsqueda.') }}</p>
                    <button wire:click="$set('search', '')" class="text-xs text-stone-700 underline font-semibold cursor-pointer">
                        {{ __('Limpiar búsqueda') }}
                    </button>
                </div>
            @endif
        @else
            <div class="p-10 text-center flex flex-col items-center justify-center space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-stone-100 text-stone-400 flex items-center justify-center">
                    <x-lucide-hard-drive class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-900">{{ __('No hay respaldos disponibles') }}</h3>
                    <p class="text-xs text-zinc-500 mt-1 max-w-sm">{{ __('Aún no se ha generado ninguna copia de seguridad local. Puedes crear la primera haciendo clic a continuación.') }}</p>
                </div>
                <button 
                    wire:click="createBackup"
                    wire:loading.attr="disabled"
                    class="mt-2 px-3.5 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl transition cursor-pointer flex items-center gap-2 shadow-2xs"
                >
                    <x-lucide-plus class="w-3.5 h-3.5" />
                    <span>{{ __('Crear Primer Respaldo') }}</span>
                </button>
            </div>
        @endif
    </div>

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
