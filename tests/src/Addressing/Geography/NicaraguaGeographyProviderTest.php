<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaAddressFormatter;

it('formats Nicaraguan addresses with the postcode above the municipality', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.',
        'city' => 'Managua',
        'state' => 'Managua',
        'postcode' => '12005',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.\n12005\nManagua\nNicaragua");
});
it('formats Nicaraguan Granada addresses with the town postcode', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle La Calzada 4',
        'city' => 'Granada',
        'postcode' => '43000',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Calle La Calzada 4\n43000\nGranada\nNicaragua");
});
