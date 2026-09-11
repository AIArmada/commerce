<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Addressing\Models\Address;
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
        public readonly string | null | Optional $line1,
        public readonly string | null | Optional $line2,
        public readonly string | null | Optional $city,
        public readonly string | null | Optional $state,
        public readonly string | null | Optional $postcode,
        public readonly string | null | Optional $country_code,
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

        $address = $venue->primaryAddress();

        return new self(
            id: $venue->id,
            name: $venue->name,
            slug: $venue->slug,
            venue_type: $venue->venue_type,
            line1: $address?->line1,
            line2: $address?->line2,
            city: $address?->city,
            state: $address?->state,
            postcode: $address?->postcode,
            country_code: $address?->country_code,
            latitude: $address?->latitude,
            longitude: $address?->longitude,
            google_maps_url: $address?->google_maps_url,
            waze_url: $address?->waze_url,
            phone: $venue->phone,
            email: $venue->email,
            website_url: $venue->website_url,
            directions: self::directionsFrom($address),
        );
    }

    private static function directionsFrom(?Address $address): ?string
    {
        $directions = $address?->metadata['directions'] ?? null;

        return is_string($directions) ? $directions : null;
    }
}
