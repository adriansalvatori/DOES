<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventType,
        public string $label,
        public ?string $companyName = null,
        public ?string $taskName = null,
        public ?int $orderId = null,
        public ?string $detailText = null,
        public ?int $actorId = null,
        public ?string $actorName = null,
        public bool $isUrgent = false,
        public ?int $designerId = null,
        public ?string $designerName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => $this->eventType,
            'label' => $this->label,
            'company_name' => $this->companyName,
            'task_name' => $this->taskName,
            'order_id' => $this->orderId,
            'detail_text' => $this->detailText,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'is_urgent' => $this->isUrgent,
            'designer_id' => $this->designerId,
            'designer_name' => $this->designerName,
            // Fallback backward compatibility fields:
            'title' => $this->label.': '.($this->taskName ?? 'Orden'),
            'message' => $this->detailText ?: ($this->companyName.' - '.$this->taskName),
        ];
    }
}
