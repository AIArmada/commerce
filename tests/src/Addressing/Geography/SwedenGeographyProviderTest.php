<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Sweden\SwedenAddressFormatter;

it('formats Swedish addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'NYBY 10',
        'city' => 'LILLBYN',
        'postcode' => '123 45',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("NYBY 10\n123 45 LILLBYN\nSweden");
});
it('formats Swedish box addresses with the box postcode', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BOX 222',
        'city' => 'STOCKHOLM',
        'postcode' => '111 81',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("BOX 222\n111 81 STOCKHOLM\nSweden");
});
