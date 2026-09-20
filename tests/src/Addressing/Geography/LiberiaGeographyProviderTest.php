<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;

it('formats Liberian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Buchanan',
        'postcode' => '4000',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\n4000 Buchanan\nLiberia");
});
it('formats Liberian addresses without a postcode when missing', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Monrovia',
        'state' => 'Montserrado',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\nMonrovia\nMontserrado\nLiberia");
});
