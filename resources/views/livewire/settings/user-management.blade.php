<div class="p-6 max-w-6xl mx-auto space-y-6">
    
    <!-- Title & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Gestión de Usuarios y Roles') }}</h1>
        </div>

        <button 
            wire:click="openCreateModal" 
            type="button" 
            class="inline-flex items-center gap-2 px-3 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl shadow cursor-pointer transition">
            <x-lucide-user-plus class="w-4 h-4" />
            <span>{{ __('Crear Usuario') }}</span>
        </button>
    </div>

    {{-- CS WhatsApp Phone Configuration Card (Admin Only) --}}
    <div class="bg-gradient-to-r from-emerald-50/80 via-white to-emerald-50/40 border border-emerald-200/90 rounded-2xl p-4 shadow-xs space-y-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <x-lucide-message-circle class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-zinc-900 tracking-tight">{{ __('WhatsApp de Atención al Cliente (CS)') }}</h2>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold border border-emerald-200">{{ __('Admin Only') }}</span>
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        {{ __('Este número se utiliza en el Portal del Cliente para las consultas dirigidas al equipo de Customer Service.') }}
                    </p>
                </div>
            </div>

            <form wire:submit="saveCsWhatsapp" class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-56">
                    <x-lucide-phone class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-2.5" />
                    <input 
                        type="text" 
                        wire:model="cs_whatsapp_phone" 
                        placeholder="+16783580594"
                        class="w-full pl-8 pr-3 py-1.5 text-xs font-mono border border-stone-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
                <button 
                    type="submit" 
                    class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-2xs transition cursor-pointer shrink-0 flex items-center gap-1.5"
                >
                    <x-lucide-save class="w-3.5 h-3.5" />
                    <span>{{ __('Guardar') }}</span>
                </button>
            </form>
        </div>

        @if(session('success_cs'))
            <div class="p-2.5 bg-emerald-100/70 border border-emerald-300 text-emerald-900 rounded-xl text-xs font-medium flex items-center gap-2">
                <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-700 shrink-0" />
                <span>{{ session('success_cs') }}</span>
            </div>
        @endif
        @error('cs_whatsapp_phone')
            <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
        @enderror
    </div>

    @if(session('success_user'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium">
            {{ session('success_user') }}
        </div>
    @endif

    <!-- Search & Filter Bar -->
    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-xs">
            <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-2.5" />
            <input 
                wire:model.live.debounce.300ms="search" 
                type="text" 
                placeholder="{{ __('Buscar por nombre o correo...') }}"
                class="w-full pl-9 pr-3 py-1.5 text-xs border border-stone-300 rounded-xl bg-white focus:ring-2 focus:ring-stone-900 focus:border-stone-900" />
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-stone-200/80 rounded-2xl shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs text-zinc-600">
            <thead class="bg-stone-50 border-b border-stone-200 text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3 px-4">{{ __('Usuario') }}</th>
                    <th class="py-3 px-4">{{ __('Rol') }}</th>
                    <th class="py-3 px-4">{{ __('Diseñador Vinculado') }}</th>
                    <th class="py-3 px-4">{{ __('Estado') }}</th>
                    <th class="py-3 px-4 text-right">{{ __('Acciones') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($users as $u)
                    <tr class="hover:bg-stone-50/60 transition">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-stone-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                    {{ $u->initials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-zinc-900 truncate">{{ $u->name }}</p>
                                    <div class="flex items-center gap-2 text-[11px] text-zinc-400 truncate flex-wrap">
                                        <span>{{ $u->email }}</span>
                                        @if($u->phone || $u->designer?->phone)
                                            <span class="inline-flex items-center gap-0.5 text-zinc-600 font-mono">
                                                <x-lucide-phone class="w-2.5 h-2.5 text-emerald-600" />
                                                <span>{{ $u->phone ?: $u->designer?->phone }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $u->role->badgeStyle() }}">
                                {{ $u->role->label() }}
                            </span>
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px]">
                            @if($u->designer)
                                <span class="px-2 py-0.5 rounded-md bg-stone-100 text-stone-700 font-medium">
                                    🎨 {{ $u->designer->name }}
                                </span>
                            @else
                                <span class="text-zinc-400 italic">{{ __('Sin vincular') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <button 
                                wire:click="toggleActive({{ $u->id }})" 
                                type="button"
                                class="inline-flex items-center gap-1.5 text-[11px] font-medium cursor-pointer">
                                @if($u->active)
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="text-emerald-700">{{ __('Activo') }}</span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                    <span class="text-rose-600">{{ __('Inactivo') }}</span>
                                @endif
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button 
                                wire:click="openEditModal({{ $u->id }})" 
                                type="button" 
                                class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-stone-100 transition cursor-pointer"
                                title="{{ __('Editar') }}">
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-zinc-400">
                            {{ __('No se encontraron usuarios.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-3 border-t border-stone-100">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Create / Edit User Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-stone-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-stone-200 w-full max-w-md p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-sm text-zinc-900">
                        {{ $editingUserId ? __('Editar Usuario') : __('Crear Nuevo Usuario') }}
                    </h3>
                    <button @click="$wire.showModal = false" class="text-zinc-400 hover:text-zinc-700">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit="saveUser" autocomplete="off" class="space-y-4 text-xs">
                    
                    <div class="space-y-1">
                        <label class="block font-semibold text-zinc-700">{{ __('Nombre') }}</label>
                        <input wire:model="name" type="text" required class="w-full px-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900" />
                        @error('name') <p class="text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-semibold text-zinc-700">{{ __('Correo electrónico') }}</label>
                        <input wire:model="email" type="email" required class="w-full px-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900" />
                        @error('email') <p class="text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-semibold text-zinc-700 flex items-center justify-between">
                            <span>{{ __('Teléfono / WhatsApp de Contacto') }}</span>
                            <span class="text-[10px] text-zinc-400 font-normal">{{ __('Para "Chatear con tu Diseñador"') }}</span>
                        </label>
                        <div class="relative">
                            <x-lucide-phone class="w-4 h-4 text-zinc-400 absolute left-3 top-2.5" />
                            <input wire:model="phone" type="text" placeholder="+1 (678) 358-0594" class="w-full pl-9 pr-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900 font-mono text-xs" />
                        </div>
                        @error('phone') <p class="text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-semibold text-zinc-700">{{ __('Rol de usuario') }}</label>
                        <select wire:model="role" class="w-full px-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900">
                            @foreach($roles as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="text-red-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-semibold text-zinc-700">{{ __('Vincular Diseñador (Opcional)') }}</label>
                        <select wire:model="designer_id" class="w-full px-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900">
                            <option value="">-- {{ __('Ninguno') }} --</option>
                            @foreach($designers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($editingUserId)
                        <div class="pt-2 border-t border-stone-100">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model.live="changePassword" type="checkbox" class="rounded border-stone-300 text-stone-900 focus:ring-stone-900" />
                                <span class="font-medium text-zinc-700 text-xs">{{ __('Cambiar contraseña de este usuario') }}</span>
                            </label>
                        </div>
                    @endif

                    @if(! $editingUserId || $changePassword)
                        <div class="space-y-1">
                            <label class="block font-semibold text-zinc-700">
                                {{ $editingUserId ? __('Nueva Contraseña (mínimo 8 caracteres)') : __('Contraseña (mínimo 8 caracteres)') }}
                            </label>
                            <input wire:model="password" type="password" autocomplete="new-password" required class="w-full px-3 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-stone-900" />
                            @error('password') <p class="text-red-600 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="flex items-center gap-2 pt-1">
                        <input wire:model="active" type="checkbox" id="userActive" class="rounded border-stone-300 text-stone-900 focus:ring-stone-900" />
                        <label for="userActive" class="font-medium text-zinc-700 cursor-pointer">{{ __('Usuario Activo') }}</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-stone-100">
                        <button type="button" @click="$wire.showModal = false" class="px-3 py-1.5 border border-stone-300 rounded-lg text-zinc-600 hover:bg-stone-50 font-medium">
                            {{ __('Cancelar') }}
                        </button>
                        <button type="submit" class="px-4 py-1.5 bg-stone-900 text-white rounded-lg font-semibold hover:bg-stone-800 shadow">
                            {{ __('Guardar') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

</div>
