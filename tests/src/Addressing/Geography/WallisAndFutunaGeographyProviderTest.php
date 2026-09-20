<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaAddressFormatter;

it('formats Wallis and Futuna addresses with the code left of the locality', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Route de Mata-Utu',
        'city' => 'MATA-UTU',
        'postcode' => '98600',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("Route de Mata-Utu\n98600 MATA-UTU\nWallis and Futuna Islands");
});

it('formats Futuna addresses with their own code', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 5',
        'city' => 'LEAVA',
        'postcode' => '98620',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("BP 5\n98620 LEAVA\nWallis and Futuna Islands");
});
