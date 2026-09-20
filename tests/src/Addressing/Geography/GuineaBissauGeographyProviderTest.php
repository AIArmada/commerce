<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;

it('formats Bissau-Guinean addresses with the postcode left of the locality', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'postcode' => '1000',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\n1000 BISSAU\nGuinea-Bissau");
});
it('formats Bissau-Guinean addresses without a postcode when missing', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\nBISSAU\nGuinea-Bissau");
});
