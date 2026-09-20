<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ireland\IrelandAddressFormatter;

it('formats Irish addresses with the Eircode below the county', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '56 Broomfield',
        'city' => 'MACROOM',
        'state' => 'CO. CORK',
        'postcode' => 'T37 F8HK',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("56 Broomfield\nMACROOM\nCO. CORK\nT37 F8HK\nIreland");
});
it('formats Irish Dublin addresses with the district routing key', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Grafton Street',
        'city' => 'DUBLIN 2',
        'state' => 'Dublin',
        'postcode' => 'D02 TF12',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("12 Grafton Street\nDUBLIN 2\nDublin\nD02 TF12\nIreland");
});
