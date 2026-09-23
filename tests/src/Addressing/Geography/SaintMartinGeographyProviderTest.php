<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinAddressFormatter;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinGeographyProvider;

it('formats Saint-Martinois addresses with the single code left of the locality', function (): void {
    $formatted = app(SaintMartinAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 RUE DES ACACIAS',
        'city' => 'SAINT-MARTIN',
        'postcode' => '97150',
        'country_code' => 'MF',
    ]));

    expect($formatted)->toBe("5 RUE DES ACACIAS\n97150 SAINT-MARTIN\nSaint-Martin (French part)");
});
it('formats Saint-Martinois Marigot addresses with the same single code', function (): void {
    $formatted = app(SaintMartinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la République 4',
        'city' => 'Marigot',
        'postcode' => '97150',
        'country_code' => 'MF',
    ]));

    expect($formatted)->toBe("Rue de la République 4\n97150 Marigot\nSaint-Martin (French part)");
});

it('ships the single collectivity as a terminal state', function (): void {
    $areas = app(SaintMartinGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(1)
        ->and($areas->first()->sourceId)->toBe('mf:overseas_collectivity:saint-martin');
});
