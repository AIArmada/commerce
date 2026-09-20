<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\HongKong\HongKongAddressFormatter;

it('formats Hong Kong addresses without a postcode system', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Flat 25, 12/F',
        'line2' => 'Acacia Building',
        'line3' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("Flat 25, 12/F\nAcacia Building\n150 Kennedy Road\nWAN CHAI\nHong Kong");
});
it('prints any forced Hong Kong code on its own line', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'postcode' => '000',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("150 Kennedy Road\nWAN CHAI\n000\nHong Kong");
});
