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
            'title' => __('Order received and started'),
            'subtitle' => __('Entered into our design system'),
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
                    'title' => $isFirst ? __('Design sent for your review') : __('New version sent for your review'),
                    'subtitle' => $isFirst ? __('First proposal ready for your review / approval') : __('Changes made and sent for your review'),
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
                    'subtitle' => __('Your assigned designer is applying the changes'),
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
                    'title' => __('Review follow-up'),
                    'subtitle' => __('We\'re reaching out to follow up on the last proposal we sent'),
                    'date' => $eventDate,
                    'color' => 'indigo',
                    'icon' => 'phone-call',
                    'is_completed' => true,
                ]);

                continue;
            }

            // On Hold
            if (str_contains($type, 'MOVED_TO_ON_HOLD') || (str_contains($type, 'CORE_STATUS') && str_contains($newVal, 'HOLD'))) {
                $reason = $event->metadata['reason'] ?? $order->pause_reason ?? ($order->blocking_reason?->label() ?: __('Waiting for information or confirmation'));
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
                    'subtitle' => __('The order was resumed and is under review'),
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
                    'title' => __('Design approved'),
                    'subtitle' => __('We received your approval! Your order is being prepared for production'),
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
                    'title' => __('In production / Printing'),
                    'subtitle' => __('Your order was sent to production for printing and finishing'),
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
                'title' => __('Design sent for your review'),
                'subtitle' => __('First proposal ready for your review / approval'),
                'date' => $order->last_sent_to_client_at,
                'color' => 'emerald',
                'icon' => 'send',
                'is_completed' => true,
            ]);
        }

        if (! $hasApproved && $order->approved) {
            $milestones->push([
                'type' => 'approved',
                'title' => __('Design approved'),
                'subtitle' => __('We received your approval! Your order is being prepared for production'),
                'date' => $order->updated_at,
                'color' => 'lime',
                'icon' => 'check-circle-2',
                'is_completed' => true,
            ]);
        }

        if (! $hasProduction && ($order->production_sent_at || $order->core_status === CoreStatus::EN_PRODUCCION)) {
            $milestones->push([
                'type' => 'in_production',
                'title' => __('In production / Printing'),
                'subtitle' => __('Your order was sent to production for printing and finishing'),
                'date' => $order->production_sent_at ?: $order->updated_at,
                'color' => 'pink',
                'icon' => 'printer',
                'is_completed' => true,
            ]);
        }

        if ($order->core_status === CoreStatus::ON_HOLD && ! $onHoldActive) {
            $reason = $order->pause_reason ?: ($order->blocking_reason?->label() ?: __('Waiting for specifications or confirmation'));
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
                'label' => __('On hold'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => $order->pause_reason ?: ($order->blocking_reason?->label() ?: __('Your order is on hold. Contact Customer Service for more information.')),
                'icon' => 'pause-circle',
            ];
        }

        if ($order->core_status === CoreStatus::EN_PRODUCCION) {
            $hex = $cc->getColorHex('production');
            $palette = $cc->derivePalette($hex);

            return [
                'label' => __('In Production'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => __('Your order is in the production phase. We\'ll contact you when it\'s ready.'),
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
                'description' => __('We\'ve sent you the proposal! We look forward to your comments or review.'),
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
                'label' => __('In design'),
                'badge_class' => 'border font-semibold',
                'badge_inline_style' => $palette['badge_style'],
                'dot_color' => '',
                'dot_style' => "background-color: {$hex};",
                'description' => __('Our design team is actively working on your order.'),
                'icon' => 'palette',
            ];
        }

        $hex = '#78716c';
        $palette = $cc->derivePalette($hex);

        return [
            'label' => __('Order received'),
            'badge_class' => 'border font-semibold',
            'badge_inline_style' => $palette['badge_style'],
            'dot_color' => '',
            'dot_style' => "background-color: {$hex};",
            'description' => __('We received your order! We\'ll contact you very soon with the next step.'),
            'icon' => 'clock',
        ];
    }
}
