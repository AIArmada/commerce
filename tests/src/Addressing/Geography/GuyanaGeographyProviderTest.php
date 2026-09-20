<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guyana\GuyanaAddressFormatter;

it('formats Guyanese addresses with the postcode below the locality', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Room 15',
        'line2' => '183/185 Marja Bulding',
        'line3' => 'Lacytown',
        'city' => 'Georgetown',
        'postcode' => '4130106',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Room 15\n183/185 Marja Bulding\nLacytown\nGeorgetown\n4130106\nGuyana");
});
it('formats Guyanese East Coast addresses with the district postcode', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot 12 Public Road',
        'city' => 'East Coast Demerara',
        'postcode' => '4212501',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Lot 12 Public Road\nEast Coast Demerara\n4212501\nGuyana");
});
