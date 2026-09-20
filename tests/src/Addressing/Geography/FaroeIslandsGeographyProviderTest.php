<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsAddressFormatter;

it('formats Faroese addresses with the postcode left of the locality', function (): void {
    $formatted = app(FaroeIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Óðinshædd 2',
        'city' => 'Tórshavn',
        'postcode' => 'FO-100',
        'country_code' => 'FO',
    ]));

    expect($formatted)->toBe("Óðinshædd 2\nFO-100 Tórshavn\nFaroe Islands");
});
it('formats Faroese northern addresses with the town postcode', function (): void {
    $formatted = app(FaroeIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bøgøta 5',
        'city' => 'Klaksvík',
        'postcode' => 'FO-700',
        'country_code' => 'FO',
    ]));

    expect($formatted)->toBe("Bøgøta 5\nFO-700 Klaksvík\nFaroe Islands");
});
