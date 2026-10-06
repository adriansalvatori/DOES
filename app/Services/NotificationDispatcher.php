<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Auth;

class NotificationDispatcher
{
    /**
     * Dispatch a structured notification for a specific event type.
     */
    public static function dispatch(
        string $eventType,
        string $label,
        ?Order $order = null,
        ?string $detailText = null,
        ?User $actor = null,
        bool $isUrgent = false
    ): void {
        // 1. Check if event type is enabled in Admin settings
        $defaults = [
            'new_order' => true,
            'order_overdue' => true,
            'status_changed' => true,
            'order_blocked' => true,
            'order_unblocked' => true,
            'order_approved_alta' => true,
            'new_attachments' => true,
            'new_comment' => true,
            'order_due_today' => true,
            'order_urgent' => true,
            'overdue_email_sent' => true,
            'welcome_email_sent' => true,
        ];

        $adminSettings = Setting::get('admin_notification_settings', []);
        $settings = array_merge($defaults, is_array($adminSettings) ? $adminSettings : json_decode($adminSettings, true) ?? []);

        if (array_key_exists($eventType, $settings) && ! $settings[$eventType]) {
            return; // Disabled by admin
        }

        $actor = $actor ?? Auth::user();
        $actorId = $actor?->id;
        $actorName = $actor?->name;

        // Force order_overdue and order_urgent to be urgent
        if ($eventType === 'order_overdue' || $eventType === 'order_urgent') {
            $isUrgent = true;
        }

        // 2. Resolve recipients:
        // - Managers (Admins and Coordinators)
        // - Lead Designers (is_lead = true)
        // - Designer assigned to the order (if applicable)
        $query = User::where('active', true)
            ->where(function ($q) use ($order) {
                $q->whereIn('role', [UserRole::ADMIN->value, UserRole::COORDINATOR->value])
                    ->orWhereHas('designer', function ($dq) {
                        $dq->where('is_lead', true);
                    });

                if ($order && $order->designer_id) {
                    $q->orWhereHas('designer', function ($dq) use ($order) {
                        $dq->where('id', $order->designer_id);
                    });
                }
            });

        // 3. Exclude the actor (actor never receives a notification for their own action)
        if ($actorId) {
            $query->where('id', '!=', $actorId);
        }

        $recipients = $query->get();

        $companyName = $order?->company_name ?? __('General');
        $taskName = $order?->task_name ?? __('Orden');
        $designerId = $order?->designer_id;
        $designerName = $order?->designer?->name;

        $notification = new SystemNotification(
            eventType: $eventType,
            label: $label,
            companyName: $companyName,
            taskName: $taskName,
            orderId: $order?->id,
            detailText: $detailText,
            actorId: $actorId,
            actorName: $actorName,
            isUrgent: $isUrgent,
            designerId: $designerId,
            designerName: $designerName
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
