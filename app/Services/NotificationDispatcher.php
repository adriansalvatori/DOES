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
     * Dispatch a notification for a specific event type.
     */
    public static function dispatch(
        string $eventType,
        string $title,
        string $message,
        ?Order $order = null,
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

        // Force order_overdue to be urgent
        if ($eventType === 'order_overdue') {
            $isUrgent = true;
        }

        // 2. Resolve recipients:
        // - Managers (Admins and Coordinators)
        // - Designer assigned to the order (if applicable)
        $query = User::where('active', true)
            ->where(function ($q) use ($order) {
                $q->whereIn('role', [UserRole::ADMIN->value, UserRole::COORDINATOR->value]);

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

        $notification = new SystemNotification(
            eventType: $eventType,
            title: $title,
            message: $message,
            orderId: $order?->id,
            taskName: $order?->task_name,
            actorId: $actorId,
            actorName: $actorName,
            isUrgent: $isUrgent
        );

        foreach ($recipients as $recipient) {
            // Prevent duplicates if same notification sent to same user recently
            $recipient->notify($notification);
        }
    }
}
