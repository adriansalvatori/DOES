<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;

/**
 * Marks an alert as handled (the email was sent, the client was called).
 */
class ResolveAlert
{
    public function __construct(private DeliveryLog $log) {}

    public function __invoke(Alert $alert, ?User $actor = null): Alert
    {
        if ($alert->resolved_at !== null) {
            return $alert;
        }

        $alert->update(['resolved_at' => now()]);

        if ($alert->deliverable_id !== null && ($deliverable = Deliverable::withTrashed()->find($alert->deliverable_id)) !== null) {
            $this->log->record($deliverable, 'alert_resolved', $actor, $alert->kind->value);
        }

        return $alert;
    }
}
