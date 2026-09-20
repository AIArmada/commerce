<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;

it('formats Kazakh addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Ryskulbekov, dom16, kv 224',
        'city' => 'ASTANA',
        'postcode' => 'Z00Y5M7',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("ul. Ryskulbekov, dom16, kv 224\nZ00Y5M7, ASTANA\nKazakhstan");
});
it('formats Kazakh addresses with legacy 6-digit postcodes and region', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Abay Street 1',
        'city' => 'Taldykorgan',
        'state' => 'Jetisu',
        'postcode' => '040000',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("Abay Street 1\n040000, Taldykorgan\nJetisu\nKazakhstan");
});
