<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Grenada\GrenadaAddressFormatter;

it('formats Grenadian addresses without a postcode system', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Woburn',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("Woburn\nST. GEORGE'S\nGrenada");
});
it('formats Grenadian box addresses with the municipality only', function (): void {
    $formatted = app(GrenadaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 1234',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD',
    ]));

    expect($formatted)->toBe("P.O. BOX 1234\nST. GEORGE'S\nGrenada");
});
