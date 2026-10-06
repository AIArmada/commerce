<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('exposes corrected Estonian municipality names', function (): void {
    $areas = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ee:rural_municipality:noo')->name)->toBe('Nõo')
        ->and($areas->get('ee:rural_municipality:noo')->code)->toBe('528')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->name)->toBe('Põhja-Pärnumaa')
        // B11: EHAK 638 -> 637 (01.01.2025 boundary change with Tori).
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->code)->toBe('637')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->type)->toBe('rural_municipality')
        ->and($areas->get('ee:rural_municipality:poltsamaa')->name)->toBe('Põltsamaa')
        ->and($areas->get('ee:rural_municipality:joelahtme')->name)->toBe('Jõelähtme')
        ->and($areas->get('ee:rural_municipality:joelahtme')->code)->toBe('245')
        ->and($areas->has('ee:rural_municipality:pohja-parnu'))->toBeFalse();
});

it('ships 15 EHAK counties with 78 post-merger municipalities', function (): void {
    $areas = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(93)
        ->and($l1->where('type', 'county'))->toHaveCount(15)
        ->and($l2->where('type', 'rural_municipality'))->toHaveCount(63)
        ->and($l2->where('type', 'urban_municipality'))->toHaveCount(15)
        ->and($l1->sortBy->code->pluck('code')->values()->all())
        ->toBe(['37', '39', '45', '50', '52', '56', '60', '64', '68', '71', '74', '79', '81', '84', '87'])
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        // Toila retired 28.11.2025 (merged into Jõhvi); nothing references it.
        ->and($areas->first(fn ($a) => str_contains((string) $a->sourceId, 'toila')))->toBeNull()
        ->and($l2->where('parentSourceId', 'ee:county:harju'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'ee:county:tartu'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'ee:county:laane-viru'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'ee:county:ida-viru'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'ee:county:parnu'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'ee:county:voru'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'ee:county:rapla'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'ee:county:viljandi'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'ee:county:hiiu'))->toHaveCount(1);
});

it('pins the B11 current-EHAK municipality codes', function (): void {
    $areas = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Triple-oracle pins (Maa-amet ADS + EMTA KOV table + stat.ee EHAK changes).
    $fixed = [
        // Build-time swap, wrong since inception (EHAK 431/430; GeoNames agrees).
        'ee:rural_municipality:laane-harju' => '431',
        'ee:rural_municipality:laaneranna' => '430',
        // Post-merger code (Toila -> Jõhvi 28.11.2025).
        'ee:rural_municipality:johvi' => '250',
        // Boundary-change recodes 2020-2025.
        'ee:rural_municipality:antsla' => '145',
        'ee:rural_municipality:marjamaa' => '502',
        'ee:urban_municipality:narva-joesuu' => '515',
        'ee:rural_municipality:saue' => '725',
        'ee:urban_municipality:sillamae' => '736',
        'ee:rural_municipality:tori' => '806',
        'ee:rural_municipality:valga' => '857',
    ];

    foreach ($fixed as $id => $code) {
        expect($areas->get($id)->code)->toBe($code);
    }

    // Neighbours that did NOT move (2019 Kiili/Saku recodes already bundled).
    expect($areas->get('ee:rural_municipality:kiili')->code)->toBe('305')
        ->and($areas->get('ee:rural_municipality:saku')->code)->toBe('719')
        ->and($areas->get('ee:urban_municipality:narva')->code)->toBe('511')
        ->and($areas->get('ee:urban_municipality:tallinn')->code)->toBe('784');
});

it('bundles the B11-verified 5481-code overlay with 17 duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('EE', $dir . '/estonia-postal-codes.csv', $dir . '/estonia-postal-code-areas.csv', 'aiarmada.addressing.estonia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(5499)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(5481)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(5481)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // City anchors incl. town-set extras and ex-Toila remaps.
    expect($primary('10001'))->toBe('ee:urban_municipality:tallinn')
        ->and($primary('20308'))->toBe('ee:urban_municipality:narva')
        ->and($primary('76601'))->toBe('ee:urban_municipality:keila')
        ->and($primary('74801'))->toBe('ee:urban_municipality:loksa')
        ->and($primary('74101'))->toBe('ee:urban_municipality:maardu')
        ->and($primary('41702'))->toBe('ee:rural_municipality:johvi')
        ->and($primary('90501'))->toBe('ee:urban_municipality:haapsalu');

    // B11 fill pins (postiindeks.ee + ADS agreed).
    expect($primary('21026'))->toBe('ee:urban_municipality:narva')
        ->and($primary('21076'))->toBe('ee:urban_municipality:narva')
        ->and($primary('91201'))->toBe('ee:rural_municipality:laane-nigula')
        ->and($primary('91233'))->toBe('ee:rural_municipality:laane-nigula')
        ->and($primary('13525'))->toBe('ee:urban_municipality:tallinn')
        ->and($primary('66304'))->toBe('ee:rural_municipality:antsla')
        ->and($primary('74022'))->toBe('ee:rural_municipality:viimsi')
        ->and($primary('92141'))->toBe('ee:rural_municipality:hiiumaa');

    // Holds stay absent: single-signal pii codes + unassigned gaps.
    foreach (['15050', '15172', '41598', '43299', '50050', '50096', '66710', '80099', '86217', '21065', '91209', '91222'] as $hold) {
        expect($byCode->has($hold))->toBeFalse();
    }

    // Exact 17-code dual set (16 doubles + the 74115 triple).
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->map(static fn ($k): string => (string) $k)->sort()->values()->all();
    expect($multi)->toBe(['10112', '11218', '30503', '41534', '41537', '41538', '41542', '41551', '45202', '60532', '65555', '70101', '74114', '74115', '76902', '76912', '85009'])
        ->and($primary('30503'))->toBe('ee:urban_municipality:kohtla-jarve')
        ->and($primary('74115'))->toBe('ee:urban_municipality:maardu')
        ->and($primary('45202'))->toBe('ee:rural_municipality:haljala')
        ->and($byCode->get('74115')->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['ee:rural_municipality:joelahtme', 'ee:urban_municipality:tallinn']);

    // Per-county primary counts pin the fill distribution.
    $parents = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $countyOf = static fn (string $sid): string => (string) $parents->get($sid)->parentSourceId;
    $counts = $postcodes->where('isPrimary', true)->countBy(fn ($row): string => $countyOf((string) $row->areaSourceId));
    expect($counts->get('ee:county:harju'))->toBe(733)
        ->and($counts->get('ee:county:ida-viru'))->toBe(332)
        ->and($counts->get('ee:county:laane'))->toBe(209)
        ->and($counts->get('ee:county:tartu'))->toBe(471)
        ->and($counts->get('ee:county:voru'))->toBe(674)
        ->and($counts->get('ee:county:saare'))->toBe(508);

    // Per-municipality pins incl. the filled towns.
    $muni = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($muni->get('ee:urban_municipality:narva'))->toBe(85)
        ->and($muni->get('ee:rural_municipality:laane-nigula'))->toBe(124)
        ->and($muni->get('ee:urban_municipality:tallinn'))->toBe(255)
        ->and($muni->get('ee:rural_municipality:saaremaa'))->toBe(455)
        ->and($muni->get('ee:rural_municipality:ruhnu'))->toBe(1);
});
