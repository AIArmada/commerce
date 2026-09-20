<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;

it('formats Cape Verdean addresses with the postcode left of the locality', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'line2' => 'C.P. 38',
        'city' => 'PRAIA',
        'postcode' => '7600',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\nC.P. 38\n7600 PRAIA\nCape Verde");
});
it('formats Cape Verdean addresses passing 7-digit codes through', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'city' => 'PRAIA',
        'postcode' => '7600-120',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\n7600-120 PRAIA\nCape Verde");
});
