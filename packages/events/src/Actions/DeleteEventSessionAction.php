<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Events\EventSessionDeleted;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\OccurrenceStatus\Archived;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;

final class DeleteEventSessionAction
{
    public function handle(EventSession $session): void
    {
        EventWriteGuard::findOrFail($session->event_id);

        $session->status->transitionTo(Archived::class);
        $session->update(['archived_at' => CarbonImmutable::now()]);

        event(new EventSessionDeleted($session));
    }
}
