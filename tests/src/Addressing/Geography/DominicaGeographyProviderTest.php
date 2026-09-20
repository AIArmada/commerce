<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Dominica\DominicaAddressFormatter;

it('formats Dominican addresses without a postcode system', function (): void {
    $formatted = app(DominicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bay Front',
        'city' => 'ROSEAU',
        'country_code' => 'DM',
    ]));

    expect($formatted)->toBe("Bay Front\nROSEAU\nDominica");
});
it('prints any supplied Dominican code on its own line', function (): void {
    $formatted = app(DominicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bay Front',
        'city' => 'ROSEAU',
        'postcode' => '99999',
        'country_code' => 'DM',
    ]));

    expect($formatted)->toBe("Bay Front\nROSEAU\n99999\nDominica");
});
