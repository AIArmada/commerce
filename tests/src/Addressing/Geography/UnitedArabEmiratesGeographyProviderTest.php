<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;

it('formats Emirati addresses without a postcode line', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'DUBAI',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDUBAI\nUnited Arab Emirates");
});
it('prints Emirati city-states once when city and emirate match', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'Dubai',
        'state' => 'Dubai',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDubai\nUnited Arab Emirates");
});
it('formats Emirati addresses with emirate only', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'state' => 'Dubai',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDubai\nUnited Arab Emirates");
});
it('keeps distinct Emirati city and emirate lines', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'Al Ain',
        'state' => 'Abu Dhabi',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nAl Ain\nAbu Dhabi\nUnited Arab Emirates");
});
