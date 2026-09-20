<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;

it('formats Tanzanian addresses with the region on its own line', function (): void {
    $formatted = app(TanzaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Ally Hassan Mwinyi',
        'city' => 'MSASANI',
        'state' => 'DAR ES SALAM',
        'postcode' => '14111',
        'country_code' => 'TZ',
    ]));

    expect($formatted)->toBe("22 Ally Hassan Mwinyi\n14111 MSASANI\nDAR ES SALAM\nTanzania");
});
