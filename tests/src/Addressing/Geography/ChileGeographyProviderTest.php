<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Chile\ChileAddressFormatter;

it('formats Chilean addresses with the postcode left of the commune', function (): void {
    $formatted = app(ChileAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Moneda 1152',
        'city' => 'SANTIAGO',
        'state' => 'REGION METROPOLITANA',
        'postcode' => '8340648',
        'country_code' => 'CL',
    ]));

    expect($formatted)->toBe("Moneda 1152\n8340648 SANTIAGO\nREGION METROPOLITANA\nChile");
});
it('formats Chilean Quilicura addresses with the block postcode', function (): void {
    $formatted = app(ChileAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Ossa 10',
        'city' => 'QUILICURA',
        'postcode' => '8720019',
        'country_code' => 'CL',
    ]));

    expect($formatted)->toBe("Av. Ossa 10\n8720019 QUILICURA\nChile");
});
