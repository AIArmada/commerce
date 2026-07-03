<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Ticketing\Models\TicketType;

final class EventTicketScope
{
    public static function event(TicketType $ticketType): ?Event
    {
        $ticketable = $ticketType->ticketable;

        return match (true) {
            $ticketable instanceof Event => $ticketable,
            $ticketable instanceof EventOccurrence => $ticketable->event,
            $ticketable instanceof EventSession => $ticketable->event,
            default => null,
        };
    }

    public static function occurrence(TicketType $ticketType): ?EventOccurrence
    {
        $ticketable = $ticketType->ticketable;

        return match (true) {
            $ticketable instanceof EventOccurrence => $ticketable,
            $ticketable instanceof EventSession => $ticketable->occurrence,
            default => null,
        };
    }

    public static function session(TicketType $ticketType): ?EventSession
    {
        $ticketable = $ticketType->ticketable;

        return $ticketable instanceof EventSession ? $ticketable : null;
    }

    public static function target(TicketType $ticketType): Event | EventOccurrence | EventSession | null
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
            'event_id' => self::event($ticketType)?->getKey(),
            'event_occurrence_id' => self::occurrence($ticketType)?->getKey(),
            'event_session_id' => self::session($ticketType)?->getKey(),
        ];
    }

    public static function belongsToRegistrationScope(TicketType $ticketType, EventRegistrationScope $scope): bool
    {
        $event = self::event($ticketType);

        if ($event === null || $event->isNot($scope->event)) {
            return false;
        }

        $session = self::session($ticketType);

        if ($session !== null) {
            return $scope->session?->is($session) ?? false;
        }

        $occurrence = self::occurrence($ticketType);

        if ($occurrence !== null) {
            return $scope->occurrence?->is($occurrence) ?? false;
        }

        return true;
    }
}
