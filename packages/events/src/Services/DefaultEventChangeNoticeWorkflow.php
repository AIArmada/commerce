<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventChangeNoticeRetracted;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeLog;

final class DefaultEventChangeNoticeWorkflow implements EventChangeNoticeWorkflow
{
    public function publishNotice(EventChangeLog $changeLog, array $options = []): void
    {
        $metadata = array_replace_recursive($changeLog->metadata ?? [], $options);

        if ($changeLog->event_id !== null) {
            $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $changeLog->event_id);

            OwnerContext::withOwner($event, function () use ($changeLog, $metadata): void {
                $changeLog->update([
                    'metadata' => $metadata,
                ]);
            });
        } else {
            $changeLog->update([
                'metadata' => $metadata,
            ]);
        }

        event(new EventChangeNoticePublished($changeLog));
    }

    public function retractNotice(EventChangeLog $changeLog): void
    {
        event(new EventChangeNoticeRetracted($changeLog));
    }
}
