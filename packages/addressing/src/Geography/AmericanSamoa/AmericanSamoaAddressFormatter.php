<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\AmericanSamoa;

use AIArmada\Addressing\Contracts\CountryAddressFormatter;
use AIArmada\Addressing\Data\AddressData;

final class AmericanSamoaAddressFormatter implements CountryAddressFormatter
{
    public function countryCode(): string
    {
        return 'AS';
    }

    public function format(AddressData $address): string
    {
        // UPU layout pending research; replaced before merge.
        $lines = array_filter([
            $address->line1,
            $address->line2,
            $address->line3,
            $address->city,
            $address->state,
        ]);

        if ($address->country !== null && $address->country !== '') {
            $lines[] = $address->country;
        } elseif ($address->countryCode !== null && $address->countryCode !== '') {
            $lines[] = mb_strtoupper($address->countryCode) === 'AS' ? 'American Samoa' : $address->countryCode;
        }

        return implode("\n", $lines);
    }
}
