<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jamaica\JamaicaAddressFormatter;

it('formats Jamaican addresses without a national postcode', function (): void {
    $formatted = app(JamaicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot 19 Mona Estate',
        'line2' => 'Bog Walk',
        'line3' => 'Bog Walk PO',
        'city' => 'St. Catherine',
        'country_code' => 'JM',
    ]));

    expect($formatted)->toBe("Lot 19 Mona Estate\nBog Walk\nBog Walk PO\nSt. Catherine\nJamaica");
});
it('formats Jamaican Kingston addresses keeping the sector in the locality', function (): void {
    $formatted = app(JamaicaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 Molynes Road',
        'city' => 'Kingston 10',
        'country_code' => 'JM',
    ]));

    expect($formatted)->toBe("15 Molynes Road\nKingston 10\nJamaica");
});
