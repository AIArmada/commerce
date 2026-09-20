<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandAddressFormatter;

it('formats Swiss addresses with the postcode left of the town', function (): void {
    $formatted = app(SwitzerlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Solothurnerstrasse 28',
        'city' => 'BETTLACH',
        'postcode' => '2544',
        'country_code' => 'CH',
    ]));

    expect($formatted)->toBe("Solothurnerstrasse 28\n2544 BETTLACH\nSwitzerland");
});
it('formats Swiss addresses passing canton suffixes through', function (): void {
    $formatted = app(SwitzerlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Route de la Gare 2',
        'city' => 'CERNIAZ VD',
        'postcode' => '1556',
        'country_code' => 'CH',
    ]));

    expect($formatted)->toBe("Route de la Gare 2\n1556 CERNIAZ VD\nSwitzerland");
});
