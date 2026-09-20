<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;

it('formats Guinean addresses with the radical left of the locality', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 457',
        'city' => 'CONAKRY',
        'postcode' => '001',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 457\n001 CONAKRY\nGuinea");
});
it('prints matching Guinean city and region once', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Labé',
        'state' => 'Labé',
        'postcode' => '201',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 12\n201 Labé\nGuinea");
});
