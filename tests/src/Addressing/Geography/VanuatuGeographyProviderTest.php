<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuAddressFormatter;

it('formats Vanuatu addresses without a postcode system', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\nVanuatu");
});

it('prints any supplied Port Vila code on its own line', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'postcode' => '99999',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\n99999\nVanuatu");
});
