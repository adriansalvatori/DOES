<div class="h-screen h-[100dvh] max-h-[100dvh] w-full flex flex-col justify-center items-center bg-gradient-to-br from-[#f7f7f5] via-[#f1f1ed] to-[#e7e7e2] overflow-hidden relative">

    <!-- Centered Card Container (Overlaps Footer, Middle of the Screen) -->
    <main class="relative z-10 w-full max-w-md mx-auto px-4 sm:px-6 space-y-5 my-auto">
        
        <!-- Brand / Header -->
        <div class="text-center space-y-2.5">
            <div class="inline-flex items-center justify-center w-13 h-13 rounded-2xl bg-[#eda621] text-white shadow-xl shadow-[#eda621]/25 ring-4 ring-[#eda621]/20 mb-0.5">
                <img src="{{ asset('images/kudos-hand-white.svg') }}" alt="{{ config('app.name') }}" class="w-8 h-8 object-contain">
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">
                {{ config('app.name') }}
            </h2>
            <p class="text-xs text-zinc-500 max-w-xs mx-auto">
                {{ __('Ingresa con tu cuenta para acceder a la gestión de órdenes y flujos de trabajo.') }}
            </p>
        </div>

        <!-- Login Form Card -->
        <div class="bg-white/85 backdrop-blur-xl border border-stone-200/90 shadow-2xl rounded-2xl p-6 sm:p-7 space-y-5">
            
            <form wire:submit="login" class="space-y-4" x-data="{ showPassword: false }">
                
                <!-- Email Input -->
                <div class="space-y-1.5">
                    <label for="email" class="block text-xs font-semibold text-zinc-700">
                        {{ __('Correo electrónico') }}
                    </label>
                    <div class="relative rounded-lg shadow-xs">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                            <x-lucide-mail class="w-4 h-4" />
                        </div>
                        <input 
                            wire:model="email" 
                            type="email" 
                            id="email" 
                            required 
                            autofocus 
                            placeholder="tu.correo@kudos.com"
                            class="block w-full pl-9 pr-3 py-2 text-xs text-zinc-900 bg-stone-50/50 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900 transition" />
                    </div>
                    @error('email')
                        <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input with Show/Hide Toggle -->
                <div class="space-y-1.5">
                    <label for="password" class="block text-xs font-semibold text-zinc-700">
                        {{ __('Contraseña') }}
                    </label>
                    <div class="relative rounded-lg shadow-xs">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                            <x-lucide-lock class="w-4 h-4" />
                        </div>
                        <input 
                            wire:model="password" 
                            :type="showPassword ? 'text' : 'password'" 
                            id="password" 
                            required 
                            placeholder="••••••••"
                            class="block w-full pl-9 pr-10 py-2 text-xs text-zinc-900 bg-stone-50/50 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900 transition" />
                        
                        <!-- Toggle Button Inside Input -->
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-700 transition cursor-pointer select-none focus:outline-none"
                            tabindex="-1"
                            :title="showPassword ? '{{ __('Ocultar contraseña') }}' : '{{ __('Mostrar contraseña') }}'">
                            <x-lucide-eye x-show="!showPassword" class="w-4 h-4" />
                            <x-lucide-eye-off x-show="showPassword" class="w-4 h-4" style="display: none;" />
                        </button>
                    </div>
                    @error('password')
                        <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me & Show Password Checkboxes -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input 
                            wire:model="remember" 
                            type="checkbox" 
                            class="w-3.5 h-3.5 rounded border-stone-300 text-stone-900 focus:ring-stone-900 transition cursor-pointer">
                        <span class="text-xs text-zinc-600 select-none">{{ __('Recordar sesión') }}</span>
                    </label>

                    <label class="flex items-center gap-1.5 cursor-pointer text-xs text-zinc-500 hover:text-zinc-800 transition select-none">
                        <input 
                            type="checkbox" 
                            x-model="showPassword" 
                            class="w-3.5 h-3.5 rounded border-stone-300 text-stone-900 focus:ring-stone-900 transition cursor-pointer">
                        <span>{{ __('Mostrar contraseña') }}</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-lg shadow-md hover:shadow-lg transition cursor-pointer disabled:opacity-50">
                    <x-lucide-log-in class="w-4 h-4" wire:loading.remove />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" wire:loading />
                    <span>{{ __('Iniciar Sesión') }}</span>
                </button>
            </form>

            <!-- Quick Demo Accounts (Helper for quick testing) -->
            <div class="border-t border-stone-200/60 pt-3.5 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-zinc-400">
                        {{ __('Cuentas de demostración rápida') }}
                    </p>
                    @if($isDemo)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">
                            <x-lucide-database class="w-3 h-3 text-amber-600" />
                            {{ __('Demo DB activa') }}
                        </span>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <a 
                        href="{{ route('demo.direct-login', ['role' => 'admin']) }}"
                        class="px-2.5 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-800 border border-purple-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer select-none">
                        <span class="inline-flex items-center gap-1.5">
                            <x-lucide-crown class="w-3.5 h-3.5 text-purple-600 shrink-0" />
                            <span>{{ __('Admin') }}</span>
                        </span>
                        <x-lucide-arrow-right class="w-3 h-3 text-purple-600 shrink-0" />
                    </a>

                    <a 
                        href="{{ route('demo.direct-login', ['role' => 'manager']) }}"
                        class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer select-none">
                        <span class="inline-flex items-center gap-1.5">
                            <x-lucide-clipboard-list class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                            <span>{{ __('Manager') }}</span>
                        </span>
                        <x-lucide-arrow-right class="w-3 h-3 text-blue-600 shrink-0" />
                    </a>

                    <a 
                        href="{{ route('demo.direct-login', ['role' => 'designer']) }}"
                        class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer select-none">
                        <span class="inline-flex items-center gap-1.5">
                            <x-lucide-palette class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>{{ __('Designer') }}</span>
                        </span>
                        <x-lucide-arrow-right class="w-3 h-3 text-emerald-600 shrink-0" />
                    </a>

                    <a 
                        href="{{ route('demo.direct-login', ['role' => 'comercial']) }}"
                        class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer select-none">
                        <span class="inline-flex items-center gap-1.5">
                            <x-lucide-briefcase class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            <span>{{ __('Comercial') }}</span>
                        </span>
                        <x-lucide-arrow-right class="w-3 h-3 text-amber-600 shrink-0" />
                    </a>
                </div>
            </div>

        </div>

    </main>

    <!-- Full-Width Footer Anchored at Bottom (Overlapped by Login Card) -->
    <footer class="absolute bottom-0 inset-x-0 w-full z-0 pointer-events-none select-none flex flex-col items-center">
        <div class="w-full overflow-hidden leading-none">
            <img src="{{ asset('images/footer-decor.svg') }}" alt="Kudos Decor" class="w-full h-auto block">
        </div>
        <div class="w-full py-2.5 text-center bg-transparent pointer-events-auto">
            <p class="text-[11px] text-zinc-400 px-4">
                {{ config('app.name') }} &copy; {{ date('Y') }} — Trello Workflow Layer
            </p>
        </div>
    </footer>

</div>
