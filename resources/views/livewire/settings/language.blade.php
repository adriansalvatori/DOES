<div class="h-full flex flex-col space-y-4 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 max-w-4xl mx-auto w-full">
    
    <!-- Notion Header & Controls -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-stone-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <x-lucide-languages class="w-5 h-5 text-stone-100" />
            </div>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-zinc-900 tracking-tight">{{ __('Idioma / Language') }}</h1>
                <p class="text-xs text-zinc-500 mt-0.5">{{ __('Selecciona tu idioma preferido para la interfaz.') }}</p>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3.5 rounded-xl text-xs font-medium flex items-center gap-2 shrink-0 shadow-2xs">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- Language Selector Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Spanish Card -->
        <div 
            wire:click="setLocale('es')"
            @click="localStorage.setItem('app_locale', 'es')"
            class="bg-white border-2 rounded-2xl p-5 cursor-pointer transition-all duration-200 select-none relative shadow-2xs hover:shadow-md flex flex-col justify-between space-y-4 {{ $currentLocale === 'es' ? 'border-stone-900 bg-stone-50/50 ring-2 ring-stone-900/10' : 'border-[#e9e9e7] hover:border-stone-400' }}"
        >
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-stone-900 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        ES
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-zinc-900">Español</h3>
                        <p class="text-xs text-zinc-500">Spanish (Default)</p>
                    </div>
                </div>
                @if($currentLocale === 'es')
                    <span class="px-2.5 py-0.5 rounded-full bg-stone-900 text-white text-[10px] font-bold flex items-center gap-1 shadow-2xs">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                        <span>{{ __('Activo') }}</span>
                    </span>
                @endif
            </div>

            <p class="text-xs text-zinc-600 leading-relaxed">
                Interfaz nativa en español con términos y flujos adaptados al equipo de diseño.
            </p>
        </div>

        <!-- English Card -->
        <div 
            wire:click="setLocale('en')"
            @click="localStorage.setItem('app_locale', 'en')"
            class="bg-white border-2 rounded-2xl p-5 cursor-pointer transition-all duration-200 select-none relative shadow-2xs hover:shadow-md flex flex-col justify-between space-y-4 {{ $currentLocale === 'en' ? 'border-stone-900 bg-stone-50/50 ring-2 ring-stone-900/10' : 'border-[#e9e9e7] hover:border-stone-400' }}"
        >
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        EN
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-zinc-900">English</h3>
                        <p class="text-xs text-zinc-500">Inglés</p>
                    </div>
                </div>
                @if($currentLocale === 'en')
                    <span class="px-2.5 py-0.5 rounded-full bg-stone-900 text-white text-[10px] font-bold flex items-center gap-1 shadow-2xs">
                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                        <span>{{ __('Activo') }}</span>
                    </span>
                @endif
            </div>

            <p class="text-xs text-zinc-600 leading-relaxed">
                Full English interface translation for international team members and partners.
            </p>
        </div>
    </div>

</div>
