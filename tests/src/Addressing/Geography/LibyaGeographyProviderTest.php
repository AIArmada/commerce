<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;

it('formats Libyan addresses without a postcode system', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\nLibya");
});
it('prints any supplied Libyan code on its own line', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'postcode' => '99999',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\n99999\nLibya");
});
