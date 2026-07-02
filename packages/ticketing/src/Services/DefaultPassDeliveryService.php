<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Notifications\TicketNotification;
use Illuminate\Support\Facades\Notification;

final class DefaultPassDeliveryService implements PassDeliveryServiceInterface
{
    public function deliver(Pass $pass): void
    {
        if (! config('ticketing.notifications.ticket.enabled', true)) {
            return;
        }

        $holder = $pass->holder;

        if ($holder === null || blank($holder->email)) {
            return;
        }

        Notification::route('mail', $holder->email)
            ->notify(new TicketNotification($pass));
    }
}
