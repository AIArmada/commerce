<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;

it('formats Pakistani addresses with dash-separated postcodes', function (): void {
    $formatted = app(PakistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 17-B',
        'city' => 'ISLAMABAD',
        'postcode' => '44000',
        'country_code' => 'PK',
    ]));

    expect($formatted)->toBe("House No 17-B\nISLAMABAD-44000\nPakistan");
});
