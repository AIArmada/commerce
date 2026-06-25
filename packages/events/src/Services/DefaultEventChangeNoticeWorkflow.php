<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventChangeNoticeRetracted;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Support\EventWriteGuard;

final class DefaultEventChangeNoticeWorkflow implements EventChangeNoticeWorkflow
{
    public function publishNotice(EventChangeLog $changeLog, array $options = []): void
    {
        $metadata = array_replace_recursive($changeLog->metadata ?? [], $options);
        $event = EventWriteGuard::findOrFail($changeLog->event_id);

        OwnerContext::withOwner($event->owner, function () use ($changeLog, $metadata): void {
            $changeLog->update([
                'metadata' => $metadata,
            ]);
        });

        event(new EventChangeNoticePublished($changeLog));
    }

    public function retractNotice(EventChangeLog $changeLog): void
    {
        EventWriteGuard::findOrFail($changeLog->event_id);

        event(new EventChangeNoticeRetracted($changeLog));
    }
}
