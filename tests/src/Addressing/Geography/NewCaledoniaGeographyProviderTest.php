<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaAddressFormatter;

it('formats New Caledonian addresses with the code left of the locality', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '24 RUE DES PALMIERS',
        'city' => 'NOUMEA',
        'postcode' => '98800',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("24 RUE DES PALMIERS\n98800 NOUMEA\nNew Caledonia");
});
it('formats Mont-Dore addresses with their own code', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 485',
        'city' => 'MONT-DORE',
        'postcode' => '98810',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("BP 485\n98810 MONT-DORE\nNew Caledonia");
});
