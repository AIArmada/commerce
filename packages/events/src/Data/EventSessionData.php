<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventSessionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string|null|Optional $summary,
        public readonly string|null|Optional $description,
        public readonly CarbonImmutable $starts_at,
        public readonly CarbonImmutable $ends_at,
        public readonly string $timezone,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string $delivery_mode,
        public readonly int|null|Optional $capacity,
        public readonly int $sort_order,
        public readonly EventLocationData|null|Optional $location,
        /** @var array<EventInvolvementData> */
        public readonly array $speakers,
    ) {}

    public static function fromEventSession(EventSession $session): self
    {
        $location = $session->relationLoaded('locations')
            ? $session->locations->where('location_role', 'primary')->first()
            : null;

        $speakers = [];
        if ($session->relationLoaded('involvements')) {
            foreach ($session->involvements->whereIn('role_code', ['speaker', 'headliner_speaker', 'panelist', 'moderator', 'mc']) as $involvement) {
                $speakers[] = EventInvolvementData::fromEventInvolvement($involvement);
            }
        }

        return new self(
            id: $session->id,
            title: $session->title,
            slug: $session->slug,
            summary: $session->summary,
            description: $session->description,
            starts_at: $session->starts_at,
            ends_at: $session->ends_at,
            timezone: $session->timezone,
            status: $session->status,
            visibility: $session->visibility,
            delivery_mode: $session->delivery_mode,
            capacity: $session->capacity,
            sort_order: $session->sort_order,
            location: $location ? EventLocationData::fromEventLocation($location) : null,
            speakers: $speakers,
        );
    }
}
