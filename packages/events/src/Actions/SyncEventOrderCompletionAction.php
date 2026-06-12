<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\Integration\CommerceIntegration;

final class SyncEventOrderCompletionAction
{
    public function handle(EventAttendance $attendance): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        if (! $attendance->event_registration_id) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner(Event::class, $attendance->event_id);

        $registration = EventRegistration::query()
            ->whereKey($attendance->event_registration_id)
            ->where('event_id', $attendance->event_id)
            ->first();

        if ($registration && $registration->external_order_id) {
            $registration->loadMissing('items');

            foreach ($registration->items as $item) {
                if ($item->external_order_item_id) {
                    app(FulfillEventOrderItemAction::class)->handle($item);
                }
            }
        }
    }
}
