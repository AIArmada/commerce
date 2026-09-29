<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('ships 529 departments/partidos/comunas under provinces with parent links', function (): void {
    $areas = app(ArgentinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['department', 'partido', 'commune']);

    expect($l2)->toHaveCount(529)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ar:partido:la-matanza')->name)->toBe('La Matanza')
        ->and($byId->get('ar:commune:comuna-1')->name)->toBe('Comuna 1')
        ->and($byId->get('ar:partido:quilmes')->name)->toBe('Quilmes')
        ->and($byId->get('ar:department:islas-del-ibicuy')->name)->toBe('Islas del Ibicuy')
        ->and($byId->get('ar:department:san-salvador')->parentSourceId)->toBe('ar:province:entre-rios');
});

it('names the capital Ciudad Autónoma de Buenos Aires with the English alias', function (): void {
    $areas = app(ArgentinaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $names = app(ArgentinaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($areas->get('ar:city:autonomous-city-of-buenos-aires')->name)->toBe('Ciudad Autónoma de Buenos Aires')
        ->and($names['ar:city:autonomous-city-of-buenos-aires'][0])->toBe(['name' => 'Autonomous City of Buenos Aires', 'name_type' => 'alternative']);
});
