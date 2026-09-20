<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;

it('formats Sri Lankan addresses with the postcode below the locality', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'line2' => 'Silkhouse Street',
        'city' => 'KANDY',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nSilkhouse Street\nKANDY\n20000\nSri Lanka");
});
it('formats Sri Lankan addresses keeping the province above the postcode line', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'city' => 'KANDY',
        'state' => 'Central',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nKANDY\nCentral\n20000\nSri Lanka");
});
