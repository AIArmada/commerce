<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Geography\SaintHelena;

use AIArmada\Addressing\Contracts\CountryAddressFormatter;
use AIArmada\Addressing\Data\AddressData;

final class SaintHelenaAddressFormatter implements CountryAddressFormatter
{
    public function countryCode(): string
    {
        return 'SH';
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
            $lines[] = mb_strtoupper($address->countryCode) === 'SH' ? 'Saint Helena' : $address->countryCode;
        }

        return implode("\n", $lines);
    }
}
