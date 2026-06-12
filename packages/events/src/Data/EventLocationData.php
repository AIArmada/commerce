<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventLocation;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventLocationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $location_role,
        public readonly string | null | Optional $label,
        public readonly string | null | Optional $address_line_1,
        public readonly string | null | Optional $city,
        public readonly string | null | Optional $state,
        public readonly string | null | Optional $country,
        public readonly float | null | Optional $latitude,
        public readonly float | null | Optional $longitude,
        public readonly string | null | Optional $google_maps_url,
        public readonly string | null | Optional $waze_url,
        public readonly string | null | Optional $directions,
        public readonly VenueData | null | Optional $venue,
    ) {}

    public static function fromEventLocation(EventLocation $location): self
    {
        return new self(
            id: $location->id,
            location_role: $location->location_role,
            label: $location->label,
            address_line_1: $location->address_line_1,
            city: $location->city,
            state: $location->state,
            country: $location->country,
            latitude: $location->latitude,
            longitude: $location->longitude,
            google_maps_url: $location->google_maps_url,
            waze_url: $location->waze_url,
            directions: $location->directions,
            venue: $location->relationLoaded('venue') ? VenueData::fromVenue($location->venue) : null,
        );
    }
}
