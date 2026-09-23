<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;

it('formats Comorian addresses without a postcode system', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 350',
        'city' => 'MORONI',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("BP 350\nMORONI\nComoros");
});
it('formats Comorian addresses with the island below the locality', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Corniche',
        'city' => 'Moroni',
        'state' => 'Grande Comore',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("Rue de la Corniche\nMoroni\nGrande Comore\nComoros");
});

it('ships 16 prefectures under islands with parent links', function (): void {
    $areas = app(ComorosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'prefecture');

    expect($l2)->toHaveCount(16)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('km:prefecture:moroni-bambao')->name)->toBe('Moroni-Bambao')
        ->and($byId->get('km:prefecture:mutsamudu')->name)->toBe('Mutsamudu')
        ->and($byId->get('km:prefecture:fomboni')->name)->toBe('Fomboni');
});

it('labels tiers Île and Préfecture', function (): void {
    $provider = app(ComorosGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['island' => 'Île', 'prefecture' => 'Préfecture'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
