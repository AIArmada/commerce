<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;

it('formats Burkinabe addresses with the postcode left of the locality', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => '566 Avenue de la Nation',
        'city' => 'OUAGADOUGOU',
        'postcode' => '10010',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("566 Avenue de la Nation\n10010 OUAGADOUGOU\nBurkina Faso");
});
it('formats rural Burkinabe addresses with the region below the postcode line', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Secteur 3',
        'city' => 'TENKODOGO',
        'state' => 'Centre-Est',
        'postcode' => '70000',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("Secteur 3\n70000 TENKODOGO\nCentre-Est\nBurkina Faso");
});
