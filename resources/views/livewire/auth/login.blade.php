<div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-[#f7f7f5] via-[#f1f1ed] to-[#e7e7e2]">
    
    <!-- Outer Card Container -->
    <div class="w-full max-w-md space-y-6">
        
        <!-- Brand / Header -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-stone-900 text-white shadow-xl ring-4 ring-stone-900/10 mb-1">
                <img src="{{ asset('favicon.png') }}" alt="Kudos Logo" class="w-9 h-9 object-contain">
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">
                Kudos Design Ops
            </h2>
            <p class="text-xs text-zinc-500 max-w-xs mx-auto">
                {{ __('Ingresa con tu cuenta para acceder a la gestión de órdenes y flujos de trabajo.') }}
            </p>
        </div>

        <!-- Login Form Card -->
        <div class="bg-white/80 backdrop-blur-xl border border-stone-200/80 shadow-xl rounded-2xl p-6 sm:p-8 space-y-6">
            
            <form wire:submit="login" class="space-y-4">
                
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

                <!-- Password Input -->
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
                            type="password" 
                            id="password" 
                            required 
                            placeholder="••••••••"
                            class="block w-full pl-9 pr-3 py-2 text-xs text-zinc-900 bg-stone-50/50 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900 transition" />
                    </div>
                    @error('password')
                        <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input 
                            wire:model="remember" 
                            type="checkbox" 
                            class="w-3.5 h-3.5 rounded border-stone-300 text-stone-900 focus:ring-stone-900 transition cursor-pointer">
                        <span class="text-xs text-zinc-600 select-none">{{ __('Recordar sesión') }}</span>
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
            <div class="border-t border-stone-200/60 pt-4 space-y-2">
                <p class="text-[10px] uppercase tracking-wider font-semibold text-zinc-400 text-center">
                    {{ __('Cuentas de demostración rápida') }}
                </p>
                <div class="grid grid-cols-2 gap-1.5">
                    <button 
                        type="button"
                        wire:click="$set('email', 'admin@kudos.com'); $set('password', 'password');"
                        class="px-2 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-800 border border-purple-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer">
                        <span>👑 Admin</span>
                        <x-lucide-arrow-right class="w-3 h-3 text-purple-600" />
                    </button>

                    <button 
                        type="button"
                        wire:click="$set('email', 'camila@kudos.com'); $set('password', 'password');"
                        class="px-2 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer">
                        <span>📋 PM Camila</span>
                        <x-lucide-arrow-right class="w-3 h-3 text-blue-600" />
                    </button>

                    <button 
                        type="button"
                        wire:click="$set('email', 'euraliz@kudos.com'); $set('password', 'password');"
                        class="px-2 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer">
                        <span>🎨 Euralíz</span>
                        <x-lucide-arrow-right class="w-3 h-3 text-emerald-600" />
                    </button>

                    <button 
                        type="button"
                        wire:click="$set('email', 'ventas@kudos.com'); $set('password', 'password');"
                        class="px-2 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[11px] font-medium flex items-center justify-between transition cursor-pointer">
                        <span>💼 Comercial</span>
                        <x-lucide-arrow-right class="w-3 h-3 text-amber-600" />
                    </button>
                </div>
            </div>

        </div>

        <p class="text-center text-[11px] text-zinc-400">
            Kudos Design Ops &copy; {{ date('Y') }} — Trello Workflow Layer
        </p>

    </div>
</div>
