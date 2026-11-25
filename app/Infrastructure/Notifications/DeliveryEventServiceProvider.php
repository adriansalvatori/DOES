<?php

namespace App\Infrastructure\Notifications;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the delivery domain events to their queued listeners.
 */
class DeliveryEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotifyOnDeliveryEvent::events(), NotifyOnDeliveryEvent::class);
    }
}
