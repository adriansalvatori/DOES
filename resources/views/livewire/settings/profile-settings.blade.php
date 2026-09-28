<div class="p-6 max-w-4xl mx-auto space-y-8">
    
    <!-- Title & Header -->
    <div class="border-b border-stone-200 pb-4">
        <h1 class="text-xl font-bold text-zinc-900 tracking-tight">{{ __('Mi Perfil y Configuración') }}</h1>
        <p class="text-xs text-zinc-500 mt-1">
            {{ __('Administra tus datos personales, preferencias de seguridad y estado en la plataforma.') }}
        </p>
    </div>

    <!-- User Information Card -->
    <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-xs space-y-6">
        
        <div class="flex items-center gap-4 border-b border-stone-100 pb-4">
            <div class="w-14 h-14 rounded-full bg-stone-900 text-white font-bold text-lg flex items-center justify-center shadow-md">
                {{ auth()->user()->initials }}
            </div>
            <div>
                <h2 class="font-semibold text-sm text-zinc-900">{{ auth()->user()->name }}</h2>
                <p class="text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                <div class="mt-1 flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ auth()->user()->role?->badgeStyle() ?? 'bg-emerald-100 text-emerald-800 border-emerald-300' }}">
                        {{ auth()->user()->role?->label() ?? __('Diseñador') }}
                    </span>
                    @if(auth()->user()->designer)
                        <span class="text-[10px] text-zinc-400 font-mono">
                            Linked Designer: {{ auth()->user()->designer->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success_profile'))
            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-medium">
                {{ session('success_profile') }}
            </div>
        @endif

        <form wire:submit="updateProfile" class="space-y-4 max-w-lg">
            
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Nombre completo') }}</label>
                <input 
                    wire:model="name" 
                    type="text" 
                    required 
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                @error('name') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Correo electrónico') }}</label>
                <input 
                    wire:model="email" 
                    type="email" 
                    required 
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                @error('email') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Teléfono') }}</label>
                <input 
                    wire:model="phone" 
                    type="text" 
                    placeholder="+58 412 1234567"
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                @error('phone') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <button 
                type="submit" 
                class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-lg shadow transition cursor-pointer">
                {{ __('Guardar Cambios') }}
            </button>
        </form>

    </div>

    <!-- Password Change Card -->
    <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-xs space-y-6">
        <div>
            <h2 class="font-semibold text-sm text-zinc-900">{{ __('Cambiar Contraseña') }}</h2>
            <p class="text-xs text-zinc-500 mt-0.5">{{ __('Asegúrate de usar una contraseña segura de al menos 8 caracteres.') }}</p>
        </div>

        @if(session('success_password'))
            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-medium">
                {{ session('success_password') }}
            </div>
        @endif

        <form wire:submit="updatePassword" class="space-y-4 max-w-lg">
            
            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Contraseña actual') }}</label>
                <input 
                    wire:model="current_password" 
                    type="password" 
                    required 
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                @error('current_password') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Nueva contraseña') }}</label>
                <input 
                    wire:model="new_password" 
                    type="password" 
                    required 
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
                @error('new_password') <p class="text-[11px] text-red-600 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold text-zinc-700">{{ __('Confirmar nueva contraseña') }}</label>
                <input 
                    wire:model="new_password_confirmation" 
                    type="password" 
                    required 
                    class="w-full px-3 py-2 text-xs border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
            </div>

            <button 
                type="submit" 
                class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-lg shadow transition cursor-pointer">
                {{ __('Actualizar Contraseña') }}
            </button>
        </form>

    </div>

</div>
