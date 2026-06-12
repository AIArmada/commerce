<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;

final class FinalizeOccurredEventOrdersAction
{
    public function handle(EventOccurrence $occurrence): void
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $occurrence->event_id);

        $registrations = EventRegistration::query()
            ->where('event_occurrence_id', $occurrence->id)
            ->whereNotNull('external_order_id')
            ->get();

        foreach ($registrations as $registration) {
            app(FulfillEventOrderAction::class)->handle($registration);
        }
    }
}
