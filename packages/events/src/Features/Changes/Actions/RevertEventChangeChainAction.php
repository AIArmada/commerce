<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RevertEventChangeChainAction
{
    public function handle(string $changeLogId): void
    {
        $changeLog = EventChangeLog::query()->whereKey($changeLogId)->firstOrFail();
        $event = EventWriteGuard::findOrFail((string) $changeLog->event_id);

        OwnerContext::withOwner($event->getRelationValue('owner'), function () use ($changeLogId): void {
            DB::transaction(function () use ($changeLogId): void {
                $locked = EventChangeLog::query()->whereKey($changeLogId)->lockForUpdate()->firstOrFail();

                $locked->update(['visibility' => 'internal']);

                EventUpdate::query()
                    ->where('event_change_log_id', $changeLogId)
                    ->update(['archived_at' => CarbonImmutable::now()]);
            });
        });
    }
}
