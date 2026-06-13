<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventPassDeliveryService;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Notifications\EventTicketNotification;
use Illuminate\Support\Facades\Notification;

final class DefaultEventPassDeliveryService implements EventPassDeliveryService
{
    public function deliver(EventPass $pass): void
    {
        if (! (bool) config('events.notifications.ticket.enabled', true)) {
            return;
        }

        $registration = $pass->registration;

        if ($registration === null) {
            return;
        }

        $notification = new EventTicketNotification($pass);
        $recipient = $registration->routeNotificationForMail($notification);

        if ($recipient === null) {
            return;
        }

        Notification::route('mail', $recipient)->notify($notification);
    }
}
