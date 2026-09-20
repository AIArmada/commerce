<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guernsey\GuernseyAddressFormatter;

it('formats Guernsey addresses with the postcode below the post town', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Anybank House',
        'line2' => 'Le Pollet',
        'line3' => 'St Peter Port',
        'city' => 'GUERNSEY',
        'postcode' => 'GY1 1AA',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("Anybank House\nLe Pollet\nSt Peter Port\nGUERNSEY\nGY1 1AA\nGuernsey");
});
it('formats Sark addresses with the two-digit district postcode', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'La Seigneurie',
        'city' => 'SARK',
        'postcode' => 'GY10 1SF',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("La Seigneurie\nSARK\nGY10 1SF\nGuernsey");
});
