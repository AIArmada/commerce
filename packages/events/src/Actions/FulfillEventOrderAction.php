<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;

final class FulfillEventOrderAction
{
    public function handle(EventRegistration $registration): void
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $registration->event_id);

        $registration->loadMissing('items');

        foreach ($registration->items as $item) {
            app(FulfillEventOrderItemAction::class)->handle($item);
        }
    }
}
