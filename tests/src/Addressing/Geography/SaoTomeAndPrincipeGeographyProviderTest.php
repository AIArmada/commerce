<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;

it('formats Santomean addresses without a postcode system', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\nSao Tome and Principe");
});
it('prints any supplied Santomean code on its own line', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'postcode' => '99999',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\n99999\nSao Tome and Principe");
});
