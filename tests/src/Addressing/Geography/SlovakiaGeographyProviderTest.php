<?php

declare(strict_types=1);

// B11 verification pins for Slovakia (no prior test file existed).
// Post-state: 8 regions + 79 districts; 3480 codes / 3514 links;
// 215 Košice-city office codes stay at region level, 114 KI office
// codes moved to district level (see gate_sk.py / doc05.txt).

use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Slovakia tree of 8 regions and 79 districts', function (): void {
    $areas = app(SlovakiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(87)
        ->and($areas->where('type', 'region'))->toHaveCount(8)
        ->and($areas->where('type', 'district'))->toHaveCount(79)
        ->and($areas->where('level', 1))->toHaveCount(8)
        ->and($areas->where('level', 2))->toHaveCount(79);

    // ISO 3166-2:SK region codes.
    expect($areas->where('level', 1)->pluck('code')->sort()->values()->all())->toBe(
        ['BC', 'BL', 'KI', 'NI', 'PV', 'TA', 'TC', 'ZI']
    );

    // Per-region district membership: 13/8/11/7/13/9/7/11.
    expect($areas->where('parentSourceId', 'sk:region:banska-bystrica'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'sk:region:bratislava'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'sk:region:kosice'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'sk:region:nitra'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sk:region:presov'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'sk:region:trencin'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'sk:region:trnava'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sk:region:zilina'))->toHaveCount(11);

    // Urban districts parent to the city regions.
    expect($byId->get('sk:district:bratislava-i')->parentSourceId)->toBe('sk:region:bratislava')
        ->and($byId->get('sk:district:bratislava-v')->parentSourceId)->toBe('sk:region:bratislava')
        ->and($byId->get('sk:district:kosice-i')->parentSourceId)->toBe('sk:region:kosice')
        ->and($byId->get('sk:district:kosice-iv')->parentSourceId)->toBe('sk:region:kosice')
        ->and($byId->get('sk:district:kosice-okolie')->parentSourceId)->toBe('sk:region:kosice');
});

it('bundles the 3480 GeoNames SK codes as 3514 district/region links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SK', $dir . '/slovakia-postal-codes.csv', $dir . '/slovakia-postal-code-areas.csv', 'aiarmada.addressing.slovakia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3514)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(3480)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(34)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(3480)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{3} \d{2}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->min())->toBe('010 01')
        ->and($postcodes->pluck('code')->max())->toBe('992 14');

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Every code carries exactly one primary; 33 codes multi-linked (32 dual + 1 triple).
    expect($byCode->every(static fn ($rows): bool => $rows->where('isPrimary', true)->count() === 1))->toBeTrue()
        ->and($byCode->filter(static fn ($rows): bool => $rows->count() > 1))->toHaveCount(33);

    // UPU SVK example pins.
    expect($primary('010 01'))->toBe('sk:district:zilina')
        ->and($primary('960 01'))->toBe('sk:district:zvolen')
        ->and($primary('058 06'))->toBe('sk:district:poprad')
        ->and($primary('917 01'))->toBe('sk:district:trnava');

    // Bratislava 2nd-digit rule pins (orsr.sk verified street rows).
    expect($primary('851 01'))->toBe('sk:district:bratislava-v')
        ->and($primary('841 04'))->toBe('sk:district:bratislava-iv')
        ->and($primary('811 01'))->toBe('sk:district:bratislava-i')
        ->and($primary('821 01'))->toBe('sk:district:bratislava-ii')
        ->and($primary('831 01'))->toBe('sk:district:bratislava-iii');

    // Rajec office codes resolve to Žilina (zero GeoNames rows; WP Rajec).
    expect($primary('015 14'))->toBe('sk:district:zilina')
        ->and($primary('015 41'))->toBe('sk:district:zilina');

    // B11 moves: KI office codes at district level.
    expect($primary('075 12'))->toBe('sk:district:trebisov')
        ->and($primary('077 01'))->toBe('sk:district:trebisov')
        ->and($primary('052 06'))->toBe('sk:district:spisska-nova-ves')
        ->and($primary('071 03'))->toBe('sk:district:michalovce')
        ->and($primary('048 03'))->toBe('sk:district:roznava')
        ->and($primary('045 23'))->toBe('sk:district:kosice-okolie')
        ->and($primary('044 54'))->toBe('sk:district:kosice-ii');

    // Košice-city office codes stay at region level (no digit rule).
    expect($primary('040 02'))->toBe('sk:region:kosice')
        ->and($postcodes->where('isPrimary', true)->where('areaSourceId', 'sk:region:kosice'))->toHaveCount(215);

    // Multi-link secondaries (GeoNames row-majority primaries).
    $secondary = static fn (string $code): array => $byCode->get($code)->where('isPrimary', false)->pluck('areaSourceId')->all();

    expect($primary('040 01'))->toBe('sk:district:kosice-i')
        ->and($secondary('040 01'))->toBe(['sk:district:kosice-iv'])
        ->and($primary('985 42'))->toBe('sk:district:lucenec')
        ->and($secondary('985 42'))->toBe(['sk:district:poltar', 'sk:district:rimavska-sobota'])
        ->and($primary('027 32'))->toBe('sk:district:liptovsky-mikulas')
        ->and($secondary('027 32'))->toBe(['sk:district:tvrdosin']);

    // Per-region primary counts.
    $areas = app(SlovakiaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $regionOf = fn (string $sid): string => str_contains($sid, ':region:')
        ? $sid
        : (string) $areas->get($sid)->parentSourceId;
    $rc = $postcodes->where('isPrimary', true)->countBy(fn ($row): string => $regionOf((string) $row->areaSourceId));

    expect($rc->sortKeys()->all())->toBe([
        'sk:region:banska-bystrica' => 349,
        'sk:region:bratislava' => 977,
        'sk:region:kosice' => 509,
        'sk:region:nitra' => 378,
        'sk:region:presov' => 354,
        'sk:region:trencin' => 242,
        'sk:region:trnava' => 300,
        'sk:region:zilina' => 371,
    ]);

    // Per-district primaries: largest blocks, moved districts, singletons.
    $mc = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($mc)->toHaveCount(80)
        ->and($mc->get('sk:district:bratislava-i'))->toBe(459)
        ->and($mc->get('sk:district:bratislava-ii'))->toBe(144)
        ->and($mc->get('sk:district:zilina'))->toBe(156)
        ->and($mc->get('sk:district:trebisov'))->toBe(116)
        ->and($mc->get('sk:district:michalovce'))->toBe(38)
        ->and($mc->get('sk:district:spisska-nova-ves'))->toBe(31)
        ->and($mc->get('sk:district:roznava'))->toBe(30)
        ->and($mc->get('sk:district:kosice-okolie'))->toBe(39)
        ->and($mc->get('sk:district:kosice-ii'))->toBe(6)
        ->and($mc->get('sk:district:kosice-i'))->toBe(2)
        ->and($mc->get('sk:district:kosice-iv'))->toBe(1)
        ->and($mc->get('sk:region:kosice'))->toBe(215);
});
