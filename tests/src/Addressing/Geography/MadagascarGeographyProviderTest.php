<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 114 districts under regions with parent links', function (): void {
    $areas = app(MadagascarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('type', 'district');

    expect($l3)->toHaveCount(114)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mg:district:antananarivo-renivohitra')->name)->toBe('Antananarivo-Renivohitra')
        ->and($byId->get('mg:district:toamasina-i')->parentSourceId)->toBe('mg:region:atsinanana')
        ->and($byId->get('mg:district:maroantsetra')->parentSourceId)->toBe('mg:region:ambatosoa');
});

it('ships 6 ISO provinces with 24 regions on the WP table scheme', function (): void {
    $areas = app(MadagascarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('level', 3);

    expect($areas->where('level', 1))->toHaveCount(6)
        ->and($areas->where('level', 2))->toHaveCount(24)
        // ISO 3166-2:MG province codes.
        ->and($byId->get('mg:province:antananarivo')->code)->toBe('T')
        ->and($byId->get('mg:province:antsiranana')->code)->toBe('D')
        ->and($byId->get('mg:province:fianarantsoa')->code)->toBe('F')
        ->and($byId->get('mg:province:mahajanga')->code)->toBe('M')
        ->and($byId->get('mg:province:toamasina')->code)->toBe('A')
        ->and($byId->get('mg:province:toliara')->code)->toBe('U')
        // Per-region district counts (WP districts table, zero-diff).
        ->and($l3->where('parentSourceId', 'mg:region:alaotra-mangoro'))->toHaveCount(5)
        ->and($l3->where('parentSourceId', 'mg:region:ambatosoa'))->toHaveCount(2)
        ->and($l3->where('parentSourceId', 'mg:region:amoron-i-mania'))->toHaveCount(4)
        ->and($l3->where('parentSourceId', 'mg:region:analamanga'))->toHaveCount(8)
        ->and($l3->where('parentSourceId', 'mg:region:analanjirofo'))->toHaveCount(4)
        ->and($l3->where('parentSourceId', 'mg:region:androy'))->toHaveCount(4)
        ->and($l3->where('parentSourceId', 'mg:region:anosy'))->toHaveCount(3)
        ->and($l3->where('parentSourceId', 'mg:region:atsimo-andrefana'))->toHaveCount(9)
        ->and($l3->where('parentSourceId', 'mg:region:atsimo-atsinanana'))->toHaveCount(5)
        ->and($l3->where('parentSourceId', 'mg:region:atsinanana'))->toHaveCount(7)
        ->and($l3->where('parentSourceId', 'mg:region:betsiboka'))->toHaveCount(3)
        ->and($l3->where('parentSourceId', 'mg:region:boeny'))->toHaveCount(6)
        ->and($l3->where('parentSourceId', 'mg:region:bongolava'))->toHaveCount(2)
        ->and($l3->where('parentSourceId', 'mg:region:diana'))->toHaveCount(5)
        ->and($l3->where('parentSourceId', 'mg:region:fitovinany'))->toHaveCount(3)
        ->and($l3->where('parentSourceId', 'mg:region:ihorombe'))->toHaveCount(3)
        ->and($l3->where('parentSourceId', 'mg:region:itasy'))->toHaveCount(3)
        ->and($l3->where('parentSourceId', 'mg:region:matsiatra-ambony'))->toHaveCount(7)
        ->and($l3->where('parentSourceId', 'mg:region:melaky'))->toHaveCount(5)
        ->and($l3->where('parentSourceId', 'mg:region:menabe'))->toHaveCount(5)
        ->and($l3->where('parentSourceId', 'mg:region:sava'))->toHaveCount(4)
        ->and($l3->where('parentSourceId', 'mg:region:sofia'))->toHaveCount(7)
        ->and($l3->where('parentSourceId', 'mg:region:vakinankaratra'))->toHaveCount(7)
        ->and($l3->where('parentSourceId', 'mg:region:vatovavy'))->toHaveCount(3)
        // Malagasy region form + Ambatosoa split pair.
        ->and($byId->get('mg:region:matsiatra-ambony')->name)->toBe('Matsiatra Ambony')
        ->and($byId->get('mg:district:mananara-avaratra')->parentSourceId)->toBe('mg:region:ambatosoa');
});

it('links 110 postcodes at district grain with 4 sealed shared codes', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MG', $dir . '/madagascar-postal-codes.csv', $dir . '/madagascar-postal-code-areas.csv', 'aiarmada.addressing.madagascar');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(114)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(110);

    $byCode = $postcodes->groupBy->code;

    // Sealed multis: both partners carry the code in their WP articles.
    $legs113 = $byCode->get('113')->keyBy->areaSourceId;
    expect($legs113->get('mg:district:betafo')->isPrimary)->toBeTrue()
        ->and($legs113->get('mg:district:mandoto')->isPrimary)->toBeFalse();

    $legs303 = $byCode->get('303')->keyBy->areaSourceId;
    expect($legs303->get('mg:district:ambalavao')->isPrimary)->toBeTrue()
        ->and($legs303->get('mg:district:lalangina')->isPrimary)->toBeFalse();

    $legs305 = $byCode->get('305')->keyBy->areaSourceId;
    expect($legs305->get('mg:district:ambohimahasoa')->isPrimary)->toBeTrue()
        ->and($legs305->get('mg:district:vohibato')->isPrimary)->toBeFalse();

    $legs314 = $byCode->get('314')->keyBy->areaSourceId;
    expect($legs314->get('mg:district:ikalamavony')->isPrimary)->toBeTrue()
        ->and($legs314->get('mg:district:isandra')->isPrimary)->toBeFalse();

    // UPU + city/commune + directory anchors (102/103 split sealed by
    // 10 Avaradrano commune articles against one stale district page).
    expect($byCode->get('101')->sole()->areaSourceId)->toBe('mg:district:antananarivo-renivohitra')
        ->and($byCode->get('102')->sole()->areaSourceId)->toBe('mg:district:antananarivo-atsimondrano')
        ->and($byCode->get('103')->sole()->areaSourceId)->toBe('mg:district:antananarivo-avaradrano')
        ->and($byCode->get('501')->sole()->areaSourceId)->toBe('mg:district:toamasina-i')
        ->and($byCode->get('601')->sole()->areaSourceId)->toBe('mg:district:toliara-i');

    // 2026-10-06 retry headline seals (WP town/commune + OSM calculated +
    // addressed usage over the French-list single lineage) + 3 residual holds.
    expect($byCode->get('207')->sole()->areaSourceId)->toBe('mg:district:nosy-be')
        ->and($byCode->get('401')->sole()->areaSourceId)->toBe('mg:district:mahajanga-i')
        ->and($byCode->get('602')->sole()->areaSourceId)->toBe('mg:district:toliara-ii')
        ->and($byCode->get('614')->sole()->areaSourceId)->toBe('mg:district:taolagnaro')
        ->and($byCode->get('515')->sole()->areaSourceId)->toBe('mg:district:nosy-boraha')
        ->and($byCode->get('516')->sole()->areaSourceId)->toBe('mg:district:soanierana-ivongo')
        ->and($byCode->get('612')->sole()->areaSourceId)->toBe('mg:district:betioky-atsimo')
        ->and($byCode->get('111')->sole()->areaSourceId)->toBe('mg:district:antsirabe-ii')
        ->and($byCode->get('323')->sole()->areaSourceId)->toBe('mg:district:manandriana')
        ->and($byCode->get('606')->sole()->areaSourceId)->toBe('mg:district:ankazoabo-atsimo');
});
