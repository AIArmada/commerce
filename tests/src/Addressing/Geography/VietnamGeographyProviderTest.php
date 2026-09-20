<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Vietnam\VietnamAddressFormatter;

it('formats Vietnamese addresses with the postcode right of the province', function (): void {
    $formatted = app(VietnamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No 5, Pham Hung Road',
        'city' => 'My Dinh 2 Ward',
        'state' => 'HANOI',
        'postcode' => '11517',
        'country_code' => 'VN',
    ]));

    expect($formatted)->toBe("No 5, Pham Hung Road\nMy Dinh 2 Ward\nHANOI 11517\nVietnam");
});
