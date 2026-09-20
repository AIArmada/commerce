<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;

it('formats Lebanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'line2' => 'Australia Street',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'postcode' => '1107 2080',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nAustralia Street\nRaoucheh 1107 2080\nBeirut\nLebanon");
});
it('formats Lebanese addresses without a postcode when missing', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nRaoucheh\nBeirut\nLebanon");
});
