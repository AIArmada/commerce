<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Israel\IsraelAddressFormatter;

it('formats Israeli addresses with the 7-digit postcode left of the locality', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '16 Yafo Street',
        'city' => 'JERUSALEM',
        'postcode' => '9414219',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("16 Yafo Street\n9414219 JERUSALEM\nIsrael");
});
it('formats Israeli addresses passing legacy 5-digit codes through', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Rothschild Blvd',
        'city' => 'TEL AVIV',
        'postcode' => '61201',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("5 Rothschild Blvd\n61201 TEL AVIV\nIsrael");
});
