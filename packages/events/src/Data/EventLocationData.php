<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Addressing\Models\Address;
use AIArmada\Events\Models\EventLocation;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventLocationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $location_role,
        public readonly string | null | Optional $space_name_snapshot,
        public readonly string | null | Optional $label,
        public readonly string | null | Optional $line1,
        public readonly string | null | Optional $city,
        public readonly string | null | Optional $state,
        public readonly string | null | Optional $country_code,
        public readonly float | null | Optional $latitude,
        public readonly float | null | Optional $longitude,
        public readonly string | null | Optional $google_maps_url,
        public readonly string | null | Optional $waze_url,
        public readonly string | null | Optional $google_place_id,
        public readonly string | null | Optional $google_feature_id,
        public readonly string | null | Optional $google_cid,
        public readonly string | null | Optional $google_entity_id,
        public readonly string | null | Optional $directions,
        public readonly VenueData | null | Optional $venue,
    ) {}

    public static function fromEventLocation(EventLocation $location): self
    {
        $address = $location->primaryAddress();

        return new self(
            id: $location->id,
            location_role: $location->location_role,
            space_name_snapshot: $location->space_name_snapshot,
            label: $location->label,
            line1: $address?->line1,
            city: $address?->city,
            state: $address?->state,
            country_code: $address?->country_code,
            latitude: $address?->latitude,
            longitude: $address?->longitude,
            google_maps_url: $address?->google_maps_url,
            waze_url: $address?->waze_url,
            google_place_id: $address?->google_place_id,
            google_feature_id: $address?->google_feature_id,
            google_cid: $address?->google_cid,
            google_entity_id: $address?->google_entity_id,
            directions: self::directionsFrom($address),
            venue: $location->relationLoaded('venue') ? VenueData::fromVenue($location->venue) : null,
        );
    }

    private static function directionsFrom(?Address $address): ?string
    {
        $directions = $address?->metadata['directions'] ?? null;

        return is_string($directions) ? $directions : null;
    }
}
