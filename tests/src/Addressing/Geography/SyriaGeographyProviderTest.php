<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;

it('formats Syrian addresses without a postcode system', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'state' => 'Damascus',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\nSyria");
});
it('prints any supplied Syrian code on its own line', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'postcode' => '0100',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\n0100\nSyria");
});
