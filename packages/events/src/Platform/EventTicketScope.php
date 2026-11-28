<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Model;

final class EventTicketScope
{
    public static function event(TicketType $ticketType): ?Model
    {
        $ticketable = self::ticketable($ticketType);

        if ($ticketable === null) {
            return null;
        }

        $eventClass = ModelResolver::eventClass();

        if ($ticketable instanceof $eventClass) {
            return $ticketable;
        }

        $event = $ticketable->getRelationValue('event');

        return $event instanceof Model ? $event : null;
    }

    public static function occurrence(TicketType $ticketType): ?Model
    {
        $ticketable = self::ticketable($ticketType);

        if ($ticketable === null) {
            return null;
        }

        $occurrenceClass = ModelResolver::occurrenceClass();

        if ($ticketable instanceof $occurrenceClass) {
            return $ticketable;
        }

        $sessionClass = ModelResolver::sessionClass();

        if (! $ticketable instanceof $sessionClass) {
            return null;
        }

        $occurrence = $ticketable->getRelationValue('occurrence');

        return $occurrence instanceof Model ? $occurrence : null;
    }

    public static function session(TicketType $ticketType): ?Model
    {
        $ticketable = self::ticketable($ticketType);

        if ($ticketable === null) {
            return null;
        }

        $sessionClass = ModelResolver::sessionClass();

        return $ticketable instanceof $sessionClass ? $ticketable : null;
    }

    public static function target(TicketType $ticketType): ?Model
    {
        return self::session($ticketType)
            ?? self::occurrence($ticketType)
            ?? self::event($ticketType);
    }

    /**
     * @return array{event_id: string|null, event_occurrence_id: string|null, event_session_id: string|null}
     */
    public static function ids(TicketType $ticketType): array
    {
        return [
            'event_id' => self::keyOf(self::event($ticketType)),
            'event_occurrence_id' => self::keyOf(self::occurrence($ticketType)),
            'event_session_id' => self::keyOf(self::session($ticketType)),
        ];
    }

    public static function belongsToRegistrationScope(TicketType $ticketType, EventRegistrationScopeData $scope): bool
    {
        $event = self::event($ticketType);

        if ($event === null || (string) $event->getKey() !== $scope->eventId) {
            return false;
        }

        $session = self::session($ticketType);

        if ($session !== null) {
            return $scope->sessionId !== null && (string) $session->getKey() === $scope->sessionId;
        }

        $occurrence = self::occurrence($ticketType);

        if ($occurrence !== null) {
            return $scope->occurrenceId !== null && (string) $occurrence->getKey() === $scope->occurrenceId;
        }

        return true;
    }

    private static function ticketable(TicketType $ticketType): ?Model
    {
        $ticketable = $ticketType->getRelationValue('ticketable');

        return $ticketable instanceof Model ? $ticketable : null;
    }

    private static function keyOf(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        return (string) $model->getKey();
    }
}
