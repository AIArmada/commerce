<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonAddressFormatter;

it('formats Saint Pierre and Miquelon addresses with the code left of the locality', function (): void {
    $formatted = app(SaintPierreAndMiquelonAddressFormatter::class)->format(AddressData::from([
        'line1' => '24 rue de Paris',
        'city' => 'Saint-Pierre',
        'postcode' => '97500',
        'country_code' => 'PM',
    ]));

    expect($formatted)->toBe("24 rue de Paris\n97500 Saint-Pierre\nSaint Pierre and Miquelon");
});

it('uses the same code for Miquelon', function (): void {
    $formatted = app(SaintPierreAndMiquelonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Chapelle',
        'city' => 'Miquelon',
        'postcode' => '97500',
        'country_code' => 'PM',
    ]));

    expect($formatted)->toBe("Rue de la Chapelle\n97500 Miquelon\nSaint Pierre and Miquelon");
});
