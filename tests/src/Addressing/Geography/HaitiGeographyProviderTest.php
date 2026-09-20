<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Haiti\HaitiAddressFormatter;

it('formats Haitian addresses with the HT postcode left of the locality', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Samba 1',
        'city' => 'DELMAS',
        'postcode' => 'HT6120',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Samba 1\nHT6120 DELMAS\nHaiti");
});
it('formats Haitian capital addresses with the Port-au-Prince postcode', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Capois 5',
        'city' => 'PORT-AU-PRINCE',
        'postcode' => 'HT6110',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Capois 5\nHT6110 PORT-AU-PRINCE\nHaiti");
});
