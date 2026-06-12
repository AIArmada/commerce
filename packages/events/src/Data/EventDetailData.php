<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\Event;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string | null | Optional $summary,
        public readonly string | null | Optional $description,
        public readonly string $type,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string $delivery_mode,
        public readonly string $timezone,
        public readonly string | null | Optional $status_reason,
        public readonly CarbonImmutable | null | Optional $published_at,
        public readonly CarbonImmutable | null | Optional $cancelled_at,
        public readonly CarbonImmutable | null | Optional $postponed_at,
        public readonly CarbonImmutable | null | Optional $archived_at,
        public readonly CarbonImmutable | null | Optional $completed_at,
        public readonly string | null | Optional $cover_image_url,
        public readonly CarbonImmutable $created_at,
        public readonly CarbonImmutable $updated_at,
        /** @var array<EventOccurrenceData> */
        public readonly array $occurrences,
        /** @var array<EventLocationData> */
        public readonly array $locations,
        /** @var array<EventInvolvementData> */
        public readonly array $involvements,
        /** @var array<TicketTypeData> */
        public readonly array $ticket_types,
        /** @var array<EventSessionData> */
        public readonly array $sessions,
        /** @var array<EventLinkData> */
        public readonly array $links,
    ) {}

    public static function fromEvent(Event $event): self
    {
        $occurrences = [];
        if ($event->relationLoaded('occurrences')) {
            foreach ($event->occurrences->sortBy('starts_at') as $occurrence) {
                $occurrences[] = EventOccurrenceData::fromOccurrence($occurrence);
            }
        }

        $locations = [];
        if ($event->relationLoaded('locations')) {
            foreach ($event->locations as $location) {
                $locations[] = EventLocationData::fromEventLocation($location);
            }
        }

        $involvements = [];
        if ($event->relationLoaded('involvements')) {
            $featured = $event->involvements->where('is_featured', true);
            $others = $event->involvements->where('is_featured', false);
            foreach ($featured->merge($others) as $involvement) {
                $involvements[] = EventInvolvementData::fromEventInvolvement($involvement);
            }
        }

        $ticketTypes = [];
        if ($event->relationLoaded('ticketTypes')) {
            foreach ($event->ticketTypes as $ticketType) {
                $ticketTypes[] = TicketTypeData::fromTicketType($ticketType);
            }
        }

        $sessions = [];
        if ($event->relationLoaded('sessions')) {
            foreach ($event->sessions->sortBy('sort_order') as $session) {
                $sessions[] = EventSessionData::fromEventSession($session);
            }
        }

        $links = [];
        if ($event->relationLoaded('links')) {
            foreach ($event->links as $link) {
                $links[] = EventLinkData::fromEventLink($link);
            }
        }

        return new self(
            id: $event->id,
            title: $event->title,
            slug: $event->slug,
            summary: $event->summary,
            description: $event->description,
            type: $event->type,
            status: $event->status,
            visibility: $event->visibility,
            delivery_mode: $event->delivery_mode,
            timezone: $event->timezone,
            status_reason: $event->status_reason,
            published_at: $event->published_at,
            cancelled_at: $event->cancelled_at,
            postponed_at: $event->postponed_at,
            archived_at: $event->archived_at,
            completed_at: $event->completed_at,
            cover_image_url: null,
            created_at: CarbonImmutable::make($event->created_at),
            updated_at: CarbonImmutable::make($event->updated_at),
            occurrences: $occurrences,
            locations: $locations,
            involvements: $involvements,
            ticket_types: $ticketTypes,
            sessions: $sessions,
            links: $links,
        );
    }
}
