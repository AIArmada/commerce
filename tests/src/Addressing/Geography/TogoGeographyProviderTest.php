<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;

it('formats Togolese addresses without a postcode system', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 526',
        'city' => 'LOME',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("B.P. 526\nLOME\nTogo");
});
it('formats Togolese addresses with the region below the locality', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue des Jasmins',
        'city' => 'Kpalimé',
        'state' => 'Plateaux',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("Rue des Jasmins\nKpalimé\nPlateaux\nTogo");
});

it('ships 39 prefectures under regions with parent links', function (): void {
    $areas = app(TogoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'prefecture');

    expect($l2)->toHaveCount(39)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tg:prefecture:blitta')->name)->toBe('Blitta')
        ->and($byId->get('tg:prefecture:golfe')->name)->toBe('Golfe')
        ->and($byId->get('tg:prefecture:assoli')->name)->toBe('Assoli');
});

it('labels tiers Région and Préfecture', function (): void {
    $provider = app(TogoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'prefecture' => 'Préfecture'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
