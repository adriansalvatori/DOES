<div class="h-full w-full max-w-4xl flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10">
    
    <x-ui.page-header :title="__('Configuración de Notificaciones (Admin)')" :subtitle="__('Administra los 11 eventos globales de alertas. Los usuarios recibirán automáticamente todas las notificaciones activas sin opción a desactivarlas.')">
        <x-slot:actions>
            <flux:button wire:click="enableAll" type="button" variant="outline" size="sm" icon="check">{{ __('Activar Todas') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>
    <p class="text-meta text-ink-3 -mt-3 shrink-0">{{ __('Los cambios se guardan al instante.') }}</p>

    @if(session('success'))
        <div class="p-3 bg-ok-soft text-ok border border-line rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $items = [
            'new_order' => [
                'title' => '1. New Order (Nueva Orden)',
                'desc' => 'Se dispara al crear una orden manualmente o importar desde Trello.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_overdue' => [
                'title' => '2. Orden Atrasada (OVERDUE)',
                'desc' => 'Se dispara al vencer la fecha límite. Se fijará en la parte superior del centro de notificaciones.',
                'badge' => 'Urgente',
                'tone' => 'danger',
            ],
            'status_changed' => [
                'title' => '3. Cambio de Core Status',
                'desc' => 'Se dispara cuando la orden cambia de columna o estatus principal.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_blocked' => [
                'title' => '4. Orden Bloqueada',
                'desc' => 'Se dispara cuando una orden entra en estado de bloqueo o requiere información.',
                'badge' => 'Alta',
                'tone' => 'warn',
            ],
            'order_unblocked' => [
                'title' => '5. Orden Desbloqueada',
                'desc' => 'Se dispara cuando se resuelve el bloqueo de una orden y retorna a flujo.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_approved_alta' => [
                'title' => '6. Orden Aprobada, Pendiente ALTA',
                'desc' => 'Se dispara cuando el cliente/revisión aprueba la orden para pase a producción.',
                'badge' => 'Alta',
                'tone' => 'neutral',
            ],
            'new_attachments' => [
                'title' => '7. Nuevos Adjuntos en Orden',
                'desc' => 'Se dispara al subir imágenes, PDF o archivos adjuntos a la orden.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'new_comment' => [
                'title' => '8. Nuevo Comentario en Orden',
                'desc' => 'Se dispara cuando un usuario publica una nota o comentario interno.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'order_due_today' => [
                'title' => '9. Orden Se Vence HOY',
                'desc' => 'Se dispara automáticamente cuando la fecha límite pactada coincide con el día actual.',
                'badge' => 'Alta',
                'tone' => 'warn',
            ],
            'order_urgent' => [
                'title' => '10. Orden Marcada como URGENTE',
                'desc' => 'Se dispara cuando una orden es asignada o etiquetada como URGENTE. Se fijará arriba.',
                'badge' => 'Urgente',
                'tone' => 'danger',
            ],
            'overdue_email_sent' => [
                'title' => '11. Enviar Correo de Atraso',
                'desc' => 'Aviso interno cuando la plataforma despacha el email de atraso al cliente.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
            'welcome_email_sent' => [
                'title' => '12. Enviar Correo de Bienvenida',
                'desc' => 'Aviso interno al despacharse el email inicial/bienvenida de recepción de orden.',
                'badge' => 'Normal',
                'tone' => 'neutral',
            ],
        ];
    @endphp

    <div class="bg-surface border border-line rounded-card px-4">
        <div class="divide-y divide-line">
            @foreach($items as $key => $item)
                @php $on = (bool) ($settings[$key] ?? true); @endphp
                <div class="py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-body font-medium text-ink">{{ __($item['title']) }}</h3>
                            <x-ui.tag :tone="$item['tone']">{{ __($item['badge']) }}</x-ui.tag>
                        </div>
                        <p class="text-meta text-ink-3 mt-0.5">{{ __($item['desc']) }}</p>
                    </div>
                    <button
                        wire:click="toggleSetting('{{ $key }}')"
                        type="button"
                        role="switch"
                        aria-checked="{{ $on ? 'true' : 'false' }}"
                        aria-label="{{ __($item['title']) }}"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-gold {{ $on ? 'bg-ink' : 'bg-line-strong' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $on ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

</div>
