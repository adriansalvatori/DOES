<?php

namespace App\Livewire\Notifications;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationCenter extends Component
{
    public bool $open = false;

    public array $knownNotificationIds = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->knownNotificationIds = $user->notifications()
                ->take(30)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }
    }

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

        $isLeadOrManager = $user->isAdmin()
            || $user->isCoordinator()
            || ($user->isDesigner() && $user->designer?->is_lead);

        $designerOrderIds = [];
        if ($user->isDesigner() && $user->designer && ! $isLeadOrManager) {
            $designerOrderIds = Order::where('designer_id', $user->designer->id)->pluck('id')->toArray();
        }

        // Fetch notifications
        $allNotifications = $user->notifications()
            ->get()
            ->filter(function ($n) use ($user, $isLeadOrManager, $designerOrderIds) {
                $data = $n->data ?? [];

                // Exclude if user is the actor
                if (isset($data['actor_id']) && (int) $data['actor_id'] === (int) $user->id) {
                    return false;
                }

                // If standard designer (not Lead or Manager), only show notifications for assigned orders
                if (! $isLeadOrManager && $user->isDesigner()) {
                    $orderId = $data['order_id'] ?? null;
                    if ($orderId && ! in_array((int) $orderId, $designerOrderIds, true)) {
                        return false;
                    }
                }

                return true;
            });

        // Sort: Unread first, then Urgent / Overdue, then created_at DESC
        $sortedNotifications = $allNotifications->sort(function ($a, $b) {
            $aUnread = $a->unread();
            $bUnread = $b->unread();

            if ($aUnread !== $bUnread) {
                return $aUnread ? -1 : 1;
            }

            $aUrgent = ! empty($a->data['is_urgent']) || in_array($a->data['event_type'] ?? '', ['order_overdue', 'order_urgent'], true);
            $bUrgent = ! empty($b->data['is_urgent']) || in_array($b->data['event_type'] ?? '', ['order_overdue', 'order_urgent'], true);

            if ($aUrgent !== $bUrgent) {
                return $aUrgent ? -1 : 1;
            }

            return $b->created_at <=> $a->created_at;
        })->take(20);

        $unreadCount = $allNotifications->whereNull('read_at')->count();

        // Detect new incoming unread notifications not in knownNotificationIds
        $newIncoming = $allNotifications->filter(function ($n) {
            return $n->unread() && ! in_array((string) $n->id, $this->knownNotificationIds, true);
        });

        if ($newIncoming->isNotEmpty()) {
            foreach ($newIncoming->take(3) as $n) {
                $label = $n->data['label'] ?? match ($n->data['event_type'] ?? '') {
                    'new_order' => 'New Order',
                    'order_overdue' => 'Overdue',
                    'status_changed' => 'Status',
                    'order_blocked' => 'Blocked',
                    'order_unblocked' => 'Unblocked',
                    'order_approved_alta' => 'Approved',
                    'new_attachments' => 'New File',
                    'new_comment' => 'New Comment',
                    'order_due_today' => 'Due Today',
                    'order_urgent' => 'Urgent',
                    'welcome_email_sent', 'overdue_email_sent' => 'Email Sent',
                    default => 'Notification',
                };

                $companyName = $n->data['company_name'] ?? null;
                $taskName = $n->data['task_name'] ?? null;
                $detailText = $n->data['detail_text'] ?? null;
                $actorName = $n->data['actor_name'] ?? null;

                if ($companyName && $taskName && trim(strtolower($companyName)) === trim(strtolower($taskName))) {
                    $headlineParts = [$taskName];
                } else {
                    $headlineParts = array_filter([$companyName, $taskName]);
                }
                $headline = implode(' • ', $headlineParts) ?: config('app.name', 'Kudos DOES');

                $notifTitle = "[{$label}] {$headline}";

                $bodyParts = array_filter([
                    $detailText,
                    $actorName ? "por {$actorName}" : null,
                ]);
                $notifBody = implode(' • ', $bodyParts);
                if (empty($notifBody)) {
                    $notifBody = $taskName ?: $headline;
                }

                $this->dispatch(
                    'desktop-notification',
                    id: (string) $n->id,
                    title: $notifTitle,
                    body: $notifBody,
                    orderId: $n->data['order_id'] ?? null,
                    isUrgent: ! empty($n->data['is_urgent']) || in_array($n->data['event_type'] ?? '', ['order_overdue', 'order_urgent'], true),
                );
            }

            $newIds = $newIncoming->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->knownNotificationIds = array_slice(
                array_values(array_unique(array_merge($this->knownNotificationIds, $newIds))),
                -50
            );
        }

        return view('livewire.notifications.notification-center', [
            'notifications' => $sortedNotifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
