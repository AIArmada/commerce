<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;

it('formats Ivorian addresses without a postcode system', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\nIvory Coast");
});
it('prints any supplied Ivorian code on its own line', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'postcode' => '99999',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\n99999\nIvory Coast");
});
