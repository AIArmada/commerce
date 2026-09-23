<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;
use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;

it('formats Moroccan addresses with the postcode left of the locality', function (): void {
    $formatted = app(MoroccoAddressFormatter::class)->format(AddressData::from([
        'line1' => '23 BOULEVARD TAROUDANT',
        'city' => 'ERRACHIDIA',
        'postcode' => '52000',
        'country_code' => 'MA',
    ]));

    expect($formatted)->toBe("23 BOULEVARD TAROUDANT\n52000 ERRACHIDIA\nMorocco");
});

it('ships 13 prefectures and 62 provinces under regions with parent links', function (): void {
    $areas = app(MoroccoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(75)
        ->and($areas->where('type', 'prefecture'))->toHaveCount(13)
        ->and($areas->where('type', 'province'))->toHaveCount(62)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ma:prefecture:agadir-ida-ou-tanane')->name)->toBe('Agadir-Ida-Ou-Tanane')
        ->and($byId->get('ma:province:aousserd')->parentSourceId)->toBe('ma:region:dakhla-oued-ed-dahab');
});

it('labels tiers Région, Préfecture and Province', function (): void {
    $provider = app(MoroccoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'prefecture' => 'Préfecture', 'province' => 'Province'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
