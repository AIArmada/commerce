<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

final class EventLifecyclePolicy
{
    private const array PUBLISHABLE_STATUSES = ['draft', 'scheduled', 'delayed', 'postponed'];

    private const array CANCELLABLE_STATUSES = ['draft', 'scheduled', 'published', 'delayed', 'postponed', 'rescheduled'];

    private const array ARCHIVABLE_STATUSES = ['draft', 'scheduled', 'published', 'delayed', 'postponed', 'rescheduled', 'cancelled', 'completed'];

    private const array COMPLETABLE_STATUSES = ['scheduled', 'published', 'delayed', 'rescheduled'];

    public function canPublish(Event $event): bool
    {
        return in_array($event->status, self::PUBLISHABLE_STATUSES, true);
    }

    public function canCancel(Event|EventOccurrence $target): bool
    {
        return in_array($target->status, self::CANCELLABLE_STATUSES, true);
    }

    public function canArchive(Event|EventOccurrence $target): bool
    {
        return in_array($target->status, self::ARCHIVABLE_STATUSES, true);
    }

    public function canPostpone(Event|EventOccurrence $target): bool
    {
        return in_array($target->status, ['scheduled', 'published', 'delayed', 'rescheduled'], true);
    }

    public function canDelay(EventOccurrence $occurrence): bool
    {
        return in_array($occurrence->status, ['scheduled', 'published'], true);
    }

    public function canReschedule(EventOccurrence $occurrence): bool
    {
        return in_array($occurrence->status, ['scheduled', 'published', 'delayed', 'postponed', 'cancelled'], true);
    }

    public function canComplete(Event|EventOccurrence $target): bool
    {
        return in_array($target->status, self::COMPLETABLE_STATUSES, true);
    }
}
