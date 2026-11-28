<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventSessionDeleted;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Archived;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DeleteEventSessionAction
{
    public function handle(EventSession $session): void
    {
        EventWriteGuard::findOrFail($session->event_id);

        DB::transaction(function () use ($session): void {
            /** @var EventSession $session */
            $session = $session->newQuery()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            EventWriteGuard::findOrFail($session->event_id);

            if ($session->status instanceof Archived) {
                return;
            }

            $session->archived_at = CarbonImmutable::now();
            $session->status->transitionTo(Archived::class);

            DB::afterCommit(static fn () => event(new EventSessionDeleted(
                (string) $session->getKey(),
                (string) $session->event_id,
            )));
        });
    }
}
