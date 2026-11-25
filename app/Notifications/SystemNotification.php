<?php

namespace App\Notifications;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
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
        public ?string $designerName = null,
        public ?string $detailKey = null,
        public array $detailParams = []
    ) {}

    /**
     * Resolve the detail line in the reader's locale.
     *
     * New notifications store an English translation key plus parameters (status params hold raw
     * status values); rows created before that only have the pre-rendered detail_text, shown as-is.
     *
     * @param  array<string, mixed>  $data
     */
    public static function localizedDetail(array $data): ?string
    {
        $key = $data['detail_key'] ?? null;
        if (! is_string($key) || $key === '') {
            return $data['detail_text'] ?? null;
        }

        $params = [];
        foreach ((array) ($data['detail_params'] ?? []) as $name => $value) {
            if (is_array($value) && isset($value['core_status'])) {
                $value = CoreStatus::tryFrom((string) $value['core_status'])?->label() ?? (string) $value['core_status'];
            } elseif (is_array($value) && isset($value['substatus'])) {
                $value = Substatus::tryFrom((string) $value['substatus'])?->label() ?? (string) $value['substatus'];
            }
            $params[$name] = $value;
        }

        return __($key, $params);
    }

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
            'detail_key' => $this->detailKey,
            'detail_params' => $this->detailParams,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'is_urgent' => $this->isUrgent,
            'designer_id' => $this->designerId,
            'designer_name' => $this->designerName,
            // Fallback backward compatibility fields:
            'title' => $this->label.': '.($this->taskName ?? __('Order')),
            'message' => $this->detailText ?: ($this->companyName.' - '.$this->taskName),
        ];
    }
}
