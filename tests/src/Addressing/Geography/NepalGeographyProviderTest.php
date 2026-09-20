<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;

it('formats Nepali addresses with the postcode right of the locality', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'line2' => 'Baneshwore',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'postcode' => '44601',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nBaneshwore\nKATHMANDU 44601\nBagmati\nNepal");
});
it('formats Nepali addresses without a postcode when missing', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nKATHMANDU\nBagmati\nNepal");
});
