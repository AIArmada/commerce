<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bolivia\BoliviaAddressFormatter;

it('formats Bolivian addresses without a postcode system', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CALLE AZURDUY 158',
        'city' => 'SUCRE',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("CALLE AZURDUY 158\nSUCRE\nBolivia");
});
it('formats Bolivian box addresses with the casilla in the street line', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Casilla Postal 1234',
        'city' => 'LA PAZ',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("Casilla Postal 1234\nLA PAZ\nBolivia");
});
