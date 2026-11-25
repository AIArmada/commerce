<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use Illuminate\Database\Eloquent\Model;

final class EventScopeResolver
{
    /**
     * @return array{event_id: string, occurrence_id: ?string, session_id: ?string}
     */
    public static function resolve(Model $scope): array
    {
        $sessionClass = ModelResolver::sessionClass();

        if ($scope instanceof $sessionClass) {
            $persisted = $sessionClass::query()
                ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
                ->whereKey($scope->getKey())
                ->firstOrFail();

            /** @var Model $persisted */
            $eventId = (string) $persisted->getAttribute('event_id');

            EventWriteGuard::findOrFail($eventId);

            return [
                'event_id' => $eventId,
                'occurrence_id' => self::nullableKey($persisted->getAttribute('event_occurrence_id')),
                'session_id' => (string) $persisted->getKey(),
            ];
        }

        $occurrenceClass = ModelResolver::occurrenceClass();

        if ($scope instanceof $occurrenceClass) {
            $persisted = $occurrenceClass::query()
                ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
                ->whereKey($scope->getKey())
                ->firstOrFail();

            /** @var Model $persisted */
            $eventId = (string) $persisted->getAttribute('event_id');

            EventWriteGuard::findOrFail($eventId);

            return [
                'event_id' => $eventId,
                'occurrence_id' => (string) $persisted->getKey(),
                'session_id' => null,
            ];
        }

        $eventId = (string) $scope->getKey();

        EventWriteGuard::findOrFail($eventId);

        return [
            'event_id' => $eventId,
            'occurrence_id' => null,
            'session_id' => null,
        ];
    }

    private static function nullableKey(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
