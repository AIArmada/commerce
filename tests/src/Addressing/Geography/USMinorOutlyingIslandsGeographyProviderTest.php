<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsAddressFormatter;

it('formats US Minor Outlying Islands addresses without a postcode system', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wake Island Airfield',
        'city' => 'Wake Island',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("Wake Island Airfield\nWake Island\nUnited States Minor Outlying Islands");
});

it('prints any supplied Wake code on its own line', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 501',
        'city' => 'Wake Island',
        'postcode' => '96898',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("PO Box 501\nWake Island\n96898\nUnited States Minor Outlying Islands");
});
