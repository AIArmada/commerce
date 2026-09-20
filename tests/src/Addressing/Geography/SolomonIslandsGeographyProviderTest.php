<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsAddressFormatter;

it('formats Solomon Islands addresses without a postcode system', function (): void {
    $formatted = app(SolomonIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Honiara',
        'country_code' => 'SB',
    ]));

    expect($formatted)->toBe("PO Box 1\nHoniara\nSolomon Islands");
});

it('prints any supplied Honiara code on its own line', function (): void {
    $formatted = app(SolomonIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Honiara',
        'postcode' => '99999',
        'country_code' => 'SB',
    ]));

    expect($formatted)->toBe("PO Box 1\nHoniara\n99999\nSolomon Islands");
});
