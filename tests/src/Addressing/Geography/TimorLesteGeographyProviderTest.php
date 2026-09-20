<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;

it('formats Timorese addresses with the postcode right of the municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AVENIDA CAPITA SINMAU',
        'state' => 'AINARO',
        'postcode' => 'TL42000',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("AVENIDA CAPITA SINMAU\nAINARO TL42000\nTimor-Leste");
});
it('formats Timorese addresses joining distinct city and municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TRAVESSA LAVANDARIA NO.12',
        'city' => 'Bairo Pite',
        'state' => 'DILI',
        'postcode' => 'TL11212',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("TRAVESSA LAVANDARIA NO.12\nBairo Pite - DILI TL11212\nTimor-Leste");
});
it('prints Timorese city-municipalities once when city and state match', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida Presidente Nicolau Lobato',
        'city' => 'DILI',
        'state' => 'DILI',
        'postcode' => 'TL10901',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("Avenida Presidente Nicolau Lobato\nDILI TL10901\nTimor-Leste");
});
