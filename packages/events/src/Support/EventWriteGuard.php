<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;

final class EventWriteGuard
{
    public static function findOrFail(Event | int | string $event): Event
    {
        $eventId = $event instanceof Event ? $event->getKey() : $event;

        if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
            return Event::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner(Event::class, $eventId);
    }
}
