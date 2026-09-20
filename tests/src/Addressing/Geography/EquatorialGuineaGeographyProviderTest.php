<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;

it('formats Equatoguinean addresses without a postcode system', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Viviendas sociales vicatana',
        'line2' => 'Portal 5, puerta 29',
        'city' => 'MALABO',
        'state' => 'Bioko Norte',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Viviendas sociales vicatana\nPortal 5, puerta 29\nMALABO\nBioko Norte\nEquatorial Guinea");
});
it('prints any supplied Equatoguinean code on its own line', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Apartado postal 7',
        'city' => 'MALABO',
        'postcode' => '99999',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Apartado postal 7\nMALABO\n99999\nEquatorial Guinea");
});
