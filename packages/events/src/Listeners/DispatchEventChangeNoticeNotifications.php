<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Events\EventChangeNoticePublished;
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

        OwnerContext::withOwner($resolvedEvent->owner, function () use ($changeLog): void {
            $this->dispatcher->dispatch($changeLog);
        });
    }
}
