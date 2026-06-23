<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventWriteGuard;

final class FinalizeOccurredEventOrdersAction
{
    public function handle(EventOccurrence | EventSession $target): void
    {
        EventWriteGuard::findOrFail($target->event_id);

        $scopeColumn = $target instanceof EventOccurrence ? 'event_occurrence_id' : 'event_session_id';

        $registrations = EventRegistration::query()
            ->where($scopeColumn, $target->id)
            ->whereNotNull('external_order_id')
            ->get();

        foreach ($registrations as $registration) {
            app(FulfillEventOrderAction::class)->handle($registration);
        }
    }
}
