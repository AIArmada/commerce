<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;

it('formats Jordanian addresses with the postcode right of the locality', function (): void {
    $formatted = app(JordanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Mohazab Al Halabi',
        'city' => 'AMMAN',
        'postcode' => '11937',
        'country_code' => 'JO',
    ]));

    expect($formatted)->toBe("Al Mohazab Al Halabi\nAMMAN 11937\nJordan");
});
