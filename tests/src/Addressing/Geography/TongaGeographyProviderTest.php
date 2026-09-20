<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tonga\TongaAddressFormatter;

it('formats Tongan addresses without a postcode system', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\nTonga");
});

it('prints any supplied Tongan code on its own line', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'postcode' => '99999',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\n99999\nTonga");
});
