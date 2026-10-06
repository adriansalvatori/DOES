<div 
    x-data="profileSettingsPage(@js($activeTab))"
    x-init="init()"
    @input="checkDirty()"
    @change="checkDirty()"
    @form-saved.window="handleFormSaved()"
    class="flex-1 w-full flex flex-col space-y-6 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-2 max-w-5xl mx-auto pb-28">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4 shrink-0">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Mi Perfil y Configuración') }}</h1>
        </div>

        <div class="flex items-center gap-2">
            <!-- Global Logout Button in Profile Header -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button 
                    type="submit" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 border border-red-200/80 hover:border-red-300 bg-red-50/70 hover:bg-red-100/80 text-red-700 text-xs font-semibold rounded-xl shadow-2xs transition cursor-pointer">
                    <x-lucide-log-out class="w-4 h-4 text-red-600" />
                    <span>{{ __('Cerrar Sesión') }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- User Mini Hero Card -->
    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-4">
            @if($avatar_file && ! $errors->has('avatar_file') && in_array(strtolower($avatar_file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif']))
                <img src="{{ $avatar_file->temporaryUrl() }}" alt="{{ $user->name }}" class="w-14 h-14 rounded-full object-cover shadow-sm border border-stone-200" />
            @elseif($user->avatar_url)
                <div x-data="{ imgError: false }" class="shrink-0">
                    <img src="{{ $user->avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $user->name }}" class="w-14 h-14 rounded-full object-cover shadow-sm border border-stone-200" />
                    <div x-show="imgError" x-cloak class="w-14 h-14 rounded-full bg-stone-900 text-white font-bold text-lg flex items-center justify-center shadow-md shrink-0">
                        {{ $user->initials }}
                    </div>
                </div>
            @else
                <div class="w-14 h-14 rounded-full bg-stone-900 text-white font-bold text-lg flex items-center justify-center shadow-md shrink-0">
                    {{ $user->initials }}
                </div>
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-bold text-base text-zinc-900 truncate">{{ $user->name }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $user->role?->badgeStyle() ?? 'bg-emerald-100 text-emerald-800 border-emerald-300' }}">
                        {{ $user->role?->label() ?? __('Diseñador') }}
                    </span>
                    @if($user->designer && $user->designer->is_lead)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border" style="{{ $user->designer->badge_inline_style }}">
                            👑 {{ __('Lead Designer') }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-zinc-500 mt-0.5 truncate">{{ $user->email }}</p>
                <div class="flex items-center gap-3 mt-1 text-[11px] text-zinc-400">
                    @if($user->phone)
                        <span class="flex items-center gap-1 font-mono">
                            <x-lucide-phone class="w-3 h-3" />
                            {{ $user->phone }}
                        </span>
                    @endif
                    @if($user->last_login_at)
                        <span class="flex items-center gap-1">
                            <x-lucide-clock class="w-3 h-3" />
                            {{ __('Último acceso') }}: {{ $user->last_login_at->diffForHumans() }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if($user->designer)
            <div class="flex items-center gap-3 border-t md:border-t-0 md:border-l border-stone-100 pt-3 md:pt-0 md:pl-5 shrink-0">
                <div class="text-right">
                    <p class="text-[10px] text-zinc-400 uppercase font-semibold">{{ __('Diseñador Vinculado') }}</p>
                    <p class="text-xs font-semibold text-zinc-800">{{ $user->designer->name }}</p>
                </div>
                <div class="w-4 h-4 rounded-full border border-stone-300 shadow-2xs shrink-0" style="background-color: {{ $user->designer->hex_color ?? '#06b6d4' }}" title="{{ $user->designer->color_type ?? 'Color de diseñador' }}"></div>
            </div>
        @endif
    </div>

    <!-- Navigation Tabs Bar -->
    <div class="flex items-center gap-1 border-b border-[#e9e9e7] overflow-x-auto pb-px text-xs font-medium text-zinc-500 shrink-0">
        <button 
            type="button"
            @click="switchTab('general')" 
            class="px-3.5 py-2.5 rounded-t-xl transition flex items-center gap-2 border-b-2 cursor-pointer whitespace-nowrap shrink-0 {{ $activeTab === 'general' ? 'border-stone-900 text-stone-900 font-semibold bg-white' : 'border-transparent hover:text-zinc-900 hover:bg-stone-50' }}">
            <x-lucide-user class="w-4 h-4 {{ $activeTab === 'general' ? 'text-stone-900' : 'text-zinc-400' }}" />
            <span>{{ __('General & Perfil') }}</span>
        </button>

        <button 
            type="button"
            @click="switchTab('notifications')" 
            class="px-3.5 py-2.5 rounded-t-xl transition flex items-center gap-2 border-b-2 cursor-pointer whitespace-nowrap shrink-0 {{ $activeTab === 'notifications' ? 'border-stone-900 text-stone-900 font-semibold bg-white' : 'border-transparent hover:text-zinc-900 hover:bg-stone-50' }}">
            <x-lucide-bell class="w-4 h-4 {{ $activeTab === 'notifications' ? 'text-stone-900' : 'text-zinc-400' }}" />
            <span>{{ __('Notificaciones') }}</span>
        </button>

        <button 
            type="button"
            @click="switchTab('preferences')" 
            class="px-3.5 py-2.5 rounded-t-xl transition flex items-center gap-2 border-b-2 cursor-pointer whitespace-nowrap shrink-0 {{ $activeTab === 'preferences' ? 'border-stone-900 text-stone-900 font-semibold bg-white' : 'border-transparent hover:text-zinc-900 hover:bg-stone-50' }}">
            <x-lucide-sliders class="w-4 h-4 {{ $activeTab === 'preferences' ? 'text-stone-900' : 'text-zinc-400' }}" />
            <span>{{ __('Entorno & Idioma') }}</span>
        </button>

        <button 
            type="button"
            @click="switchTab('security')" 
            class="px-3.5 py-2.5 rounded-t-xl transition flex items-center gap-2 border-b-2 cursor-pointer whitespace-nowrap shrink-0 {{ $activeTab === 'security' ? 'border-stone-900 text-stone-900 font-semibold bg-white' : 'border-transparent hover:text-zinc-900 hover:bg-stone-50' }}">
            <x-lucide-shield class="w-4 h-4 {{ $activeTab === 'security' ? 'text-stone-900' : 'text-zinc-400' }}" />
            <span>{{ __('Seguridad & Sesión') }}</span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: GENERAL & PROFILE                   -->
    <!-- ========================================== -->
    @if($activeTab === 'general')
        <div class="space-y-6">
            @if(session('success_profile'))
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('success_profile') }}</span>
                </div>
            @endif

            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs space-y-6">
                <div>
                    <h3 class="font-bold text-sm text-zinc-900">{{ __('Información Personal') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Tus datos de identificación dentro del equipo de Kudos.') }}</p>
                </div>

                <form wire:submit="updateProfile" class="space-y-4 max-w-xl">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Nombre Completo') }}</label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            required 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                        @error('name') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Correo Electrónico') }}</label>
                        <input 
                            wire:model="email" 
                            type="email" 
                            required 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                        @error('email') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <!-- Phone / WhatsApp Input with Filterable Country Indicator -->
                    <div 
                        x-data="{
                            open: false,
                            search: '',
                            selectedCountry: { name: 'Venezuela', code: 'VE', dial: '+58', flag: '🇻🇪', placeholder: '412 1234567' },
                            countries: [
                                { name: 'Venezuela', code: 'VE', dial: '+58', flag: '🇻🇪', placeholder: '412 1234567' },
                                { name: 'Estados Unidos', code: 'US', dial: '+1', flag: '🇺🇸', placeholder: '(555) 000-0000' },
                                { name: 'Colombia', code: 'CO', dial: '+57', flag: '🇨🇴', placeholder: '300 123 4567' },
                                { name: 'España', code: 'ES', dial: '+34', flag: '🇪🇸', placeholder: '612 34 56 78' },
                                { name: 'México', code: 'MX', dial: '+52', flag: '🇲🇽', placeholder: '55 1234 5678' },
                                { name: 'Argentina', code: 'AR', dial: '+54', flag: '🇦🇷', placeholder: '11 1234-5678' },
                                { name: 'Chile', code: 'CL', dial: '+56', flag: '🇨🇱', placeholder: '9 1234 5678' },
                                { name: 'Perú', code: 'PE', dial: '+51', flag: '🇵🇪', placeholder: '912 345 678' },
                                { name: 'Ecuador', code: 'EC', dial: '+593', flag: '🇪🇨', placeholder: '99 123 4567' },
                                { name: 'Panamá', code: 'PA', dial: '+507', flag: '🇵🇦', placeholder: '6123-4567' },
                                { name: 'Costa Rica', code: 'CR', dial: '+506', flag: '🇨🇷', placeholder: '8123 4567' },
                                { name: 'República Dominicana', code: 'DO', dial: '+1', flag: '🇩🇴', placeholder: '809 123 4567' },
                                { name: 'Uruguay', code: 'UY', dial: '+598', flag: '🇺🇾', placeholder: '91 234 567' },
                                { name: 'Paraguay', code: 'PY', dial: '+595', flag: '🇵🇾', placeholder: '981 123456' },
                                { name: 'Bolivia', code: 'BO', dial: '+591', flag: '🇧🇴', placeholder: '71234567' },
                                { name: 'Guatemala', code: 'GT', dial: '+502', flag: '🇬🇹', placeholder: '5123 4567' },
                                { name: 'El Salvador', code: 'SV', dial: '+503', flag: '🇸🇻', placeholder: '7123 4567' },
                                { name: 'Honduras', code: 'HN', dial: '+504', flag: '🇭🇳', placeholder: '9123-4567' },
                                { name: 'Nicaragua', code: 'NI', dial: '+505', flag: '🇳🇮', placeholder: '8123 4567' },
                                { name: 'Brasil', code: 'BR', dial: '+55', flag: '🇧🇷', placeholder: '11 91234-5678' },
                                { name: 'Canadá', code: 'CA', dial: '+1', flag: '🇨🇦', placeholder: '(555) 000-0000' },
                                { name: 'Reino Unido', code: 'GB', dial: '+44', flag: '🇬🇧', placeholder: '7911 123456' },
                                { name: 'Portugal', code: 'PT', dial: '+351', flag: '🇵🇹', placeholder: '912 345 678' },
                                { name: 'Francia', code: 'FR', dial: '+33', flag: '🇫🇷', placeholder: '6 12 34 56 78' },
                                { name: 'Alemania', code: 'DE', dial: '+49', flag: '🇩🇪', placeholder: '151 12345678' },
                                { name: 'Italia', code: 'IT', dial: '+39', flag: '🇮🇹', placeholder: '312 345 6789' },
                                { name: 'Países Bajos', code: 'NL', dial: '+31', flag: '🇳🇱', placeholder: '6 12345678' },
                                { name: 'Suiza', code: 'CH', dial: '+41', flag: '🇨🇭', placeholder: '78 123 45 67' },
                                { name: 'Australia', code: 'AU', dial: '+61', flag: '🇦🇺', placeholder: '412 345 678' },
                                { name: 'Puerto Rico', code: 'PR', dial: '+1', flag: '🇵🇷', placeholder: '787 123 4567' }
                            ],
                            get filteredCountries() {
                                if (!this.search.trim()) return this.countries;
                                const q = this.search.toLowerCase();
                                return this.countries.filter(c => 
                                    c.name.toLowerCase().includes(q) || 
                                    c.dial.includes(q) || 
                                    c.code.toLowerCase().includes(q)
                                );
                            },
                            selectCountry(c) {
                                this.selectedCountry = c;
                                $wire.set('phone_country', c.dial);
                                this.open = false;
                                this.search = '';
                                this.$nextTick(() => this.$refs.phoneInput.focus());
                            },
                            handleInput(e) {
                                const val = e.target.value.trim();
                                if (val.startsWith('+')) {
                                    for (const c of this.countries) {
                                        if (val.startsWith(c.dial)) {
                                            this.selectedCountry = c;
                                            $wire.set('phone_country', c.dial);
                                            const rest = val.substring(c.dial.length).trim();
                                            e.target.value = rest;
                                            $wire.set('phone_number', rest);
                                            return;
                                        }
                                    }
                                }
                            },
                            init() {
                                const currentDial = @js($phone_country) || '+58';
                                const found = this.countries.find(c => c.dial === currentDial);
                                if (found) {
                                    this.selectedCountry = found;
                                }
                            }
                        }"
                        class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Teléfono / WhatsApp') }}</label>
                        
                        <div class="relative flex items-center rounded-xl border border-stone-300 bg-white focus-within:ring-2 focus-within:ring-stone-900 focus-within:border-stone-900 transition shadow-2xs">
                            <!-- Country Selector Trigger Button -->
                            <button 
                                type="button"
                                @click="open = !open; if(open) $nextTick(() => $refs.searchInput.focus())"
                                class="h-9 px-3 flex items-center gap-1.5 border-r border-stone-200 bg-stone-50/70 hover:bg-stone-100 rounded-l-xl text-xs font-medium text-zinc-700 transition cursor-pointer shrink-0 select-none">
                                <span class="text-base leading-none" x-text="selectedCountry.flag"></span>
                                <span class="font-mono text-zinc-900 font-semibold" x-text="selectedCountry.dial"></span>
                                <x-lucide-chevron-down class="w-3.5 h-3.5 text-zinc-400" />
                            </button>

                            <!-- Hidden input to track selected country dial in form snapshot -->
                            <input type="hidden" name="phone_country" :value="selectedCountry.dial" />

                            <!-- Local Phone Number Input -->
                            <input 
                                x-ref="phoneInput"
                                wire:model="phone_number" 
                                @input="handleInput($event)"
                                type="tel" 
                                :placeholder="selectedCountry.placeholder"
                                class="w-full px-3 py-2 text-xs border-0 rounded-r-xl focus:ring-0 focus:outline-none bg-transparent placeholder-zinc-400 font-mono text-zinc-900" />

                            <!-- Searchable Dropdown Popover -->
                            <div 
                                x-show="open" 
                                @click.outside="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="transform opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-1.5 w-72 bg-white border border-stone-200 rounded-xl shadow-xl z-50 p-2 space-y-2"
                                style="display: none;">
                                
                                <!-- Search Input with Clear Button -->
                                <div class="relative">
                                    <x-lucide-search class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                    <input 
                                        x-ref="searchInput"
                                        x-model="search"
                                        type="text" 
                                        placeholder="{{ __('Buscar país o código...') }}"
                                        class="w-full pl-8 pr-7 py-1.5 text-xs border border-stone-200 rounded-lg bg-stone-50 focus:bg-white focus:ring-1 focus:ring-stone-900 focus:border-stone-900 outline-none" />
                                    <button 
                                        x-show="search.length > 0"
                                        @click="search = ''; $refs.searchInput.focus()"
                                        type="button" 
                                        class="absolute right-2 top-2 text-zinc-400 hover:text-zinc-600">
                                        <x-lucide-x class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Filtered Countries List -->
                                <div class="max-h-56 overflow-y-auto custom-vertical-scrollbar divide-y divide-stone-50 text-xs">
                                    <template x-for="c in filteredCountries" :key="c.code + c.dial">
                                        <button 
                                            type="button"
                                            @click="selectCountry(c)"
                                            class="w-full px-2.5 py-1.5 flex items-center justify-between hover:bg-stone-100 rounded-lg transition text-left cursor-pointer group"
                                            :class="selectedCountry.code === c.code && selectedCountry.dial === c.dial ? 'bg-stone-50 font-semibold' : ''">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="text-base leading-none" x-text="c.flag"></span>
                                                <span class="truncate font-medium text-zinc-800" x-text="c.name"></span>
                                                <span class="text-[10px] text-zinc-400 font-mono" x-text="'(' + c.code + ')'"></span>
                                            </div>
                                            <span class="font-mono text-[11px] font-semibold text-zinc-500 group-hover:text-zinc-900 ml-2 shrink-0" x-text="c.dial"></span>
                                        </button>
                                    </template>
                                    
                                    <div x-show="filteredCountries.length === 0" class="py-4 text-center text-xs text-zinc-400">
                                        {{ __('No se encontraron países') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-zinc-400 mt-0.5">{{ __('Para coordinación interna y avisos urgentes de operaciones.') }}</p>
                        @error('phone') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                        @error('phone_number') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <!-- Foto de Perfil / Avatar Upload Section -->
                    <div class="space-y-3 pt-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Foto de Perfil') }}</label>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-4 rounded-xl bg-stone-50/70 border border-stone-200">
                            <!-- Avatar Preview -->
                            <div x-data="{ imgError: false }" class="relative shrink-0 w-16 h-16 rounded-full overflow-hidden ring-2 ring-stone-300 shadow-xs bg-stone-200 flex items-center justify-center">
                                @if ($avatar_file && ! $errors->has('avatar_file') && in_array(strtolower($avatar_file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif']))
                                    <img src="{{ $avatar_file->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                                @elseif ($avatar_url)
                                    <img src="{{ $avatar_url }}" x-show="!imgError" x-on:error="imgError = true" alt="{{ $user->name }}" class="w-full h-full object-cover">
                                    <span x-show="imgError" x-cloak class="text-stone-700 font-bold text-lg select-none">{{ $user->initials }}</span>
                                @else
                                    <span class="text-stone-700 font-bold text-lg select-none">{{ $user->initials }}</span>
                                @endif

                                <!-- Loading overlay while uploading -->
                                <div wire:loading wire:target="avatar_file" class="absolute inset-0 bg-stone-900/60 flex items-center justify-center text-white">
                                    <x-lucide-loader-2 class="w-5 h-5 animate-spin" />
                                </div>
                            </div>

                            <!-- Upload Actions & Controls -->
                            <div class="space-y-2 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <label for="avatar_file_input" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow-xs transition cursor-pointer select-none">
                                        <x-lucide-upload class="w-3.5 h-3.5" />
                                        <span>{{ ($avatar_file || $avatar_url) ? __('Cambiar Foto') : __('Subir Foto') }}</span>
                                    </label>
                                    <input 
                                        id="avatar_file_input" 
                                        type="file" 
                                        wire:model="avatar_file" 
                                        accept="image/png,image/jpeg,image/jpg,image/webp" 
                                        class="hidden">

                                    @if($avatar_file || $avatar_url)
                                        <button 
                                            type="button" 
                                            wire:click="removeAvatar"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 bg-white hover:bg-red-50 text-red-600 text-xs font-semibold rounded-xl transition cursor-pointer">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            <span>{{ __('Eliminar') }}</span>
                                        </button>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3 text-[11px] text-zinc-500">
                                    <span>{{ __('Formatos:') }} <strong>PNG, JPG o WEBP</strong> (máx. 3 MB)</span>
                                </div>

                                @error('avatar_file')
                                    <p class="text-[11px] text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- External URL Collapsible (Optional) -->
                        <div x-data="{ showUrl: {{ !empty($avatar_url) && !str_starts_with($avatar_url, '/storage/') ? 'true' : 'false' }} }" class="pt-1">
                            <button 
                                type="button" 
                                @click="showUrl = !showUrl" 
                                class="text-[11px] text-zinc-500 hover:text-zinc-800 font-medium inline-flex items-center gap-1 transition cursor-pointer">
                                <x-lucide-link class="w-3 h-3 text-zinc-400" />
                                <span x-text="showUrl ? '{{ __('Ocultar URL de imagen externa') }}' : '{{ __('O ingresar una URL externa de imagen...') }}'"></span>
                            </button>
                            <div x-show="showUrl" x-cloak class="mt-2 space-y-1">
                                <input 
                                    wire:model="avatar_url" 
                                    type="url" 
                                    placeholder="https://ejemplo.com/avatar.jpg"
                                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900 font-mono" />
                                <p class="text-[10px] text-zinc-400">{{ __('Enlace directo HTTP/HTTPS a tu imagen o avatar de servicio externo.') }}</p>
                                @error('avatar_url') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <!-- Disabled by default when no changes -->
                        <template x-if="!isDirty">
                            <button 
                                type="button" 
                                disabled 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-stone-100 text-stone-400 border border-stone-200/80 text-xs font-semibold rounded-xl cursor-not-allowed select-none opacity-60 shadow-none">
                                <x-lucide-save class="w-4 h-4 text-stone-400" />
                                <span>{{ __('Guardar Cambios') }}</span>
                            </button>
                        </template>

                        <!-- Appears in green when user makes changes -->
                        <template x-if="isDirty">
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/25 ring-2 ring-emerald-500/20 transition-all duration-200 cursor-pointer animate-in fade-in zoom-in-95">
                                <x-lucide-check class="w-4 h-4 text-white" />
                                <span>{{ __('Guardar Cambios') }}</span>
                            </button>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- TAB 2: NOTIFICATIONS                       -->
    <!-- ========================================== -->
    @if($activeTab === 'notifications')
        <div class="space-y-6">
            @if(session('success_notifications'))
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('success_notifications') }}</span>
                </div>
            @endif

            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs space-y-6">
                <div>
                    <h3 class="font-bold text-sm text-zinc-900">{{ __('Centro de Alertas y Notificaciones') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Configura qué eventos en tus órdenes disparan avisos en la campana del Topbar.') }}</p>
                </div>

                <form wire:submit="updateNotificationSettings" class="space-y-4 max-w-2xl">
                    <div class="divide-y divide-stone-100 border border-stone-200 rounded-xl overflow-hidden">
                        
                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-stone-50/50 transition">
                            <div>
                                <p class="text-xs font-semibold text-zinc-900">{{ __('Nueva orden o tarea asignada') }}</p>
                                <p class="text-[11px] text-zinc-500">{{ __('Recibir notificación cuando te asignan una orden o subtarea.') }}</p>
                            </div>
                            <input type="checkbox" wire:model="notify_order_assigned" class="w-4 h-4 text-stone-900 rounded border-stone-300 focus:ring-stone-900 cursor-pointer" />
                        </div>

                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-stone-50/50 transition">
                            <div>
                                <p class="text-xs font-semibold text-zinc-900">{{ __('Órdenes bloqueadas o sin medidas') }}</p>
                                <p class="text-[11px] text-zinc-500">{{ __('Avisar cuando una orden de tu flujo entra en estado de bloqueo o se desbloquea.') }}</p>
                            </div>
                            <input type="checkbox" wire:model="notify_order_blocked" class="w-4 h-4 text-stone-900 rounded border-stone-300 focus:ring-stone-900 cursor-pointer" />
                        </div>

                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-stone-50/50 transition">
                            <div>
                                <p class="text-xs font-semibold text-zinc-900">{{ __('Feedback de revisión (Camila / Cliente)') }}</p>
                                <p class="text-[11px] text-zinc-500">{{ __('Avisar cuando se solicitan cambios o revisiones en tus trabajos enviados.') }}</p>
                            </div>
                            <input type="checkbox" wire:model="notify_review_feedback" class="w-4 h-4 text-stone-900 rounded border-stone-300 focus:ring-stone-900 cursor-pointer" />
                        </div>

                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-stone-50/50 transition">
                            <div>
                                <p class="text-xs font-semibold text-zinc-900">{{ __('Alerta de vencimiento crítico (OVERDUE)') }}</p>
                                <p class="text-[11px] text-zinc-500">{{ __('Avisar inmediatamente si una orden supera la fecha pactada de entrega.') }}</p>
                            </div>
                            <input type="checkbox" wire:model="notify_overdue" class="w-4 h-4 text-stone-900 rounded border-stone-300 focus:ring-stone-900 cursor-pointer" />
                        </div>

                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-stone-50/50 transition bg-stone-50/30">
                            <div>
                                <p class="text-xs font-semibold text-zinc-900">{{ __('Sonido de alerta (Chime)') }}</p>
                                <p class="text-[11px] text-zinc-500">{{ __('Reproducir un sonido discreto en el navegador ante eventos de alta prioridad.') }}</p>
                            </div>
                            <input type="checkbox" wire:model="notify_sound_enabled" class="w-4 h-4 text-stone-900 rounded border-stone-300 focus:ring-stone-900 cursor-pointer" />
                        </div>

                    </div>

                    <div class="pt-2">
                        <!-- Disabled by default when no changes -->
                        <template x-if="!isDirty">
                            <button 
                                type="button" 
                                disabled 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-stone-100 text-stone-400 border border-stone-200/80 text-xs font-semibold rounded-xl cursor-not-allowed select-none opacity-60 shadow-none">
                                <x-lucide-save class="w-4 h-4 text-stone-400" />
                                <span>{{ __('Guardar Preferencias de Alertas') }}</span>
                            </button>
                        </template>

                        <!-- Appears in green when user makes changes -->
                        <template x-if="isDirty">
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/25 ring-2 ring-emerald-500/20 transition-all duration-200 cursor-pointer animate-in fade-in zoom-in-95">
                                <x-lucide-check class="w-4 h-4 text-white" />
                                <span>{{ __('Guardar Preferencias de Alertas') }}</span>
                            </button>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- TAB 3: ENVIRONMENT & PREFERENCES          -->
    <!-- ========================================== -->
    @if($activeTab === 'preferences')
        <div class="space-y-6">
            @if(session('success_preferences'))
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('success_preferences') }}</span>
                </div>
            @endif

            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs space-y-6">
                <div>
                    <h3 class="font-bold text-sm text-zinc-900">{{ __('Entorno y Preferencias de Interfaz') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Personaliza cómo se muestra la plataforma al iniciar sesión.') }}</p>
                </div>

                <form wire:submit="updatePreferences" class="space-y-6 max-w-xl">
                    
                    <!-- Language Picker -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Idioma de la Interfaz') }}</label>
                        <!-- Hidden input to track selected locale in form snapshot -->
                        <input type="hidden" name="locale" value="{{ $locale }}" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button 
                                type="button"
                                wire:click="$set('locale', 'es')"
                                @click="localStorage.setItem('app_locale', 'es'); $nextTick(() => setTimeout(() => checkDirty(), 100))"
                                class="p-3.5 border-2 rounded-2xl flex items-center justify-between gap-3 cursor-pointer transition-all duration-200 select-none text-left {{ $locale === 'es' ? 'border-stone-900 bg-stone-50 ring-2 ring-stone-900/10 shadow-2xs' : 'border-[#e9e9e7] hover:border-stone-400 bg-white' }}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-stone-900 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        ES
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-zinc-900">Español</p>
                                        <p class="text-[10px] text-zinc-400">Spanish (Predeterminado)</p>
                                    </div>
                                </div>
                                @if($locale === 'es')
                                    <span class="px-2.5 py-0.5 rounded-full bg-stone-900 text-white text-[10px] font-bold flex items-center gap-1 shadow-2xs">
                                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        <span>{{ __('Activo') }}</span>
                                    </span>
                                @endif
                            </button>

                            <button 
                                type="button"
                                wire:click="$set('locale', 'en')"
                                @click="localStorage.setItem('app_locale', 'en'); $nextTick(() => setTimeout(() => checkDirty(), 100))"
                                class="p-3.5 border-2 rounded-2xl flex items-center justify-between gap-3 cursor-pointer transition-all duration-200 select-none text-left {{ $locale === 'en' ? 'border-stone-900 bg-stone-50 ring-2 ring-stone-900/10 shadow-2xs' : 'border-[#e9e9e7] hover:border-stone-400 bg-white' }}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-stone-900 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        EN
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-zinc-900">English</p>
                                        <p class="text-[10px] text-zinc-400">English Interface</p>
                                    </div>
                                </div>
                                @if($locale === 'en')
                                    <span class="px-2.5 py-0.5 rounded-full bg-stone-900 text-white text-[10px] font-bold flex items-center gap-1 shadow-2xs">
                                        <x-lucide-check class="w-3 h-3 stroke-[3]" />
                                        <span>{{ __('Activo') }}</span>
                                    </span>
                                @endif
                            </button>
                        </div>
                    </div>

                    <!-- Default Landing Page -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Pantalla Inicial al Iniciar Sesión') }}</label>
                        <select 
                            wire:model="default_landing_page" 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900 bg-white">
                            <option value="dashboard">{{ __('Dashboard Principal (Centro de Control)') }}</option>
                            <option value="kanban">{{ __('Tablero Kanban General') }}</option>
                            <option value="planner">{{ __('Weekly Planner (Agenda Semanal)') }}</option>
                            <option value="backlog">{{ __('Backlog de Órdenes') }}</option>
                            <option value="resolver">{{ __('Cola Resolver (Atención de Bloqueos)') }}</option>
                        </select>
                        <p class="text-[10px] text-zinc-400 mt-0.5">{{ __('A dónde se te redirige automáticamente tras autenticarte.') }}</p>
                    </div>

                    <!-- Date Format -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Formato de Fecha') }}</label>
                        <select 
                            wire:model="date_format" 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900 bg-white">
                            <option value="d/m/Y">DD/MM/YYYY (ej. 29/09/2026)</option>
                            <option value="m/d/Y">MM/DD/YYYY (ej. 09/29/2026)</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <!-- Disabled by default when no changes -->
                        <template x-if="!isDirty">
                            <button 
                                type="button" 
                                disabled 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-stone-100 text-stone-400 border border-stone-200/80 text-xs font-semibold rounded-xl cursor-not-allowed select-none opacity-60 shadow-none">
                                <x-lucide-save class="w-4 h-4 text-stone-400" />
                                <span>{{ __('Guardar Preferencias de Entorno') }}</span>
                            </button>
                        </template>

                        <!-- Appears in green when user makes changes -->
                        <template x-if="isDirty">
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/25 ring-2 ring-emerald-500/20 transition-all duration-200 cursor-pointer animate-in fade-in zoom-in-95">
                                <x-lucide-check class="w-4 h-4 text-white" />
                                <span>{{ __('Guardar Preferencias de Entorno') }}</span>
                            </button>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- TAB 4: SECURITY & SESSIONS                -->
    <!-- ========================================== -->
    @if($activeTab === 'security')
        <div class="space-y-6">
            @if(session('success_password'))
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ session('success_password') }}</span>
                </div>
            @endif

            <!-- Password Change Form -->
            <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs space-y-6">
                <div>
                    <h3 class="font-bold text-sm text-zinc-900">{{ __('Cambiar Contraseña') }}</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ __('Asegúrate de utilizar una contraseña segura de al menos 8 caracteres.') }}</p>
                </div>

                <form wire:submit="updatePassword" class="space-y-4 max-w-xl">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-semibold text-zinc-700">{{ __('Contraseña Actual') }}</label>
                            <button 
                                type="button" 
                                wire:click="openRecoveryModal"
                                class="text-xs text-blue-600 hover:text-blue-800 font-semibold hover:underline cursor-pointer">
                                {{ __('¿Olvidaste tu contraseña?') }}
                            </button>
                        </div>
                        <input 
                            wire:model="current_password" 
                            type="{{ $show_passwords ? 'text' : 'password' }}" 
                            autocomplete="current-password"
                            required 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                        @error('current_password') 
                            <div class="mt-1 flex items-center justify-between text-[11px]">
                                <p class="text-red-600">{{ $message }}</p>
                                <button 
                                    type="button" 
                                    wire:click="openRecoveryModal"
                                    class="text-blue-600 hover:text-blue-800 font-semibold underline cursor-pointer">
                                    {{ __('Recupérala aquí') }} &rarr;
                                </button>
                            </div>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Nueva Contraseña') }}</label>
                        <input 
                            wire:model="new_password" 
                            type="{{ $show_passwords ? 'text' : 'password' }}" 
                            autocomplete="new-password"
                            required 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                        @error('new_password') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-zinc-700">{{ __('Confirmar Nueva Contraseña') }}</label>
                        <input 
                            wire:model="new_password_confirmation" 
                            type="{{ $show_passwords ? 'text' : 'password' }}" 
                            autocomplete="new-password"
                            required 
                            class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-600 select-none">
                            <input type="checkbox" wire:model.live="show_passwords" class="w-3.5 h-3.5 text-stone-900 rounded border-stone-300" />
                            <span>{{ __('Mostrar contraseñas') }}</span>
                        </label>

                        <!-- Disabled by default when no changes -->
                        <template x-if="!isDirty">
                            <button 
                                type="button" 
                                disabled 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-stone-100 text-stone-400 border border-stone-200/80 text-xs font-semibold rounded-xl cursor-not-allowed select-none opacity-60 shadow-none">
                                <x-lucide-lock class="w-4 h-4 text-stone-400" />
                                <span>{{ __('Actualizar Contraseña') }}</span>
                            </button>
                        </template>

                        <!-- Appears in green when user makes changes -->
                        <template x-if="isDirty">
                            <button 
                                type="submit" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/25 ring-2 ring-emerald-500/20 transition-all duration-200 cursor-pointer animate-in fade-in zoom-in-95">
                                <x-lucide-check class="w-4 h-4 text-white" />
                                <span>{{ __('Actualizar Contraseña') }}</span>
                            </button>
                        </template>
                    </div>
                </form>
            </div>

            <!-- Session & Logout Management Card (Danger Zone) -->
            <div class="bg-white border border-red-200/80 rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-sm text-red-900 flex items-center gap-2">
                            <x-lucide-alert-octagon class="w-4 h-4 text-red-600" />
                            <span>{{ __('Gestión de Sesión y Salida') }}</span>
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            {{ __('Finaliza tu sesión actual de trabajo de forma segura en este navegador.') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button 
                            type="submit" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-xl shadow transition cursor-pointer">
                            <x-lucide-log-out class="w-4 h-4" />
                            <span>{{ __('Cerrar Sesión Ahora') }}</span>
                        </button>
                    </form>
                </div>

                <div class="pt-3 border-t border-stone-100 text-[11px] text-zinc-400 flex items-center gap-4 flex-wrap">
                    <span>{{ __('IP Actual:') }} <strong class="font-mono text-zinc-600">{{ request()->ip() }}</strong></span>
                    @if($user->last_login_at)
                        <span>{{ __('Último inicio de sesión:') }} <strong class="text-zinc-600">{{ $user->last_login_at->format('d/m/Y H:i') }}</strong></span>
                    @endif
                </div>
            </div>

            <!-- Recovery Modal -->
            @if($showRecoveryModal)
                <div class="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div 
                        @click.outside="$wire.closeRecoveryModal()"
                        class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200 space-y-5">
                        
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center shrink-0">
                                    <x-lucide-key-round class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-base text-zinc-900">{{ __('Recuperar Contraseña') }}</h3>
                                    <p class="text-xs text-zinc-500">{{ __('Sesión verificada de') }} {{ $user->email }}</p>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                wire:click="closeRecoveryModal" 
                                class="text-zinc-400 hover:text-zinc-600 p-1 rounded-lg hover:bg-stone-100 transition cursor-pointer">
                                <x-lucide-x class="w-4 h-4" />
                            </button>
                        </div>

                        @if(session('recovery_email_sent'))
                            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>{{ session('recovery_email_sent') }}</span>
                            </div>
                        @endif

                        <p class="text-xs text-zinc-600 leading-relaxed">
                            {{ __('Como tienes una sesión activa en este navegador, puedes establecer una nueva contraseña directamente sin necesidad de recordar la anterior.') }}
                        </p>

                        <form wire:submit="resetPasswordWithSession" class="space-y-3.5">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-zinc-700">{{ __('Nueva Contraseña') }}</label>
                                <input 
                                    wire:model="recovery_new_password" 
                                    type="{{ $recovery_show_passwords ? 'text' : 'password' }}" 
                                    autocomplete="new-password"
                                    placeholder="{{ __('Mínimo 8 caracteres') }}"
                                    required 
                                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                                @error('recovery_new_password') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-zinc-700">{{ __('Confirmar Nueva Contraseña') }}</label>
                                <input 
                                    wire:model="recovery_new_password_confirmation" 
                                    type="{{ $recovery_show_passwords ? 'text' : 'password' }}" 
                                    autocomplete="new-password"
                                    placeholder="{{ __('Repite tu nueva contraseña') }}"
                                    required 
                                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-xl focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-600 select-none">
                                    <input type="checkbox" wire:model.live="recovery_show_passwords" class="w-3.5 h-3.5 text-stone-900 rounded border-stone-300" />
                                    <span>{{ __('Mostrar contraseñas') }}</span>
                                </label>

                                <button 
                                    type="submit" 
                                    class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow transition cursor-pointer">
                                    {{ __('Restablecer Contraseña') }}
                                </button>
                            </div>
                        </form>

                        <div class="pt-3 border-t border-stone-100 flex items-center justify-between">
                            <button 
                                type="button" 
                                wire:click="sendPasswordResetEmail"
                                class="text-[11px] text-zinc-500 hover:text-stone-900 font-medium flex items-center gap-1.5 transition cursor-pointer">
                                <x-lucide-mail class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ __('Enviar enlace a mi correo') }}</span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="closeRecoveryModal" 
                                class="text-xs text-zinc-500 hover:text-zinc-800 font-medium cursor-pointer">
                                {{ __('Cancelar') }}
                            </button>
                        </div>

                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Floating Unsaved Changes Bar -->
    <div 
        x-show="isDirty" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-8 scale-95"
        x-cloak
        class="fixed bottom-6 inset-x-0 mx-auto max-w-lg z-40 px-4 pointer-events-auto">
        <div class="bg-stone-900/95 backdrop-blur-md text-white px-4 py-3 rounded-2xl shadow-2xl border border-stone-700/60 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                <p class="text-xs font-medium text-stone-200 truncate">
                    {{ __('Tienes cambios sin guardar') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button 
                    type="button" 
                    @click="discardChanges()"
                    class="px-3 py-1.5 rounded-xl text-xs font-medium text-stone-300 hover:text-white hover:bg-stone-800 transition cursor-pointer">
                    {{ __('Descartar') }}
                </button>
                <button 
                    type="button" 
                    @click="submitActiveForm()"
                    class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/30 transition flex items-center gap-1.5 cursor-pointer ring-2 ring-emerald-500/20">
                    <x-lucide-check class="w-3.5 h-3.5" />
                    <span>{{ __('Guardar') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Alert Modal -->
    <div 
        x-show="showUnsavedModal" 
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div 
            @click.outside="cancelNavigation()"
            x-show="showUnsavedModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200 space-y-4">
            
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-200">
                    <x-lucide-alert-triangle class="w-5 h-5" />
                </div>
                <div class="space-y-1">
                    <h3 class="font-bold text-base text-zinc-900">
                        {{ __('¿Salir sin guardar los cambios?') }}
                    </h3>
                    <p class="text-xs text-zinc-600 leading-relaxed">
                        {{ __('Tienes cambios sin guardar en tu configuración. Si cambias de vista o de sección perderás las modificaciones realizadas.') }}
                    </p>
                </div>
            </div>

            <div class="pt-3 border-t border-stone-100 flex items-center justify-end gap-2.5">
                <button 
                    type="button" 
                    @click="cancelNavigation()" 
                    class="px-4 py-2 border border-stone-200 hover:bg-stone-50 text-zinc-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                    {{ __('Permanecer aquí') }}
                </button>

                <button 
                    type="button" 
                    @click="confirmNavigation()" 
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-xl shadow transition cursor-pointer">
                    {{ __('Descartar y salir') }}
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Alpine.js Profile Settings Form Tracker & Navigation Guard Script -->
<script>
    (function() {
        function registerProfileSettings() {
            if (window.Alpine) {
                Alpine.data('profileSettingsPage', (initialTab) => ({
                    activeTab: initialTab || 'general',
                    isDirty: false,
                    initialSnapshot: '',
                    showUnsavedModal: false,
                    pendingUrl: null,
                    pendingTab: null,
                    pendingForm: null,
                    linkClickHandler: null,
                    beforeUnloadHandler: null,
                    popstateHandler: null,

                    init() {
                        this.$nextTick(() => {
                            this.takeSnapshot();
                        });

                        // Watch for Livewire tab changes
                        this.$watch('$wire.activeTab', (newTab) => {
                            this.activeTab = newTab;
                            this.$nextTick(() => {
                                this.takeSnapshot();
                            });
                        });

                        // Intercept clicks on links and logout forms across the entire document
                        this.linkClickHandler = (e) => {
                            if (!this.isDirty) return;

                            const link = e.target.closest('a[href]');
                            if (link) {
                                const href = link.getAttribute('href');
                                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                                e.preventDefault();
                                e.stopPropagation();
                                this.pendingUrl = link.href;
                                this.pendingTab = null;
                                this.pendingForm = null;
                                this.showUnsavedModal = true;
                                return;
                            }

                            const submitBtn = e.target.closest('button[type="submit"]');
                            if (submitBtn) {
                                const form = submitBtn.closest('form');
                                if (form && form.action && form.action.includes('logout')) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    this.pendingForm = form;
                                    this.pendingUrl = null;
                                    this.pendingTab = null;
                                    this.showUnsavedModal = true;
                                    return;
                                }
                            }
                        };
                        document.addEventListener('click', this.linkClickHandler, true);

                        // Browser beforeunload handler (for page refresh, close tab, etc.)
                        this.beforeUnloadHandler = (e) => {
                            if (this.isDirty) {
                                e.preventDefault();
                                e.returnValue = '';
                                return '';
                            }
                        };
                        window.addEventListener('beforeunload', this.beforeUnloadHandler);

                        // Handle browser back/forward buttons
                        this.popstateHandler = (e) => {
                            if (this.isDirty) {
                                window.history.pushState(null, '', window.location.href);
                                this.pendingUrl = document.referrer || '/';
                                this.pendingTab = null;
                                this.pendingForm = null;
                                this.showUnsavedModal = true;
                            }
                        };
                        window.addEventListener('popstate', this.popstateHandler);
                    },

                    destroy() {
                        if (this.linkClickHandler) {
                            document.removeEventListener('click', this.linkClickHandler, true);
                        }
                        if (this.beforeUnloadHandler) {
                            window.removeEventListener('beforeunload', this.beforeUnloadHandler);
                        }
                        if (this.popstateHandler) {
                            window.removeEventListener('popstate', this.popstateHandler);
                        }
                    },

                    getActiveForm() {
                        return this.$el.querySelector('form[wire\\:submit]');
                    },

                    getFormSnapshot(form) {
                        if (!form) return '';
                        const items = [];
                        const elements = form.querySelectorAll('input, select, textarea');
                        elements.forEach(el => {
                            if (el.type === 'hidden' && el.name === '_token') return;
                            let val = '';
                            if (el.type === 'checkbox') {
                                val = el.checked ? '1' : '0';
                            } else if (el.type === 'radio') {
                                if (el.checked) val = el.value;
                            } else if (el.type === 'file') {
                                val = el.files && el.files.length > 0 ? (el.files[0].name + ':' + el.files[0].size) : '';
                            } else {
                                val = el.value || '';
                            }
                            const key = el.name || el.getAttribute('wire:model') || el.id || '';
                            items.push(key + '::' + val);
                        });
                        return items.join('||');
                    },

                    takeSnapshot() {
                        const form = this.getActiveForm();
                        if (form) {
                            this.initialSnapshot = this.getFormSnapshot(form);
                            this.isDirty = false;
                        }
                    },

                    checkDirty() {
                        const form = this.getActiveForm();
                        if (!form) return;
                        const current = this.getFormSnapshot(form);
                        this.isDirty = (this.initialSnapshot !== '' && current !== this.initialSnapshot);
                    },

                    switchTab(tab) {
                        if (this.activeTab === tab) return;
                        if (this.isDirty) {
                            this.pendingTab = tab;
                            this.pendingUrl = null;
                            this.pendingForm = null;
                            this.showUnsavedModal = true;
                            return;
                        }
                        this.activeTab = tab;
                        this.$wire.setTab(tab);
                    },

                    cancelNavigation() {
                        this.showUnsavedModal = false;
                        this.pendingUrl = null;
                        this.pendingTab = null;
                        this.pendingForm = null;
                    },

                    confirmNavigation() {
                        const url = this.pendingUrl;
                        const tab = this.pendingTab;
                        const form = this.pendingForm;

                        this.isDirty = false;
                        this.showUnsavedModal = false;
                        this.pendingUrl = null;
                        this.pendingTab = null;
                        this.pendingForm = null;

                        if (tab) {
                            this.activeTab = tab;
                            this.$wire.setTab(tab);
                        } else if (url) {
                            window.location.href = url;
                        } else if (form) {
                            form.submit();
                        }
                    },

                    discardChanges() {
                        this.$wire.$refresh().then(() => {
                            this.$nextTick(() => {
                                this.takeSnapshot();
                            });
                        });
                    },

                    submitActiveForm() {
                        const form = this.getActiveForm();
                        if (form) {
                            form.requestSubmit();
                        }
                    },

                    handleFormSaved() {
                        this.$nextTick(() => {
                            this.takeSnapshot();
                        });
                    }
                }));
            }
        }

        if (window.Alpine) {
            registerProfileSettings();
        } else {
            document.addEventListener('alpine:init', registerProfileSettings);
        }
    })();
</script>
