<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ecuador\EcuadorAddressFormatter;

it('formats Ecuadorian addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Francisco Dalmau 547 y Calle 2',
        'line2' => 'Conjunto Real Audiencia, Bloque 3',
        'line3' => 'Conocoto',
        'city' => 'QUITO',
        'postcode' => '170303',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Francisco Dalmau 547 y Calle 2\nConjunto Real Audiencia, Bloque 3\nConocoto\n170303 - QUITO\nEcuador");
});
it('formats Ecuadorian Guayaquil addresses with the zone postcode', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. 9 de Octubre 100',
        'city' => 'GUAYAQUIL',
        'postcode' => '090306',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Av. 9 de Octubre 100\n090306 - GUAYAQUIL\nEcuador");
});
