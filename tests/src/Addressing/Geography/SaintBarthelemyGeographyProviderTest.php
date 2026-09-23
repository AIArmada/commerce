<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyAddressFormatter;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;

it('formats Barthélemois addresses with the single code left of the locality', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 RUE VICTOR HUGO',
        'city' => 'SAINT-BARTHELEMY',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("5 RUE VICTOR HUGO\n97133 SAINT-BARTHELEMY\nSaint-Barthelemy");
});
it('formats Barthélemois Gustavia addresses with the same single code', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la République 2',
        'city' => 'Gustavia',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("Rue de la République 2\n97133 Gustavia\nSaint-Barthelemy");
});

it('ships the single collectivity as a terminal state', function (): void {
    $areas = app(SaintBarthelemyGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(1)
        ->and($areas->first()->sourceId)->toBe('bl:overseas_collectivity:saint-barthelemy');
});
