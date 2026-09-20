<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaAddressFormatter;

it('formats French Polynesian addresses with the code left of the locality', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 123',
        'city' => 'PAPEETE',
        'postcode' => '98714',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("BP 123\n98714 PAPEETE\nFrench Polynesia");
});

it('formats Faaa addresses with their own code', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de l’Aéroport',
        'city' => 'FAAA',
        'postcode' => '98704',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("Rue de l’Aéroport\n98704 FAAA\nFrench Polynesia");
});
