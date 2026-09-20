<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueAddressFormatter;

it('formats Mozambican addresses with the postcode left and province below', function (): void {
    $formatted = app(MozambiqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AV. Julius Nyerere 3412',
        'city' => 'MAPUTO',
        'state' => 'MAPUTO',
        'postcode' => '1100',
        'country_code' => 'MZ',
    ]));

    expect($formatted)->toBe("AV. Julius Nyerere 3412\n1100 MAPUTO\nMAPUTO\nMozambique");
});
