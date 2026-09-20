<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Palau\PalauAddressFormatter;

it('formats Palauan addresses with the US ZIP layout', function (): void {
    $formatted = app(PalauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 100',
        'city' => 'Koror',
        'postcode' => '96940',
        'country_code' => 'PW',
    ]));

    expect($formatted)->toBe("PO Box 100\nKoror PW 96940\nPalau");
});

it('formats Palau ZIP+4 codes', function (): void {
    $formatted = app(PalauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 7',
        'city' => 'Koror',
        'postcode' => '96940-0100',
        'country_code' => 'PW',
    ]));

    expect($formatted)->toBe("PO Box 7\nKoror PW 96940-0100\nPalau");
});
