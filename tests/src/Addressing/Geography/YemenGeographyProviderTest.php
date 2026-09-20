<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;

it('formats Yemeni addresses without a postcode system', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 1993',
        'city' => "SANA'A",
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("B.P. 1993\nSANA'A\nYemen");
});
it('prints matching Yemeni city and governorate once', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Wahda Street 2',
        'city' => 'Ibb',
        'state' => 'Ibb',
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("Al Wahda Street 2\nIbb\nYemen");
});
