<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ukraine\UkraineAddressFormatter;

it('formats Ukrainian addresses with locality, oblast and postcode lines', function (): void {
    $formatted = app(UkraineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'vul. Khreshchatyk, 22',
        'city' => 'KYIV',
        'postcode' => '01055',
        'country_code' => 'UA',
    ]));

    expect($formatted)->toBe("vul. Khreshchatyk, 22\nKYIV\n01055\nUkraine");
});
