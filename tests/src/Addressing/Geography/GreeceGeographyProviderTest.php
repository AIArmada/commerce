<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Greece\GreeceAddressFormatter;

it('formats Greek addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => '1, D. GOUNARI STREET',
        'city' => 'MAROUSI',
        'postcode' => '151 24',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("1, D. GOUNARI STREET\n151 24 MAROUSI\nGreece");
});
it('formats Greek post box addresses with the box postcode', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 999',
        'city' => 'MAROUSI',
        'postcode' => '151 10',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("P.O. BOX 999\n151 10 MAROUSI\nGreece");
});
