<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovenia\SloveniaAddressFormatter;

it('formats Slovenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Prešemova ul. 16',
        'city' => 'KRANJ',
        'postcode' => '4000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Prešemova ul. 16\n4000 KRANJ\nSlovenia");
});
it('formats Slovenian addresses passing SI prefixes through', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Slovenska cesta 1',
        'city' => 'LJUBLJANA',
        'postcode' => 'SI-1000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Slovenska cesta 1\nSI-1000 LJUBLJANA\nSlovenia");
});
