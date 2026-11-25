<?php

namespace App\Domain\Delivery\Exceptions;

use App\Domain\Delivery\Enums\DeliverableStatus;
use DomainException;

/**
 * A refused delivery operation. The message is already translated and safe to show in the UI.
 */
class WorkflowException extends DomainException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function refused(string $translationKey, array $replace = []): self
    {
        return new self(__($translationKey, $replace));
    }

    public static function invalidTransition(DeliverableStatus $from, DeliverableStatus $to): self
    {
        return self::refused('A deliverable cannot move from :from to :to.', ['from' => $from->label(), 'to' => $to->label()]);
    }

    public static function alreadyInStatus(DeliverableStatus $status): self
    {
        return self::refused('The deliverable is already in :status.', ['status' => $status->label()]);
    }

    public static function managerOnly(): self
    {
        return self::refused('Only a manager can make this move.');
    }

    public static function blocked(): self
    {
        return self::refused('This deliverable is blocked and cannot move forward until the block is resolved.');
    }

    public static function onHold(): self
    {
        return self::refused('This deliverable is on hold and cannot move forward until it is resumed.');
    }

    public static function closed(): self
    {
        return self::refused('This deliverable is closed.');
    }

    public static function mustSendWithBatch(): self
    {
        return self::refused('Send it to the client together with its batch from internal review.');
    }

    public static function needsReviewerApproval(): self
    {
        return self::refused('The reviewer must approve it before it goes to the client.');
    }

    public static function needsClientApproval(): self
    {
        return self::refused('Record the client approval with confirmed measurements and an approved estimate first.');
    }

    public static function gateTasksPending(DeliverableStatus $to): self
    {
        return self::refused('Complete the required tasks before moving to :status.', ['status' => $to->label()]);
    }

    public static function needsReceipt(): self
    {
        return self::refused('Log the receipt before marking it done, or close it with a reason.');
    }

    public static function needsCloseReason(): self
    {
        return self::refused('Close it with a reason instead of moving it to done.');
    }
}
