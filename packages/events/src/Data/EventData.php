<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\Event;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string | null | Optional $summary,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string $delivery_mode,
        public readonly string $timezone,
        public readonly CarbonImmutable | null | Optional $published_at,
        public readonly CarbonImmutable | null | Optional $cancelled_at,
        public readonly CarbonImmutable | null | Optional $postponed_at,
        public readonly CarbonImmutable | null | Optional $archived_at,
        public readonly CarbonImmutable | null | Optional $starts_at,
        public readonly CarbonImmutable | null | Optional $ends_at,
        public readonly string | null | Optional $location_summary,
        public readonly string | null | Optional $cover_image_url,
        public readonly CarbonImmutable $created_at,
        public readonly CarbonImmutable $updated_at,
    ) {}

    public static function fromEvent(Event $event): self
    {
        $occurrence = $event->occurrences()
            ->whereIn('status', ['scheduled', 'published', 'live'])
            ->orderBy('starts_at')
            ->first();

        $location = $event->locations()->where('location_role', 'primary')->first();

        return new self(
            id: $event->id,
            title: $event->title,
            slug: $event->slug,
            summary: $event->summary,
            status: $event->status,
            visibility: $event->visibility,
            delivery_mode: $event->delivery_mode,
            timezone: $event->timezone,
            published_at: $event->published_at,
            cancelled_at: $event->cancelled_at,
            postponed_at: $event->postponed_at,
            archived_at: $event->archived_at,
            starts_at: $occurrence?->starts_at,
            ends_at: $occurrence?->ends_at,
            location_summary: $location ? collect([$location->city, $location->state, $location->country_code])->filter()->implode(', ') : null,
            cover_image_url: null,
            created_at: CarbonImmutable::make($event->created_at),
            updated_at: CarbonImmutable::make($event->updated_at),
        );
    }
}
