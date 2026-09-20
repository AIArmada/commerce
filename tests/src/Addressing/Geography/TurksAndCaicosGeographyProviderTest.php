<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosAddressFormatter;

it('formats Turks and Caicos addresses with the single code below the locality', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'George Brown Post Office',
        'line2' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'postcode' => 'TKCA 1ZZ',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("George Brown Post Office\nAirport road\nDOWNTOWN, PROVIDENCIALES\nTKCA 1ZZ\nTurks and Caicos Islands");
});
it('formats Turks and Caicos addresses without a postcode when missing', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("Airport road\nDOWNTOWN, PROVIDENCIALES\nTurks and Caicos Islands");
});
