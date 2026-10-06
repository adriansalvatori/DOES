<div class="flex-1 w-full flex flex-col space-y-6 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-2 max-w-5xl mx-auto pb-28">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4 shrink-0">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <x-lucide-bell-ring class="w-6 h-6 text-amber-500 shrink-0" />
                <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Configuración de Notificaciones (Admin)') }}</h1>
            </div>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Administra los 11 eventos globales de alertas. Los usuarios recibirán automáticamente todas las notificaciones activas sin opción a desactivarlas.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button 
                wire:click="enableAll" 
                type="button" 
                class="px-3.5 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold rounded-xl transition cursor-pointer shadow-xs flex items-center gap-1.5">
                <x-lucide-check-check class="w-4 h-4 text-emerald-400" />
                <span>{{ __('Activar Todas') }}</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2 animate-in fade-in">
            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $items = [
            'new_order' => [
                'title' => '1. New Order (Nueva Orden)',
                'desc' => 'Se dispara al crear una orden manualmente o importar desde Trello.',
                'badge' => 'Normal',
                'badge_color' => 'bg-blue-100 text-blue-800 border-blue-200',
                'icon' => 'lucide-file-plus-2',
            ],
            'order_overdue' => [
                'title' => '2. Orden Atrasada (OVERDUE)',
                'desc' => 'Se dispara al vencer la fecha límite. Se fijará en la parte superior del centro de notificaciones.',
                'badge' => 'URGENTE',
                'badge_color' => 'bg-red-100 text-red-800 border-red-300 font-bold',
                'icon' => 'lucide-alert-triangle',
            ],
            'status_changed' => [
                'title' => '3. Cambio de Core Status',
                'desc' => 'Se dispara cuando la orden cambia de columna o estatus principal.',
                'badge' => 'Normal',
                'badge_color' => 'bg-stone-100 text-stone-700 border-stone-200',
                'icon' => 'lucide-arrow-right-left',
            ],
            'order_blocked' => [
                'title' => '4. Orden Bloqueada',
                'desc' => 'Se dispara cuando una orden entra en estado de bloqueo o requiere información.',
                'badge' => 'Alta',
                'badge_color' => 'bg-amber-100 text-amber-800 border-amber-300',
                'icon' => 'lucide-lock',
            ],
            'order_unblocked' => [
                'title' => '5. Orden Desbloqueada',
                'desc' => 'Se dispara cuando se resuelve el bloqueo de una orden y retorna a flujo.',
                'badge' => 'Normal',
                'badge_color' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'icon' => 'lucide-unlock',
            ],
            'order_approved_alta' => [
                'title' => '6. Orden Aprobada, Pendiente ALTA',
                'desc' => 'Se dispara cuando el cliente/revisión aprueba la orden para pase a producción.',
                'badge' => 'Alta',
                'badge_color' => 'bg-purple-100 text-purple-800 border-purple-300',
                'icon' => 'lucide-check-circle',
            ],
            'new_attachments' => [
                'title' => '7. Nuevos Adjuntos en Orden',
                'desc' => 'Se dispara al subir imágenes, PDF o archivos adjuntos a la orden.',
                'badge' => 'Normal',
                'badge_color' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                'icon' => 'lucide-paperclip',
            ],
            'new_comment' => [
                'title' => '8. Nuevo Comentario en Orden',
                'desc' => 'Se dispara cuando un usuario publica una nota o comentario interno.',
                'badge' => 'Normal',
                'badge_color' => 'bg-sky-100 text-sky-800 border-sky-200',
                'icon' => 'lucide-message-square',
            ],
            'order_due_today' => [
                'title' => '9. Orden Se Vence HOY',
                'desc' => 'Se dispara automáticamente cuando la fecha límite pactada coincide con el día actual.',
                'badge' => 'Alta',
                'badge_color' => 'bg-orange-100 text-orange-800 border-orange-300',
                'icon' => 'lucide-clock-alert',
            ],
            'overdue_email_sent' => [
                'title' => '10. Enviar Correo de Atraso',
                'desc' => 'Aviso interno cuando la plataforma despacha el email de atraso al cliente.',
                'badge' => 'Normal',
                'badge_color' => 'bg-rose-100 text-rose-800 border-rose-200',
                'icon' => 'lucide-mail-warning',
            ],
            'welcome_email_sent' => [
                'title' => '11. Enviar Correo de Bienvenida',
                'desc' => 'Aviso interno al despacharse el email inicial/bienvenida de recepción de orden.',
                'badge' => 'Normal',
                'badge_color' => 'bg-teal-100 text-teal-800 border-teal-200',
                'icon' => 'lucide-mail-check',
            ],
        ];
    @endphp

    <div class="bg-white border border-[#e9e9e7] rounded-2xl p-6 shadow-2xs">
        <div class="divide-y divide-stone-100">
            @foreach($items as $key => $item)
                <div class="py-4 flex items-center justify-between gap-4 first:pt-0 last:pb-0 hover:bg-stone-50/50 transition px-2 rounded-xl">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="p-2 rounded-xl bg-stone-100 text-stone-700 shrink-0 mt-0.5">
                            <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-xs font-bold text-zinc-900 leading-snug">{{ $item['title'] }}</h3>
                                <span class="px-2 py-0.5 rounded-full text-[9px] border {{ $item['badge_color'] }}">
                                    {{ $item['badge'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-zinc-500 mt-0.5 leading-normal">{{ $item['desc'] }}</p>
                        </div>
                    </div>

                    <button 
                        wire:click="toggleSetting('{{ $key }}')" 
                        type="button" 
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ ($settings[$key] ?? true) ? 'bg-emerald-600' : 'bg-stone-300' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ ($settings[$key] ?? true) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

</div>
