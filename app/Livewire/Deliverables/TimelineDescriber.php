<?php

namespace App\Livewire\Deliverables;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\DeliverableEvent;
use Illuminate\Support\Str;

/**
 * Turns an audit trail row into a translated one-line sentence.
 */
final class TimelineDescriber
{
    public static function describe(DeliverableEvent $event): string
    {
        $meta = $event->metadata ?? [];
        $previous = $event->previous_value;
        $new = $event->new_value;

        return match ($event->event_type) {
            'deliverable_created' => __('Created'),
            'deliverable_moved' => __('Moved from :from to :to', [
                'from' => self::status($previous),
                'to' => self::status($new),
            ]),
            'deliverable_blocked' => __('Blocked: :reason', ['reason' => self::enumLabel(BlockReason::class, $new)]),
            'block_resolved' => __('Block resolved: :reason', ['reason' => self::enumLabel(BlockReason::class, $previous)]),
            'deliverable_on_hold' => __('Put on hold: :reason', ['reason' => self::enumLabel(HoldReason::class, $new)]),
            'deliverable_resumed' => __('Resumed'),
            'task_added' => __('Task added: :title', ['title' => (string) $new]),
            'task_completed' => __('Task completed: :title', ['title' => (string) $new]),
            'task_reopened' => __('Task reopened: :title', ['title' => (string) $new]),
            'approved_by_reviewer' => __('Approved by the reviewer'),
            'client_approval_recorded' => __('Client approval recorded (:source)', ['source' => self::enumLabel(ApprovalSource::class, $new)]),
            'deliverable_planned' => __('Planned for :date', ['date' => (string) $new]),
            'deliverable_unplanned' => __('Taken off the plan'),
            'deliverable_duplicated' => __('Duplicated from deliverable #:id', ['id' => (string) $new]),
            'deliverable_trashed' => __('Moved to the trash'),
            'deliverable_restored' => __('Restored from the trash'),
            'receipt_logged' => __('Receipt logged (:method)', ['method' => self::enumLabel(FulfillmentMethod::class, $new)]),
            'carried_over' => __('Carried over to :date', ['date' => (string) $new]),
            'alert_resolved' => __('Alert resolved: :alert', ['alert' => self::enumLabel(AlertKind::class, $previous)]),
            'reviewer_changed' => __('Reviewer changed: :from → :to', ['from' => $previous ?? __('(empty)'), 'to' => $new ?? __('(empty)')]),
            'due_target_changed' => __('Due target changed: :from → :to', ['from' => $previous ?? __('(empty)'), 'to' => $new ?? __('(empty)')]),
            'comment_added' => __('Comment added'),
            'file_attached' => __('File attached: :name', ['name' => (string) $new]),
            'details_updated' => self::describeChanges($meta['changes'] ?? []),
            default => self::describeLegacy($event),
        };
    }

    /**
     * Events written before the rebuild keep their original uppercase codes.
     */
    private static function describeLegacy(DeliverableEvent $event): string
    {
        $meta = $event->metadata ?? [];

        return match ($event->event_type) {
            'ORDER_CREATED' => __('Created'),
            'AUTOMATIC_TASK_TRIGGERED' => __('Automatic task created: :title', ['title' => (string) ($meta['task_title'] ?? '')]),
            'SUBTASK_COMPLETED' => __('Task completed: :title', ['title' => (string) ($meta['task_title'] ?? '')]),
            'SUBTASK_SCHEDULED' => __('Task scheduled: :title', ['title' => (string) ($meta['task_title'] ?? '')]),
            'DUE_DATE_CHANGED' => __('Due date changed: :from → :to', ['from' => (string) $event->previous_value, 'to' => (string) $event->new_value]),
            'ORDER_SENT_TO_CLIENT' => __('Sent to the client'),
            'ORDER_APPROVED' => __('Approved'),
            'APPROVAL_RESET' => __('Approval reset'),
            'TRELLO_ATTACHMENT_ADDED' => __('Attachment added: :name', ['name' => (string) $event->new_value]),
            'TRELLO_COMMENT_ADDED' => __('Comment added'),
            'MOVED_TO_PRODUCTION' => __('Moved to production'),
            'MOVED_TO_ON_HOLD', 'AUTO_MOVED_ON_HOLD' => __('Put on hold'),
            'ORDER_BLOCKED' => __('Blocked'),
            'ORDER_UNBLOCKED' => __('Block resolved'),
            'ORDER_TRASHED' => __('Moved to the trash'),
            'ORDER_MERGED' => __('Merged with another order'),
            'DELAY_RESOLVED' => __('Delay resolved'),
            'SUBSTATUS_CHANGED', 'ORDER_UPDATED', 'INSTALLATION_CHANGED', 'DESIGNER_ASSIGNED', 'REVIEW_STATUS_CHANGED' => __('Details updated'),
            'CORE_STATUS_CHANGED', 'SCHEDULED_TODAY_PROMOTED', 'AUTO_RETURNED_AFTER_SUBTASK_COMPLETION', 'AUTO_REVERTED_TO_DESIGNER_LIST', 'REOPENED_TO_WORKING_TODAY', 'ROUTED_TO_DESIGNER_FUTURE_SUBTASKS', 'RESTORED_TO_ENVIADO_AL_CLIENTE' => __('Status changed'),
            default => __('Activity'),
        };
    }

    /**
     * The free text attached to an event (a note, a reason), if any.
     */
    public static function note(DeliverableEvent $event): ?string
    {
        $meta = $event->metadata ?? [];
        $isLegacy = strtoupper($event->event_type) === $event->event_type;
        $note = $isLegacy
            ? ($meta['comment'] ?? $meta['reason'] ?? $meta['description'] ?? null)
            : ($meta['note'] ?? null);

        return is_string($note) && trim($note) !== '' ? Str::limit(trim($note), 300) : null;
    }

    /**
     * @param  array<string, array{old?: mixed, new?: mixed}>  $changes
     */
    private static function describeChanges(array $changes): string
    {
        if ($changes === []) {
            return __('Details updated');
        }

        $parts = [];
        foreach ($changes as $field => $change) {
            $parts[] = __(':field: :from → :to', [
                'field' => self::fieldLabel((string) $field),
                'from' => self::changeValue((string) $field, $change['old'] ?? null),
                'to' => self::changeValue((string) $field, $change['new'] ?? null),
            ]);
        }

        return implode(' · ', $parts);
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => __('Name'),
            'size' => __('Size'),
            'material' => __('Material'),
            'quantity' => __('Quantity'),
            'files_url' => __('Files link'),
            'trello_card_url' => __('Trello card'),
            'phase_id' => __('Phase'),
            'flags' => __('Flags'),
            default => $field,
        };
    }

    private static function changeValue(string $field, mixed $value): string
    {
        if ($field === 'flags') {
            $labels = array_map(fn (string $flag): string => self::enumLabel(DeliverableFlag::class, $flag), is_array($value) ? $value : []);

            return $labels === [] ? __('(empty)') : implode(', ', $labels);
        }

        if ($value === null || $value === '') {
            return __('(empty)');
        }

        return is_scalar($value) ? (string) $value : __('(empty)');
    }

    private static function status(?string $value): string
    {
        $status = $value === null ? null : DeliverableStatus::tryFrom($value);

        return $status?->label() ?? (string) $value;
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private static function enumLabel(string $enum, ?string $value): string
    {
        $case = $value === null ? null : $enum::tryFrom($value);

        return $case !== null ? $case->label() : (string) $value;
    }
}
