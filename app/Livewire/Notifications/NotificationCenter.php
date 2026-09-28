<?php

namespace App\Livewire\Notifications;

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

        $notifications = $user ? $user->notifications()->take(15)->get() : collect();
        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return view('livewire.notifications.notification-center', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
