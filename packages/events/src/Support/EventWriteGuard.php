<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;

final class EventWriteGuard
{
    public static function findOrFail(Event | int | string $event): Event
    {
        $eventClass = ModelResolver::eventClass();
        $eventId = $event instanceof Event ? $event->getKey() : $event;

        if (method_exists($eventClass, 'ownerScopeConfig') && ! $eventClass::ownerScopeConfig()->enabled) {
            return $eventClass::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner($eventClass, $eventId);
    }
}
