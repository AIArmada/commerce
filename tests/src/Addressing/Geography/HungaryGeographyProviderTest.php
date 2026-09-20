<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Hungary\HungaryAddressFormatter;

it('formats Hungarian addresses with the postcode and town on one line', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIRÁG TÉR 3. IV. 61',
        'city' => 'BUDAPEST',
        'postcode' => '1037',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("VIRÁG TÉR 3. IV. 61\n1037 BUDAPEST\nHungary");
});
it('formats Hungarian post box addresses with the box postcode', function (): void {
    $formatted = app(HungaryAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PF. 83',
        'city' => 'DABAS',
        'postcode' => '2380',
        'country_code' => 'HU',
    ]));

    expect($formatted)->toBe("PF. 83\n2380 DABAS\nHungary");
});
