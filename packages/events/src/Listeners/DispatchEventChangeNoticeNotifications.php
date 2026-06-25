<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Support\EventWriteGuard;

final class DispatchEventChangeNoticeNotifications
{
    public function __construct(
        private readonly EventChangeNoticeNotificationDispatcher $dispatcher,
    ) {}

    public function handle(EventChangeNoticePublished $event): void
    {
        $changeLog = $event->changeLog;
        $changeLog->loadMissing('eventUpdate');

        $resolvedEvent = EventWriteGuard::findOrFail($changeLog->event_id);

        $batch = OwnerContext::withOwner($resolvedEvent->owner, function () use ($changeLog): EventNotificationBatch {
            return EventNotificationBatch::query()->create([
                'event_id' => $changeLog->event_id,
                'event_occurrence_id' => $changeLog->event_occurrence_id,
                'event_session_id' => $changeLog->event_session_id,
                'event_change_log_id' => $changeLog->id,
                'event_update_id' => $changeLog->eventUpdate?->id,
                'audience_scope' => in_array($changeLog->impact_level, ['critical', 'high'], true) ? 'registrants' : 'followers',
                'title' => 'Event Change Notice',
                'message' => $changeLog->reason,
                'status' => 'pending',
                'metadata' => $changeLog->metadata,
            ]);
        });

        $this->dispatcher->dispatch($batch);
    }
}
