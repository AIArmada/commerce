<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Finland\FinlandAddressFormatter;

it('formats Finnish addresses with the postcode left of the locality', function (): void {
    $formatted = app(FinlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mäkelänkatu 25 B 13',
        'city' => 'HELSINKI',
        'postcode' => '00550',
        'country_code' => 'FI',
    ]));

    expect($formatted)->toBe("Mäkelänkatu 25 B 13\n00550 HELSINKI\nFinland");
});
it('formats Finnish post box addresses with the box postcode', function (): void {
    $formatted = app(FinlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PL 900',
        'city' => 'HELSINKI',
        'postcode' => '00101',
        'country_code' => 'FI',
    ]));

    expect($formatted)->toBe("PL 900\n00101 HELSINKI\nFinland");
});
