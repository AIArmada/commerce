<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Data\EventChangeNoticeNotificationData;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Platform\EventWriteGuard;

final class DispatchEventChangeNoticeNotificationsAction
{
    public function __construct(
        private readonly EventChangeNoticeNotificationDispatcher $dispatcher,
    ) {}

    public function handle(string $changeLogId): void
    {
        $changeLog = EventChangeLog::query()->whereKey($changeLogId)->firstOrFail();
        $changeLog->loadMissing('eventUpdate');

        $event = EventWriteGuard::findOrFail((string) $changeLog->event_id);

        $audienceScope = in_array($changeLog->impact_level, ['critical', 'high'], true)
            ? 'registrants'
            : 'followers';

        $update = $changeLog->getRelationValue('eventUpdate');

        $notice = new EventChangeNoticeNotificationData(
            title: 'Event Change Notice',
            message: $changeLog->reason,
            eventId: (string) $changeLog->event_id,
            eventType: $event->getMorphClass(),
            changeLogId: $changeLogId,
            updateId: $update instanceof EventUpdate ? (string) $update->getKey() : null,
            audienceScope: $audienceScope,
        );

        OwnerContext::withOwner($event->getRelationValue('owner'), function () use ($notice): void {
            $this->dispatcher->dispatch($notice);
        });
    }
}
