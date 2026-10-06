<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('roles Belgrade as a city and county cities as municipalities', function (): void {
    $roles = app(SerbiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['rs:city:belgrade'][0]['role'])->toBe('city')
        ->and($roles['rs:city:bor'][0]['role'])->toBe('municipality');
});

it('ships 32 first-level areas and 161 second-level areas with the 4 B15 cities', function (): void {
    $areas = app(SerbiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(193)
        ->and($l1)->toHaveCount(32)
        ->and($l1->where('type', 'district'))->toHaveCount(29)
        ->and($l1->where('type', 'province'))->toHaveCount(2)
        ->and($l2)->toHaveCount(161)
        ->and($l2->where('type', 'municipality'))->toHaveCount(117)
        ->and($l2->where('type', 'city'))->toHaveCount(27)
        ->and($l2->where('type', 'city_municipality'))->toHaveCount(17)
        // B15 added cities (official units, wiki-table order 14/3/19/26).
        ->and($byId->get('rs:city:nis')->name)->toBe('Niš')
        ->and($byId->get('rs:city:nis')->parentSourceId)->toBe('rs:district:nisava')
        ->and($byId->get('rs:city:vranje')->name)->toBe('Vranje')
        ->and($byId->get('rs:city:vranje')->parentSourceId)->toBe('rs:district:pcinja')
        ->and($byId->get('rs:city:pozarevac')->name)->toBe('Požarevac')
        ->and($byId->get('rs:city:pozarevac')->parentSourceId)->toBe('rs:district:branicevo')
        ->and($byId->get('rs:city:uzice')->name)->toBe('Užice')
        ->and($byId->get('rs:city:uzice')->parentSourceId)->toBe('rs:district:zlatibor');
});

it('links 1411 postcodes with the B15 district-to-city moves and primary flips', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('RS', $dir . '/serbia-postal-codes.csv', $dir . '/serbia-postal-code-areas.csv', 'aiarmada.addressing.serbia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1411);

    $byCode = $postcodes->groupBy->code;

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // District-crutch legs moved to the 4 new cities (settlement + operator + OSM).
    expect($primary('12000'))->toBe('rs:city:pozarevac')
        ->and($primary('12208'))->toBe('rs:city:pozarevac')
        ->and($primary('17501'))->toBe('rs:city:vranje')
        ->and($primary('17554'))->toBe('rs:city:vranje')
        ->and($primary('18000'))->toBe('rs:city:nis')
        ->and($primary('18205'))->toBe('rs:city:nis')
        ->and($primary('31000'))->toBe('rs:city:uzice')
        ->and($primary('31311'))->toBe('rs:city:uzice')
        ->and($byCode->get('17521')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city:vranje', 'rs:municipality:bujanovac'])
        // Belgrade primary flips (operator streets + OSM polygons).
        ->and($primary('11118'))->toBe('rs:city_municipality:vracar')
        ->and($primary('11120'))->toBe('rs:city_municipality:zvezdara')
        ->and($primary('11158'))->toBe('rs:city_municipality:stari-grad')
        ->and($primary('11160'))->toBe('rs:city_municipality:zvezdara')
        // Rural primary flips (settlement membership + GN web / operator).
        ->and($primary('15226'))->toBe('rs:municipality:koceljeva')
        ->and($primary('37202'))->toBe('rs:city:krusevac')
        ->and($primary('37233'))->toBe('rs:municipality:aleksandrovac')
        ->and($primary('18411'))->toBe('rs:municipality:doljevac')
        // 11102 dual + L1-generic refinements.
        ->and($byCode->get('11102')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city_municipality:savski-venac', 'rs:city_municipality:stari-grad'])
        ->and($primary('11150'))->toBe('rs:city_municipality:novi-beograd')
        ->and($primary('11167'))->toBe('rs:city_municipality:vracar');

    // 2026-10-06 retry: 7 verdicts (PAK + PlanPlus + OSM + addressed usage).
    expect($primary('11040'))->toBe('rs:city_municipality:savski-venac')
        ->and($byCode->get('11040')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city_municipality:savski-venac', 'rs:city_municipality:vozdovac'])
        ->and($primary('11050'))->toBe('rs:city_municipality:zvezdara')
        ->and($byCode->get('11050')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city_municipality:vozdovac', 'rs:city_municipality:zvezdara'])
        ->and($primary('17508'))->toBe('rs:city:vranje')
        ->and($byCode->get('17508')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city:vranje', 'rs:municipality:trgoviste'])
        ->and($primary('18110'))->toBe('rs:city:nis')
        ->and($primary('18251'))->toBe('rs:city:nis')
        ->and($byCode->get('18251')->map->areaSourceId->sort()->values()->all())
        ->toBe(['rs:city:nis', 'rs:district:nisava'])
        ->and($byCode->get('18252')->map->areaSourceId->all())->toBe(['rs:municipality:merosina'])
        ->and($byCode->get('18411')->map->areaSourceId->all())->toBe(['rs:municipality:doljevac']);

    // 2026-10-06 retry: L1 branch-tail spot pins (full 91 in the gate).
    expect($primary('11011'))->toBe('rs:city_municipality:vozdovac')
        ->and($primary('11032'))->toBe('rs:city_municipality:cukarica')
        ->and($primary('11165'))->toBe('rs:city_municipality:rakovica')
        ->and($primary('11189'))->toBe('rs:city_municipality:novi-beograd')
        ->and($primary('11281'))->toBe('rs:city_municipality:zemun')
        ->and($primary('11161'))->toBe('rs:city:belgrade');
});

it('fills 7 operator-confirmed delivery codes and keeps the verified attributions', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('RS', $dir . '/serbia-postal-codes.csv', $dir . '/serbia-postal-code-areas.csv', 'aiarmada.addressing.serbia');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Fills (Pošta PAK streets + OSM postcode hits).
    expect($primary('18101'))->toBe('rs:city:nis')
        ->and($primary('18103'))->toBe('rs:city:nis')
        ->and($primary('18104'))->toBe('rs:city:nis')
        ->and($primary('18105'))->toBe('rs:city:nis')
        ->and($primary('31109'))->toBe('rs:city:uzice')
        ->and($primary('11042'))->toBe('rs:city_municipality:vozdovac')
        ->and($primary('11197'))->toBe('rs:city_municipality:novi-beograd')
        // Verified keeps (operator-confirmed, OSM rejected).
        ->and($primary('11231'))->toBe('rs:city_municipality:rakovica')
        ->and($primary('11102'))->toBe('rs:city_municipality:stari-grad')
        ->and($primary('11060'))->toBe('rs:city_municipality:palilula')
        ->and($primary('11080'))->toBe('rs:city_municipality:zemun')
        ->and($primary('24414'))->toBe('rs:city:subotica');
});
