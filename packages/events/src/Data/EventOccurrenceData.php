<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventOccurrenceData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $event_id,
        public readonly string | null | Optional $title,
        public readonly CarbonImmutable $starts_at,
        public readonly CarbonImmutable $ends_at,
        public readonly string $timezone,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string | null | Optional $delivery_mode,
        public readonly int | null | Optional $capacity,
        public readonly CarbonImmutable | null | Optional $published_at,
        public readonly CarbonImmutable | null | Optional $cancelled_at,
        public readonly int | null | Optional $registration_count,
        /** @var array<EventSessionData> */
        public readonly array $sessions,
        /** @var array<EventLocationData> */
        public readonly array $locations,
        /** @var array<EventInvolvementData> */
        public readonly array $involvements,
        /** @var array<TicketTypeData> */
        public readonly array $ticket_types,
    ) {}

    public static function fromOccurrence(EventOccurrence $occurrence): self
    {
        $sessions = [];
        if ($occurrence->relationLoaded('sessions')) {
            foreach ($occurrence->sessions->sortBy('sort_order') as $session) {
                $sessions[] = EventSessionData::fromEventSession($session);
            }
        }

        $locations = [];
        if ($occurrence->relationLoaded('locations')) {
            foreach ($occurrence->locations as $location) {
                $locations[] = EventLocationData::fromEventLocation($location);
            }
        }

        $involvements = [];
        if ($occurrence->relationLoaded('involvements')) {
            $featured = $occurrence->involvements->where('is_featured', true);
            $others = $occurrence->involvements->where('is_featured', false);
            foreach ($featured->merge($others) as $involvement) {
                $involvements[] = EventInvolvementData::fromEventInvolvement($involvement);
            }
        }

        $ticketTypes = [];
        if ($occurrence->relationLoaded('ticketTypes')) {
            foreach ($occurrence->ticketTypes as $ticketType) {
                $ticketTypes[] = TicketTypeData::fromTicketType($ticketType);
            }
        }

        $registrationCount = null;
        if ($occurrence->relationLoaded('registrations')) {
            $registrationCount = $occurrence->registrations->count();
        }

        return new self(
            id: $occurrence->id,
            event_id: $occurrence->event_id,
            title: $occurrence->title,
            starts_at: $occurrence->starts_at,
            ends_at: $occurrence->ends_at,
            timezone: $occurrence->timezone,
            status: $occurrence->status->getValue(),
            visibility: $occurrence->visibility,
            delivery_mode: $occurrence->delivery_mode,
            capacity: $occurrence->capacity,
            published_at: $occurrence->published_at,
            cancelled_at: $occurrence->cancelled_at,
            registration_count: $registrationCount,
            sessions: $sessions,
            locations: $locations,
            involvements: $involvements,
            ticket_types: $ticketTypes,
        );
    }
}
