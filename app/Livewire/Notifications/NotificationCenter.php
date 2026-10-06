<?php

namespace App\Livewire\Notifications;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationCenter extends Component
{
    public bool $open = false;

    public function markAsRead(string $id)
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();

            if (isset($notification->data['order_id'])) {
                $this->dispatch('open-order-detail', orderId: $notification->data['order_id']);
                $this->open = false;
            }
        }
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
    }

    public function render()
    {
        $user = Auth::user();

        if (! $user) {
            return view('livewire.notifications.notification-center', [
                'notifications' => collect(),
                'unreadCount' => 0,
            ]);
        }

        $designerOrderIds = [];
        if ($user->isDesigner() && $user->designer) {
            $designerOrderIds = Order::where('designer_id', $user->designer->id)->pluck('id')->toArray();
        }

        // Fetch notifications
        $allNotifications = $user->notifications()
            ->get()
            ->filter(function ($n) use ($user, $designerOrderIds) {
                $data = $n->data ?? [];

                // Exclude if user is the actor
                if (isset($data['actor_id']) && (int) $data['actor_id'] === (int) $user->id) {
                    return false;
                }

                // If designer, only show notifications for assigned orders
                if ($user->isDesigner()) {
                    $orderId = $data['order_id'] ?? null;
                    if ($orderId && ! in_array((int) $orderId, $designerOrderIds, true)) {
                        return false;
                    }
                }

                return true;
            });

        // Sort: Urgent / Overdue first, then created_at DESC
        $sortedNotifications = $allNotifications->sort(function ($a, $b) {
            $aUrgent = ! empty($a->data['is_urgent']) || ($a->data['event_type'] ?? '') === 'order_overdue';
            $bUrgent = ! empty($b->data['is_urgent']) || ($b->data['event_type'] ?? '') === 'order_overdue';

            if ($aUrgent !== $bUrgent) {
                return $aUrgent ? -1 : 1;
            }

            return $b->created_at <=> $a->created_at;
        })->take(20);

        $unreadCount = $allNotifications->whereNull('read_at')->count();

        return view('livewire.notifications.notification-center', [
            'notifications' => $sortedNotifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
