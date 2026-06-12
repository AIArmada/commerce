<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\Venue;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class VenueData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $venue_type,
        public readonly string | null | Optional $address_line_1,
        public readonly string | null | Optional $address_line_2,
        public readonly string | null | Optional $city,
        public readonly string | null | Optional $state,
        public readonly string | null | Optional $postcode,
        public readonly string | null | Optional $country,
        public readonly float | null | Optional $latitude,
        public readonly float | null | Optional $longitude,
        public readonly string | null | Optional $google_maps_url,
        public readonly string | null | Optional $waze_url,
        public readonly string | null | Optional $phone,
        public readonly string | null | Optional $email,
        public readonly string | null | Optional $website_url,
        public readonly string | null | Optional $directions,
    ) {}

    public static function fromVenue(?Venue $venue): ?self
    {
        if ($venue === null) {
            return null;
        }

        return new self(
            id: $venue->id,
            name: $venue->name,
            slug: $venue->slug,
            venue_type: $venue->venue_type,
            address_line_1: $venue->address_line_1,
            address_line_2: $venue->address_line_2,
            city: $venue->city,
            state: $venue->state,
            postcode: $venue->postcode,
            country: $venue->country,
            latitude: $venue->latitude,
            longitude: $venue->longitude,
            google_maps_url: $venue->google_maps_url,
            waze_url: $venue->waze_url,
            phone: $venue->phone,
            email: $venue->email,
            website_url: $venue->website_url,
            directions: $venue->directions,
        );
    }
}
