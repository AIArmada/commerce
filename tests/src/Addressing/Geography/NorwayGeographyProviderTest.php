<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Norway\NorwayAddressFormatter;

it('formats Norwegian addresses with the postcode left of the locality', function (): void {
    $formatted = app(NorwayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Karl Johansgate 25 B',
        'city' => 'OSLO',
        'postcode' => '0025',
        'country_code' => 'NO',
    ]));

    expect($formatted)->toBe("Karl Johansgate 25 B\n0025 OSLO\nNorway");
});
it('formats Norwegian rural addresses with the village postcode', function (): void {
    $formatted = app(NorwayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ølvevegen 44',
        'city' => 'ØLVE',
        'postcode' => '5637',
        'country_code' => 'NO',
    ]));

    expect($formatted)->toBe("Ølvevegen 44\n5637 ØLVE\nNorway");
});
