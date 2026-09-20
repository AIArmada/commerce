<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaAddressFormatter;

it('formats Micronesian addresses with the US ZIP layout', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'Pohnpei',
        'postcode' => '96941',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 123\nPohnpei FM 96941\nMicronesia");
});
it('formats Chuuk addresses with its own ZIP', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 9',
        'city' => 'Weno',
        'postcode' => '96942',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 9\nWeno FM 96942\nMicronesia");
});
