<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;

it('formats Burkinabe addresses with the postcode left of the locality', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => '566 Avenue de la Nation',
        'city' => 'OUAGADOUGOU',
        'postcode' => '10010',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("566 Avenue de la Nation\n10010 OUAGADOUGOU\nBurkina Faso");
});
it('formats rural Burkinabe addresses with the region below the postcode line', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Secteur 3',
        'city' => 'TENKODOGO',
        'state' => 'Centre-Est',
        'postcode' => '70000',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("Secteur 3\n70000 TENKODOGO\nCentre-Est\nBurkina Faso");
});

it('nests all 47 provinces at level 2 under their regions', function (): void {
    $areas = app(BurkinaFasoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(64);

    $provinces = $areas->where('type', 'province');
    $regions = $areas->where('type', 'region')->keyBy('sourceId');

    expect($provinces)->toHaveCount(47)
        ->and($regions)->toHaveCount(17)
        ->and($provinces->every(fn ($area) => $area->level === 2 && $regions->has($area->parentSourceId)))->toBeTrue();

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('bf:province:koosin')->parentSourceId)->toBe('bf:region:sourou')
        ->and($byId->get('bf:province:dyamongou')->parentSourceId)->toBe('bf:region:tapoa')
        ->and($byId->get('bf:province:gobnangou')->parentSourceId)->toBe('bf:region:tapoa')
        ->and($byId->get('bf:province:karo-peli')->parentSourceId)->toBe('bf:region:soum')
        ->and($byId->get('bf:province:djelgodji')->parentSourceId)->toBe('bf:region:soum');
});

it('exposes the province level below the region level', function (): void {
    $levels = app(BurkinaFasoGeographyProvider::class)->addressHierarchies()[0]->levels;

    expect($levels)->toHaveCount(2)
        ->and($levels[0]->key)->toBe('region')
        ->and($levels[0]->kind)->toBe('state')
        ->and($levels[1]->key)->toBe('province')
        ->and($levels[1]->parentKey)->toBe('region');
});

it('labels tiers Région and Province', function (): void {
    $provider = app(BurkinaFasoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'province' => 'Province'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
