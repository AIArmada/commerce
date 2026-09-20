<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;

it('formats Beninese addresses without a postcode system', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\nBenin");
});
it('prints any supplied Beninese code on its own line', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'postcode' => '99999',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\n99999\nBenin");
});
