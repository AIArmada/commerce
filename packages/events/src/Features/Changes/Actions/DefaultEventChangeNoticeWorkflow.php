<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventChangeNoticeRetracted;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Platform\EventWriteGuard;
use Illuminate\Support\Facades\DB;

final class DefaultEventChangeNoticeWorkflow implements EventChangeNoticeWorkflow
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function publishNotice(string $changeLogId, array $options = []): void
    {
        $changeLog = EventChangeLog::query()->whereKey($changeLogId)->firstOrFail();
        $event = EventWriteGuard::findOrFail((string) $changeLog->event_id);
        $updateId = null;

        OwnerContext::withOwner($event->getRelationValue('owner'), function () use ($changeLogId, $options, &$updateId): void {
            DB::transaction(function () use ($changeLogId, $options, &$updateId): void {
                $locked = EventChangeLog::query()->whereKey($changeLogId)->lockForUpdate()->firstOrFail();

                $metadata = is_array($locked->metadata) ? $locked->metadata : [];

                $locked->update(['metadata' => array_replace_recursive($metadata, $options)]);

                $locked->loadMissing('eventUpdate');

                $update = $locked->getRelationValue('eventUpdate');

                if ($update instanceof EventUpdate) {
                    $updateId = (string) $update->getKey();
                }
            });
        });

        event(new EventChangeNoticePublished($changeLogId, (string) $changeLog->event_id, $updateId));
    }

    public function retractNotice(string $changeLogId): void
    {
        $changeLog = EventChangeLog::query()->whereKey($changeLogId)->firstOrFail();

        EventWriteGuard::findOrFail((string) $changeLog->event_id);

        event(new EventChangeNoticeRetracted($changeLogId, (string) $changeLog->event_id));
    }
}
