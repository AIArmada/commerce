<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Barbados\BarbadosAddressFormatter;

it('formats Barbadian addresses with the postcode right of the parish', function (): void {
    $formatted = app(BarbadosAddressFormatter::class)->format(AddressData::from([
        'line1' => '#35, "The Grotto"',
        'line2' => '04th Ave., Cherry Lane',
        'line3' => 'Farm Road',
        'state' => 'St. Peter',
        'postcode' => 'BB26028',
        'country_code' => 'BB',
    ]));

    expect($formatted)->toBe("#35, \"The Grotto\"\n04th Ave., Cherry Lane\nFarm Road\nSt. Peter BB26028\nBarbados");
});
it('formats Barbadian GPO addresses with the Cheapside postcode', function (): void {
    $formatted = app(BarbadosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'General Post Office',
        'line2' => 'Cheapside',
        'city' => 'Bridgetown',
        'state' => 'St. Michael',
        'postcode' => 'BB11000',
        'country_code' => 'BB',
    ]));

    expect($formatted)->toBe("General Post Office\nCheapside\nBridgetown\nSt. Michael BB11000\nBarbados");
});
