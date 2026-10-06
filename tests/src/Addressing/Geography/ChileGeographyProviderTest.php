<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Chile\ChileGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 16 ISO regions with 56 provinces and parent links', function (): void {
    $areas = app(ChileGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($l1->where('type', 'region'))->toHaveCount(16)
        ->and($l2->where('type', 'province'))->toHaveCount(56)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'cl:region:nuble')->sortBy->sourceId->pluck('sourceId')->values()->all())
        ->toBe(['cl:province:diguillin', 'cl:province:itata', 'cl:province:punilla'])
        ->and($l2->where('parentSourceId', 'cl:region:valparaiso'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'cl:region:region-metropolitana-de-santiago'))->toHaveCount(6)
        ->and($byId->get('cl:province:el-ranco')->parentSourceId)->toBe('cl:region:los-rios');
});

it('pins the 16 ISO 3166-2:CL region codes', function (): void {
    $areas = app(ChileGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $iso = [
        'cl:region:aisen-del-general-carlos-ibanez-del-campo' => 'AI',
        'cl:region:antofagasta' => 'AN',
        'cl:region:arica-y-parinacota' => 'AP',
        'cl:region:atacama' => 'AT',
        'cl:region:biobio' => 'BI',
        'cl:region:coquimbo' => 'CO',
        'cl:region:la-araucania' => 'AR',
        'cl:region:libertador-general-bernardo-ohiggins' => 'LI',
        'cl:region:los-lagos' => 'LL',
        'cl:region:los-rios' => 'LR',
        'cl:region:magallanes-y-de-la-antartica-chilena' => 'MA',
        'cl:region:maule' => 'ML',
        'cl:region:nuble' => 'NB',
        'cl:region:region-metropolitana-de-santiago' => 'RM',
        'cl:region:tarapaca' => 'TA',
        'cl:region:valparaiso' => 'VS',
    ];

    foreach ($iso as $id => $code) {
        expect($areas->get($id)->code)->toBe($code);
    }
});

it('spells the province Cautín with the accent per GeoNames and en/es WP', function (): void {
    $areas = app(ChileGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('cl:province:cautin')->name)->toBe('Cautín')
        ->and($areas->get('cl:province:cautin')->parentSourceId)->toBe('cl:region:la-araucania');
});

it('bundles the B10-verified 346-code 7-digit overlay with zero duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CL', $dir . '/chile-postal-codes.csv', $dir . '/chile-postal-code-areas.csv', 'aiarmada.addressing.chile');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(346)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(346)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(346)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{7}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Region anchors incl. the 65-prefix Villa Alemana outlier and the far south.
    expect($primary('1000000'))->toBe('cl:province:arica')
        ->and($primary('1100000'))->toBe('cl:province:iquique')
        ->and($primary('1240000'))->toBe('cl:province:antofagasta')
        ->and($primary('1700000'))->toBe('cl:province:elqui')
        ->and($primary('2340000'))->toBe('cl:province:valparaiso')
        ->and($primary('2770000'))->toBe('cl:province:isla-de-pascua')
        ->and($primary('6500000'))->toBe('cl:province:marga-marga')
        ->and($primary('4780000'))->toBe('cl:province:cautin')
        ->and($primary('6000000'))->toBe('cl:province:aysen')
        ->and($primary('6200000'))->toBe('cl:province:magallanes')
        ->and($primary('6350000'))->toBe('cl:province:antartica-chilena')
        ->and($primary('7500000'))->toBe('cl:province:santiago');

    // Full 21-code Ñuble split (Law 21.033): Diguillín 9 / Punilla 5 / Itata 7.
    expect($primary('3780000'))->toBe('cl:province:diguillin')
        ->and($primary('3820000'))->toBe('cl:province:diguillin')
        ->and($primary('3880000'))->toBe('cl:province:diguillin')
        ->and($primary('3890000'))->toBe('cl:province:diguillin')
        ->and($primary('3900000'))->toBe('cl:province:diguillin')
        ->and($primary('3910000'))->toBe('cl:province:diguillin')
        ->and($primary('3920000'))->toBe('cl:province:diguillin')
        ->and($primary('3930000'))->toBe('cl:province:diguillin')
        ->and($primary('3940000'))->toBe('cl:province:diguillin')
        ->and($primary('3840000'))->toBe('cl:province:punilla')
        ->and($primary('3850000'))->toBe('cl:province:punilla')
        ->and($primary('3860000'))->toBe('cl:province:punilla')
        ->and($primary('3870000'))->toBe('cl:province:punilla')
        ->and($primary('4020000'))->toBe('cl:province:punilla')
        ->and($primary('3950000'))->toBe('cl:province:itata')
        ->and($primary('3960000'))->toBe('cl:province:itata')
        ->and($primary('3970000'))->toBe('cl:province:itata')
        ->and($primary('3980000'))->toBe('cl:province:itata')
        ->and($primary('3990000'))->toBe('cl:province:itata')
        ->and($primary('4000000'))->toBe('cl:province:itata')
        ->and($primary('4010000'))->toBe('cl:province:itata');

    // es-annex gaps correctly included: Torres del Paine + San Pedro (Melipilla).
    expect($primary('6170000'))->toBe('cl:province:ultima-esperanza')
        ->and($primary('9660000'))->toBe('cl:province:melipilla');

    // Labranza 4810000 is a Temuco locality, not a commune: correctly absent.
    expect($byCode->has('4810000'))->toBeFalse();

    // Zero duals: every code links exactly once, all primary.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->all();
    expect($multi)->toBe([]);

    // Per-province primary counts pin the Ñuble split + the WP-typo province.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts->get('cl:province:diguillin'))->toBe(9)
        ->and($counts->get('cl:province:punilla'))->toBe(5)
        ->and($counts->get('cl:province:itata'))->toBe(7)
        ->and($counts->get('cl:province:cautin'))->toBe(21)
        ->and($counts->get('cl:province:santiago'))->toBe(32)
        ->and($counts->get('cl:province:concepcion'))->toBe(12)
        ->and($counts->get('cl:province:petorca'))->toBe(5)
        ->and($counts->get('cl:province:melipilla'))->toBe(5)
        ->and($counts->get('cl:province:ultima-esperanza'))->toBe(2);
});
