<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;

/**
 * Keeps the pre-existing venue/location flat columns readable while canonical
 * address attachments become the only write path for shared addressing.
 */
trait ReadsLegacyAddressColumns
{
    public function getPrimaryAddressData(): ?AddressData
    {
        $address = $this->primaryAddress();

        if ($address instanceof Address) {
            return AddressData::from($address->attributesToArray());
        }

        return $this->buildAddressDataFromFlatColumns();
    }

    protected function buildAddressDataFromFlatColumns(): AddressData
    {
        return AddressData::from([
            'line1' => $this->getAttribute('line1'),
            'line2' => $this->getAttribute('line2'),
            'line3' => $this->getAttribute('line3'),
            'city' => $this->getAttribute('city'),
            'state' => $this->getAttribute('state'),
            'postcode' => $this->getAttribute('postcode'),
            'country' => $this->getAttribute('country'),
            'countryCode' => $this->getAttribute('country_code'),
            'latitude' => $this->getAttribute('latitude'),
            'longitude' => $this->getAttribute('longitude'),
            'googleMapsUrl' => $this->getAttribute('google_maps_url') ?? $this->getAttribute('map_url'),
            'wazeUrl' => $this->getAttribute('waze_url'),
            'providerPlaceId' => $this->getAttribute('google_place_id'),
            'metadata' => array_filter([
                'directions' => $this->getAttribute('directions'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }
}
