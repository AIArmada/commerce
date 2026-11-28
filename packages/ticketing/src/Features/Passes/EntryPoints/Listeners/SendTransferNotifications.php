<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners;

use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Notifications\PassTransferredToNewHolderNotification;
use AIArmada\Ticketing\Notifications\PassTransferredToOldHolderNotification;
use Illuminate\Support\Facades\Notification;

final class SendTransferNotifications
{
    public function handle(PassTransferred $event): void
    {
        $transfer = $event->transfer;

        if ($transfer->bulk) {
            return;
        }

        if (! blank($transfer->previousHolder->email) && $transfer->previousHolder->id !== $transfer->newHolder->id) {
            Notification::route('mail', $transfer->previousHolder->email)
                ->notify(new PassTransferredToOldHolderNotification(
                    $transfer->pass,
                    $transfer->newHolder,
                    $transfer->reason,
                ));
        }

        if (! blank($transfer->newHolder->email)) {
            Notification::route('mail', $transfer->newHolder->email)
                ->notify(new PassTransferredToNewHolderNotification(
                    $transfer->pass,
                    $transfer->previousHolder,
                    $transfer->reason,
                ));
        }
    }
}
