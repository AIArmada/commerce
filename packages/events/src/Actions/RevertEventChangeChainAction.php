<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeLog;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevertEventChangeChainAction
{
    use AsAction;

    public function handle(EventChangeLog $changeLog): void
    {
        $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $changeLog->event_id);

        OwnerContext::withOwner($event, function () use ($changeLog): void {
            $changeLog->update(['visibility' => 'internal']);

            $changeLog->loadMissing(['updates', 'notificationBatches']);

            foreach ($changeLog->updates as $update) {
                $update->update(['archived_at' => CarbonImmutable::now()]);
            }

            foreach ($changeLog->notificationBatches as $batch) {
                $batch->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
            }
        });
    }
}
