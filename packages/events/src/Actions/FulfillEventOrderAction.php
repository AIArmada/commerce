<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventWriteGuard;

final class FulfillEventOrderAction
{
    public function handle(EventRegistration $registration): void
    {
        EventWriteGuard::findOrFail($registration->event_id);

        $registration->loadMissing('items');

        foreach ($registration->items as $item) {
            app(FulfillEventOrderItemAction::class)->handle($item);
        }
    }
}
