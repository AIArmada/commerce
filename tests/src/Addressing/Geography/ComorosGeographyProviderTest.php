<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;

it('formats Comorian addresses without a postcode system', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 350',
        'city' => 'MORONI',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("BP 350\nMORONI\nComoros");
});
it('formats Comorian addresses with the island below the locality', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Corniche',
        'city' => 'Moroni',
        'state' => 'Grande Comore',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("Rue de la Corniche\nMoroni\nGrande Comore\nComoros");
});
