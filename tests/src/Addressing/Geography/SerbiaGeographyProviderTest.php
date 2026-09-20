<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Serbia\SerbiaAddressFormatter;

it('formats Serbian addresses with the postcode left of the office', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Beogradska 3',
        'city' => 'BAJMOK',
        'postcode' => '24210',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Beogradska 3\n24210 BAJMOK\nSerbia");
});
it('prints matching Serbian city and capital once', function (): void {
    $formatted = app(SerbiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Knez Mihailova 10',
        'city' => 'Belgrade',
        'state' => 'Belgrade',
        'postcode' => '11130',
        'country_code' => 'RS',
    ]));

    expect($formatted)->toBe("Knez Mihailova 10\n11130 Belgrade\nSerbia");
});
