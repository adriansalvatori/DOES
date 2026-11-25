<?php

namespace App\Livewire\Notifications;

use App\Application\Delivery\Queries\WorkflowLabels;
use App\Domain\Delivery\Support\Roles;
use App\Models\Order;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationCenter extends Component
{
    public const LIST_LIMIT = 50;

    public bool $open = false;

    public array $knownNotificationIds = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->knownNotificationIds = $user->notifications()
                ->latest()
                ->limit(self::LIST_LIMIT)
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
                $this->dispatch('open-deliverable', deliverableId: (int) $notification->data['order_id']);
                $this->open = false;
            }
        }
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        if ($user) {
            $this->visibleNotifications($user)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    /**
     * Notifications the user may see: not their own actions, and for a designer only the cards assigned to them.
     *
     * @return Builder<DatabaseNotification>
     */
    private function visibleNotifications(User $user): Builder
    {
        $isLeadOrManager = Roles::isManager($user) || ($user->isDesigner() && $user->designer?->is_lead);

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where(fn (Builder $query) => $query
                ->whereNull('data->actor_id')
                ->orWhere('data->actor_id', '!=', $user->getKey()))
            ->when($user->isDesigner() && ! $isLeadOrManager, fn (Builder $query) => $query
                ->where(fn (Builder $assigned) => $assigned
                    ->whereNull('data->order_id')
                    ->orWhereIn('data->order_id', Order::query()->select('id')->where(fn (Builder $mine) => $mine
                        ->where('assignee_id', $user->getKey())
                        ->when($user->designer, fn (Builder $legacy) => $legacy->orWhere('designer_id', $user->designer->id))))));
    }

    /**
     * The detail line in the reader's locale. A block reason stored as already rendered text is mapped back to its code.
     *
     * @param  array<string, mixed>  $data
     */
    private function localizedDetail(array $data): ?string
    {
        $labels = app(WorkflowLabels::class);
        $params = (array) ($data['detail_params'] ?? []);

        if (isset($params['block_reason']) && is_string($params['block_reason'])) {
            return $labels->label('block_reason', $params['block_reason']);
        }

        if (($data['event_type'] ?? '') === 'order_blocked' && is_string($params['reason'] ?? null)) {
            $code = $labels->codeForLabel('block_reason', $params['reason']);
            if ($code !== null) {
                return $labels->label('block_reason', $code);
            }
        }

        return SystemNotification::localizedDetail($data);
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

        $visible = $this->visibleNotifications($user);
        $unreadCount = (clone $visible)->whereNull('read_at')->count();

        $allNotifications = $visible
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get()
            ->each(function ($n): void {
                $data = $n->data ?? [];
                $data['detail_text'] = $this->localizedDetail($data);
                $n->data = $data;
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
        })->values();

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
                    $actorName ? __('by :name', ['name' => $actorName]) : null,
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
