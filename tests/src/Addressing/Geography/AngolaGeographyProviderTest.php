<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Angola\AngolaAddressFormatter;

it('formats Angolan addresses without a postcode line', function (): void {
    $formatted = app(AngolaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Ndunduma 51',
        'city' => 'LUANDA',
        'country_code' => 'AO',
    ]));

    expect($formatted)->toBe("Rua Ndunduma 51\nLUANDA\nAngola");
});
