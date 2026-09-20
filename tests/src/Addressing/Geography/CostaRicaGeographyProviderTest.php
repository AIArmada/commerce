<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaAddressFormatter;

it('formats Costa Rican addresses with the postcode above the country', function (): void {
    $formatted = app(CostaRicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ca 15 Av 37 # 55',
        'city' => 'Heredia, San Rafael, San Rafael',
        'postcode' => '40501',
        'country_code' => 'CR',
    ]));

    expect($formatted)->toBe("Ca 15 Av 37 # 55\nHeredia, San Rafael, San Rafael\n40501\nCosta Rica");
});
it('formats Costa Rican box addresses with the combined box postcode', function (): void {
    $formatted = app(CostaRicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Apdo 257 – 3017',
        'city' => 'Heredia, San Isidro, San Isidro',
        'postcode' => '3017-40601',
        'country_code' => 'CR',
    ]));

    expect($formatted)->toBe("Apdo 257 – 3017\nHeredia, San Isidro, San Isidro\n3017-40601\nCosta Rica");
});
