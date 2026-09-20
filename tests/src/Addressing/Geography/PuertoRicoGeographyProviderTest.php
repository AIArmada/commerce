<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoAddressFormatter;

it('formats Puerto Rican addresses as locality PR ZIP', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'URB LAS GLADIOLAS',
        'line2' => '150 CALLE A',
        'city' => 'SAN JUAN',
        'postcode' => '00926-0221',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("URB LAS GLADIOLAS\n150 CALLE A\nSAN JUAN PR 00926-0221\nPuerto Rico");
});
it('formats Puerto Rican addresses keeping the barrio above the ZIP line', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Luna 10',
        'city' => 'Santurce',
        'state' => 'San Juan',
        'postcode' => '00907',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("Calle Luna 10\nSanturce\nSan Juan PR 00907\nPuerto Rico");
});
