<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;

it('formats Mauritian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => '10, rue Claude Delaître',
        'line2' => 'Les Guibies',
        'city' => 'PORT LOUIS',
        'postcode' => '11213',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("10, rue Claude Delaître\nLes Guibies\nPORT LOUIS 11213\nMauritius");
});
it('formats Rodriguan addresses with the R postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Solidarité',
        'city' => 'Port Mathurin',
        'state' => 'Rodrigues Island',
        'postcode' => 'R5135',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("Rue de la Solidarité\nPort Mathurin R5135\nRodrigues Island\nMauritius");
});
