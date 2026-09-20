<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaAddressFormatter;

it('formats Macedonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(NorthMacedoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '"Ilindenska" 2/1-8',
        'city' => 'SKOPJE',
        'postcode' => '1020',
        'country_code' => 'MK',
    ]));

    expect($formatted)->toBe("\"Ilindenska\" 2/1-8\n1020 SKOPJE\nNorth Macedonia");
});
it('formats Macedonian addresses with the town postcode', function (): void {
    $formatted = app(NorthMacedoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bulevar JNA 12',
        'city' => 'KUMANOVO',
        'postcode' => '1310',
        'country_code' => 'MK',
    ]));

    expect($formatted)->toBe("Bulevar JNA 12\n1310 KUMANOVO\nNorth Macedonia");
});
