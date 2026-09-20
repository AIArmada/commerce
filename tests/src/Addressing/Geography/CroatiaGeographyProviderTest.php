<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Croatia\CroatiaAddressFormatter;

it('formats Croatian inbound addresses with the HR postcode prefix', function (): void {
    $formatted = app(CroatiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Krapinska 17/I stan 4',
        'city' => 'ZAGREB',
        'postcode' => 'HR-10000',
        'country_code' => 'HR',
    ]));

    expect($formatted)->toBe("Krapinska 17/I stan 4\nHR-10000 ZAGREB\nCroatia");
});
it('formats Croatian domestic addresses with a bare postcode', function (): void {
    $formatted = app(CroatiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.P. 105',
        'city' => 'SPLIT',
        'postcode' => '21001',
        'country_code' => 'HR',
    ]));

    expect($formatted)->toBe("P.P. 105\n21001 SPLIT\nCroatia");
});
