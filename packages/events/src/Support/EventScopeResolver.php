<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

/**
 * Resolve the canonical persisted scope tuple for event child writers.
 *
 * Scope models supplied by callers may carry dirty in-memory attributes, so
 * occurrence and session scopes are re-resolved from storage and only the
 * persisted keys are used for writes. The owning event is always guarded
 * through EventWriteGuard, and a nullable session occurrence stays null.
 */
final class EventScopeResolver
{
    /**
     * @return array{event: Event, event_id: string, occurrence_id: ?string, session_id: ?string}
     */
    public static function resolve(Event | EventOccurrence | EventSession $scope): array
    {
        if ($scope instanceof EventSession) {
            $persisted = EventSession::query()
                ->withoutOwnerScope()
                ->whereKey($scope->getKey())
                ->firstOrFail();

            $event = EventWriteGuard::findOrFail($persisted->event_id);

            return [
                'event' => $event,
                'event_id' => (string) $event->getKey(),
                'occurrence_id' => self::nullableKey($persisted->event_occurrence_id),
                'session_id' => (string) $persisted->getKey(),
            ];
        }

        if ($scope instanceof EventOccurrence) {
            $persisted = EventOccurrence::query()
                ->withoutOwnerScope()
                ->whereKey($scope->getKey())
                ->firstOrFail();

            $event = EventWriteGuard::findOrFail($persisted->event_id);

            return [
                'event' => $event,
                'event_id' => (string) $event->getKey(),
                'occurrence_id' => (string) $persisted->getKey(),
                'session_id' => null,
            ];
        }

        $event = EventWriteGuard::findOrFail($scope->getKey());

        return [
            'event' => $event,
            'event_id' => (string) $event->getKey(),
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
