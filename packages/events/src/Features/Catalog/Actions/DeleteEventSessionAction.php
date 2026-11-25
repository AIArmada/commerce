<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventSessionDeleted;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Archived;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;

final class DeleteEventSessionAction
{
    public function handle(EventSession $session): void
    {
        EventWriteGuard::findOrFail($session->event_id);

        $session->status->transitionTo(Archived::class);
        $session->update(['archived_at' => CarbonImmutable::now()]);

        event(new EventSessionDeleted(
            (string) $session->getKey(),
            (string) $session->event_id,
        ));
    }
}
