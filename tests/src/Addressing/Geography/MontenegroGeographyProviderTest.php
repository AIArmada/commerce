<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Montenegro\MontenegroAddressFormatter;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;

it('formats Montenegrin addresses with the postcode left of the locality', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Slobode br. 1',
        'city' => 'PODGORICA',
        'postcode' => '81000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Ul. Slobode br. 1\n81000 PODGORICA\nMontenegro");
});
it('formats Montenegrin coastal addresses with the town postcode', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jadranska magistrala 5',
        'city' => 'BAR',
        'postcode' => '85000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Jadranska magistrala 5\n85000 BAR\nMontenegro");
});

it('ships the 25 municipalities as terminal states', function (): void {
    $areas = app(MontenegroGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(25)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('me:municipality:bar')->name)->toBe('Bar');
});

it('labels the tier Opština', function (): void {
    $provider = app(MontenegroGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Opština'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
