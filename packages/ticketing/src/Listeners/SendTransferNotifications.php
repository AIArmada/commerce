<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Listeners;

use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use AIArmada\Ticketing\Notifications\PassTransferredToOldHolderNotification;
use Illuminate\Support\Facades\Notification;

final class SendTransferNotifications
{
    public function handle(PassTransferred $event): void
    {
        $previousHolder = $event->previousHolder;
        $newHolder = $event->newHolder;

        if (! blank($previousHolder->email)) {
            Notification::route('mail', $previousHolder->email)
                ->notify(new PassTransferredToOldHolderNotification($event->pass, $newHolder, $event->reason));
        }

        if (! blank($newHolder->email)) {
            Notification::route('mail', $newHolder->email)
                ->notify(new PassTransferredToNewHolderNotification($event->pass, $previousHolder, $event->reason));
        }
    }
}
