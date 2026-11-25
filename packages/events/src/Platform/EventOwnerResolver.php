<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EventOwnerResolver
{
    public static function resolve(string $eventId): ?Model
    {
        $eventClass = ModelResolver::eventClass();

        /** @var Model|null $event */
        $event = $eventClass::query()->whereKey($eventId)->first();

        if (! $event instanceof Model) {
            return null;
        }

        $ownerType = $event->getAttribute('owner_type');
        $ownerId = $event->getAttribute('owner_id');

        if (! is_string($ownerType) || $ownerType === '' || $ownerId === null || $ownerId === '') {
            return null;
        }

        $ownerClass = Relation::getMorphedModel($ownerType) ?? $ownerType;

        if (! is_string($ownerClass) || ! is_a($ownerClass, Model::class, true)) {
            return null;
        }

        $owner = $ownerClass::query()->find($ownerId);

        return $owner instanceof Model ? $owner : null;
    }
}
