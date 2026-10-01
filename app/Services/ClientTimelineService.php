<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientTimelineService
{
    /**
     * Get a simplified, client-facing compact timeline for an order.
     *
     * @return Collection<int, array{
     *     type: string,
     *     title: string,
     *     subtitle: ?string,
     *     date: Carbon,
     *     date_formatted: string,
     *     time_formatted: ?string,
     *     color: string,
     *     icon: string,
     *     is_completed: bool,
     *     is_current: bool
     * }>
     */
    public function getClientTimeline(Order $order): Collection
    {
        $events = $order->events()->reorder('created_at', 'asc')->get();
        $milestones = collect();

        // 1. Initial creation milestone
        $firstEvent = $order->events()->where('event_type', 'like', '%CREATED%')->oldest('created_at')->first();
        $createdAt = $firstEvent?->created_at
            ?? $order->trello_created_at
            ?? $order->manual_creation_date
            ?? $order->created_at;

        if ($order->start_date && $order->start_date->lt($createdAt)) {
            $createdAt = $order->start_date->startOfDay();
        }

        $milestones->push([
            'type' => 'created',
            'title' => __('Orden recibida e iniciada'),
            'subtitle' => __('Ingresado en nuestro sistema de diseño'),
            'date' => Carbon::parse($createdAt),
            'color' => 'yellow',
            'icon' => 'folder-plus',
            'is_completed' => true,
        ]);

        $sentCount = 0;
        $hasApproved = false;
        $hasProduction = false;
        $hasSent = false;
        $onHoldActive = false;

        $lastSentTimestamp = null;
        $lastFeedbackTimestamp = null;
        $lastFollowUpTimestamp = null;

        foreach ($events as $event) {
            $type = strtoupper((string) $event->event_type);
            $newVal = strtoupper((string) $event->new_value);
            $eventDate = $event->created_at;

            // Sent to client (deduplicate if fired multiple times within 2 hours)
            if (str_contains($type, 'ORDER_SENT_TO_CLIENT') || (str_contains($type, 'CORE_STATUS') && str_contains($newVal, 'CLIENTE'))) {
                if ($lastSentTimestamp && abs($eventDate->timestamp - $lastSentTimestamp->timestamp) < 7200) {
                    continue;
                }
                $lastSentTimestamp = $eventDate;
                $sentCount++;
                $hasSent = true;
                $isFirst = ($sentCount === 1);

                $milestones->push([
                    'type' => 'sent_to_client',
                    'title' => $isFirst ? __('Diseño enviado para tu revisión') : __('Nueva versión enviada para tu revisión'),
                    'subtitle' => $isFirst ? __('Primera propuesta lista para tu revisión / aprobación') : __('Ajustes realizados y enviados para tu revisión'),
                    'date' => $eventDate,
                    'color' => 'emerald',
                    'icon' => 'send',
                    'is_completed' => true,
                ]);

                continue;
            }

            // Client feedback / changes received (deduplicate within 12 hours)
            if (str_contains($type, 'SUBTASK') && (
                str_contains(strtoupper((string) $event->new_value), 'AJUSTES') ||
                str_contains(strtoupper((string) $event->new_value), 'CAMBIO') ||
                str_contains(strtoupper((string) $event->new_value), 'CORRECCION')
            )) {
                if ($lastFeedbackTimestamp && abs($eventDate->timestamp - $lastFeedbackTimestamp->timestamp) < 43200) {
                    continue;
                }
                $lastFeedbackTimestamp = $eventDate;

                $milestones->push([
                    'type' => 'changes_received',
                    'title' => __('Cambios / comentarios recibidos'),
                    'subtitle' => __('Tu diseñador asignado está aplicando las modificaciones'),
                    'date' => $eventDate,
                    'color' => 'purple',
                    'icon' => 'message-square',
                    'is_completed' => true,
                ]);

                continue;
            }

            // Client Follow Up (deduplicate within 4 hours)
            if (str_contains($type, 'SUBTASK') && (
                str_contains(strtoupper((string) $event->new_value), 'FOLLOW UP') ||
                str_contains(strtoupper((string) $event->new_value), 'SEGUIMIENTO')
            )) {
                if ($lastFollowUpTimestamp && abs($eventDate->timestamp - $lastFollowUpTimestamp->timestamp) < 14400) {
                    continue;
                }
                $lastFollowUpTimestamp = $eventDate;

                $milestones->push([
                    'type' => 'follow_up',
                    'title' => __('Seguimiento de revisión'),
                    'subtitle' => __('Te contactamos para hacer seguimiento a la última propuesta enviada'),
                    'date' => $eventDate,
                    'color' => 'indigo',
                    'icon' => 'phone-call',
                    'is_completed' => true,
                ]);

                continue;
            }

            // On Hold
            if (str_contains($type, 'MOVED_TO_ON_HOLD') || (str_contains($type, 'CORE_STATUS') && str_contains($newVal, 'HOLD'))) {
                $reason = $event->metadata['reason'] ?? $order->pause_reason ?? ($order->blocking_reason?->label() ?: __('En espera de información o confirmación'));
                $milestones->push([
                    'type' => 'on_hold',
                    'title' => __('En pausa (On Hold)'),
                    'subtitle' => __('Motivo: :reason', ['reason' => $reason]),
                    'date' => $eventDate,
                    'color' => 'amber',
                    'icon' => 'pause-circle',
                    'is_completed' => true,
                ]);
                $onHoldActive = true;

                continue;
            }

            // Unblocked
            if (str_contains($type, 'UNBLOCKED') || str_contains($type, 'DESBLOQUEA')) {
                $milestones->push([
                    'type' => 'unblocked',
                    'title' => __('Pausa resuelta / Trabajo reanudado'),
                    'subtitle' => __('La orden fue retomada y está en proceso de revisión'),
                    'date' => $eventDate,
                    'color' => 'emerald',
                    'icon' => 'play-circle',
                    'is_completed' => true,
                ]);
                $onHoldActive = false;

                continue;
            }

            // Approved
            if (str_contains($type, 'APPROVED') || str_contains($type, 'APPROVAL_SUBMITTED') || (is_string($event->new_value) && str_contains(strtolower($event->new_value), 'estimate: yes'))) {
                $milestones->push([
                    'type' => 'approved',
                    'title' => __('Diseño aprobado ✓'),
                    'subtitle' => __('¡Recibimos tu aprobación! Tu orden está siendo preparada para producción'),
                    'date' => $eventDate,
                    'color' => 'lime',
                    'icon' => 'check-circle-2',
                    'is_completed' => true,
                ]);
                $hasApproved = true;

                continue;
            }

            // In Production
            if (str_contains($type, 'CORE_STATUS') && str_contains($newVal, 'PRODUCCI')) {
                $milestones->push([
                    'type' => 'in_production',
                    'title' => __('En producción / Impresión'),
                    'subtitle' => __('Tu orden fue enviada a producción para impresión y acabado'),
                    'date' => $eventDate,
                    'color' => 'pink',
                    'icon' => 'printer',
                    'is_completed' => true,
                ]);
                $hasProduction = true;

                continue;
            }
        }

        // Synthesize fallback milestones if state exists but wasn't captured in events log:
        if (! $hasSent && $order->last_sent_to_client_at) {
            $milestones->push([
                'type' => 'sent_to_client',
                'title' => __('Diseño enviado para tu revisión'),
                'subtitle' => __('Primera propuesta lista para tu revisión / aprobación'),
                'date' => $order->last_sent_to_client_at,
                'color' => 'emerald',
                'icon' => 'send',
                'is_completed' => true,
            ]);
        }

        if (! $hasApproved && $order->approved) {
            $milestones->push([
                'type' => 'approved',
                'title' => __('Diseño aprobado ✓'),
                'subtitle' => __('¡Recibimos tu aprobación! Tu orden está siendo preparada para producción'),
                'date' => $order->updated_at,
                'color' => 'lime',
                'icon' => 'check-circle-2',
                'is_completed' => true,
            ]);
        }

        if (! $hasProduction && ($order->production_sent_at || $order->core_status === CoreStatus::EN_PRODUCCION)) {
            $milestones->push([
                'type' => 'in_production',
                'title' => __('En producción / Impresión'),
                'subtitle' => __('Tu orden fue enviada a producción para impresión y acabado'),
                'date' => $order->production_sent_at ?: $order->updated_at,
                'color' => 'pink',
                'icon' => 'printer',
                'is_completed' => true,
            ]);
        }

        if ($order->core_status === CoreStatus::ON_HOLD && ! $onHoldActive) {
            $reason = $order->pause_reason ?: ($order->blocking_reason?->label() ?: __('En espera de especificaciones o confirmación'));
            $milestones->push([
                'type' => 'on_hold',
                'title' => __('En pausa (On Hold)'),
                'subtitle' => __('Motivo: :reason', ['reason' => $reason]),
                'date' => $order->updated_at,
                'color' => 'amber',
                'icon' => 'pause-circle',
                'is_completed' => true,
            ]);
        }

        // Sort chronologically ascending
        $sorted = $milestones->sortBy(fn ($m) => $m['date']->timestamp)->values();

        // Mark current active milestone (the last completed one)
        $lastIndex = $sorted->count() - 1;
        $cc = app(ColorCodingService::class);

        return $sorted->map(function ($m, $index) use ($lastIndex, $cc) {
            $date = $m['date'];
            $m['date_formatted'] = $date->translatedFormat('d M, Y');
            $m['time_formatted'] = $date->format('g:i A');
            $m['is_current'] = ($index === $lastIndex);

            $hex = match ($m['type'] ?? '') {
                'in_production' => $cc->getColorHex('production'),
                'changes_received' => $cc->getColorHex('camila'),
                'sent_to_client' => $cc->getColorHex('client'),
                'approved' => $cc->getColorHex('todo_today'),
                'on_hold' => $cc->getColorHex('blocked'),
                default => match ($m['color'] ?? '') {
                    'pink' => $cc->getColorHex('production'),
                    'purple' => $cc->getColorHex('camila'),
                    'emerald', 'lime' => $cc->getColorHex('todo_today'),
                    'amber', 'yellow' => $cc->getColorHex('blocked'),
                    default => $cc->getColorHex('client'),
                },
            };

            $m['hex_color'] = $hex;
            $m['dot_style'] = "background-color: {$hex}; box-shadow: 0 0 0 4px {$hex}25;";
            $m['line_style'] = "background-color: {$hex}50;";

            return $m;
        });
    }

    /**
     * Get a high-level customer-friendly status description and styles.
     *
     * @return array{
     *     label: string,
     *     badge_class: string,
     *     badge_inline_style: string,
     *     dot_color: string,
     *     dot_style: string,
     *     description: string,
     *     icon: string
     * }
     */
    public function getCustomerStatus(Order $order): array
    {
        $cc = app(ColorCodingService::class);

        if ($order->core_status === CoreStatus::ON_HOLD) {
            $hex = $cc->getColorHex('blocked');
            $palette = $cc->derivePalette($hex);

            return [
                'label' => __('En Pausa'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => $order->pause_reason ?: ($order->blocking_reason?->label() ?: __('Tu orden se encuentra pausada. Contacta a Customer Service para más información.')),
                'icon' => 'pause-circle',
            ];
        }

        if ($order->core_status === CoreStatus::EN_PRODUCCION) {
            $hex = $cc->getColorHex('production');
            $palette = $cc->derivePalette($hex);

            return [
                'label' => __('En Producción'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => __('Tu orden se encuentra en etapa de producción. Te contactaremos cuando esté lista.'),
                'icon' => 'printer',
            ];
        }

        if ($order->core_status === CoreStatus::ENVIADO_AL_CLIENTE) {
            $hex = $cc->getColorHex('client');
            $palette = $cc->derivePalette($hex);

            return [
                'label' => __('Esperando tu respuesta'),
                'badge_class' => 'border font-semibold ring-2 ring-blue-500/20',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => __('¡Te hemos enviado la propuesta! Esperamos tus comentarios o revisión.'),
                'icon' => 'sparkles',
            ];
        }

        if (in_array($order->core_status, [
            CoreStatus::TO_DO_TODAY,
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED,
            CoreStatus::ENVIADO_A_CAMILA,
        ], true)) {
            $hex = $cc->getColorHex('todo_today');
            $palette = $cc->derivePalette($hex);

            return [
                'label' => __('En Diseño'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => __('Nuestro equipo de diseño está trabajando activamente en tu orden.'),
                'icon' => 'palette',
            ];
        }

        $hex = '#78716c';
        $palette = $cc->derivePalette($hex);

        return [
            'label' => __('Orden Recibida'),
            'badge_class' => 'border font-semibold',
            'badge_inline_style' => $palette['badge_style'],
            'dot_color' => '',
            'dot_style' => "background-color: {$hex};",
            'description' => __('¡Recibimos tu orden! Muy pronto te contactaremos para el siguiente paso.'),
            'icon' => 'clock',
        ];
    }
}
