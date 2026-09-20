<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;

it('formats Somali addresses without an operational postcode', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nSomalia");
});
it('prints any supplied Somali paper code on its own line', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'postcode' => 'JH 09010',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nJH 09010\nSomalia");
});
