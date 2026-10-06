<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventType,
        public string $title,
        public string $message,
        public ?int $orderId = null,
        public ?string $taskName = null,
        public ?int $actorId = null,
        public ?string $actorName = null,
        public bool $isUrgent = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->orderId,
            'task_name' => $this->taskName,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'is_urgent' => $this->isUrgent,
        ];
    }
}
