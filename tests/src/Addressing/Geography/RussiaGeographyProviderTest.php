<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;

it('formats Russian addresses with the postcode below, country last', function (): void {
    $formatted = app(RussiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Lesnaya d. 5, kv.176',
        'city' => 'MOSKVA',
        'postcode' => '123456',
        'country_code' => 'RU',
    ]));

    expect($formatted)->toBe("ul. Lesnaya d. 5, kv.176\nMOSKVA\n123456\nRussia");
});
