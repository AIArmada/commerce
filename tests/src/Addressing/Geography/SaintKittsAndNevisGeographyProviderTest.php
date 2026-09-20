<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisAddressFormatter;

it('formats Kittitian addresses with the postcode below the island', function (): void {
    $formatted = app(SaintKittsAndNevisAddressFormatter::class)->format(AddressData::from([
        'line1' => '2 Fern Street',
        'line2' => 'Greenlands',
        'city' => 'Basseterre',
        'state' => 'St Kitts',
        'postcode' => 'KN0101',
        'country_code' => 'KN',
    ]));

    expect($formatted)->toBe("2 Fern Street\nGreenlands\nBasseterre\nSt Kitts\nKN0101\nSaint Kitts and Nevis");
});
it('formats Nevis addresses with the island postcode', function (): void {
    $formatted = app(SaintKittsAndNevisAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Main Street',
        'city' => 'Charlestown',
        'state' => 'Nevis',
        'postcode' => 'KN0902',
        'country_code' => 'KN',
    ]));

    expect($formatted)->toBe("Main Street\nCharlestown\nNevis\nKN0902\nSaint Kitts and Nevis");
});
