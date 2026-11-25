<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EventWriteGuard
{
    public const string OWNER_SCOPE = 'event_owner';

    public static function findOrFail(Model | int | string $event): Model
    {
        $eventClass = ModelResolver::eventClass();
        $eventId = $event instanceof Model ? $event->getKey() : $event;

        if (! is_int($eventId) && ! is_string($eventId)) {
            throw new InvalidArgumentException(sprintf(
                'Event keys must be scalar; got %s.',
                get_debug_type($eventId),
            ));
        }

        if (self::ownerScopeDisabled($eventClass)) {
            return $eventClass::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner($eventClass, $eventId);
    }

    /**
     * @param  class-string<Model>  $eventClass
     */
    private static function ownerScopeDisabled(string $eventClass): bool
    {
        if (! method_exists($eventClass, 'ownerScopeConfig')) {
            return false;
        }

        return ! $eventClass::ownerScopeConfig()->enabled;
    }
}
