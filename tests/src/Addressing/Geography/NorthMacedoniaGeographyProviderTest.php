<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified North Macedonia tree of 80 municipalities', function (): void {
    $areas = app(NorthMacedoniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Single level: all 80 are municipalities (opstini), no parents.
    // 10 of them constitute the City of Skopje (Greater Skopje).
    expect($areas)->toHaveCount(80)
        ->and($areas->where('type', 'municipality'))->toHaveCount(80)
        ->and($areas->where('level', 1))->toHaveCount(80)
        ->and($areas->whereNotNull('parentSourceId'))->toHaveCount(0);

    // ISO 3166-2:MK oracle: codes are the current MK-101..MK-817 series.
    expect($byId->get('mk:municipality:veles')->code)->toBe('101')
        ->and($byId->get('mk:municipality:skopje'))->toBeNull()
        ->and($byId->get('mk:municipality:cucer-sandevo')->code)->toBe('816')
        ->and($byId->get('mk:municipality:kicevo')->code)->toBe('307')
        ->and($byId->get('mk:municipality:tetovo')->code)->toBe('609')
        ->and($byId->get('mk:municipality:centar')->code)->toBe('814')
        ->and($byId->get('mk:municipality:suto-orizari')->code)->toBe('817')
        ->and($byId->get('mk:municipality:stip')->code)->toBe('211');

    // 2013-merge oracle (WP Municipalities of North Macedonia): Kicevo
    // absorbed Drugovo, Zajas, Oslomej and Vranestica; the four former
    // municipalities are absent from the post-2013 roster of 80.
    $slugs = $areas->map(static fn ($a): string => (string) $a->sourceId)->all();
    expect($slugs)->not->toContain(
        'mk:municipality:drugovo',
        'mk:municipality:zajas',
        'mk:municipality:oslomej',
        'mk:municipality:vranestica',
    );

    // WP-spelling keeps where ISO romanizes differently: Debarca (ISO
    // Debrca) and Mavrovo and Rostusa (ISO Mavrovo i Rostuse).
    expect($byId->get('mk:municipality:debarca')->code)->toBe('304')
        ->and($byId->get('mk:municipality:mavrovo-and-rostusa')->code)->toBe('607');
});

it('bundles the verified 326-code overlay with the 1128 Ilinden fix', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MK', $dir . '/north-macedonia-postal-codes.csv', $dir . '/north-macedonia-postal-code-areas.csv', 'aiarmada.addressing.north_macedonia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(326)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(326)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(326)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // The fix: 1128 is the airport post office; the terminal sits in
    // Mralino/Ilinden (OSM boundaries + history.mk 1996-reddefinition
    // note + terminal Mralino/Ilinden address). "Petrovec" is the
    // airport's conventional name (nearest village), not its commune.
    expect($primary('1128'))->toBe('mk:municipality:ilinden');

    // UPU MKD profile (07/2019) example anchors.
    expect($primary('1020'))->toBe('mk:municipality:karpos')
        ->and($primary('1310'))->toBe('mk:municipality:kumanovo')
        ->and($primary('2314'))->toBe('mk:municipality:vinica');

    // Cross-block keeps: the office village, not the block capital.
    expect($primary('2333'))->toBe('mk:municipality:cesinovo-oblesevo')
        ->and($primary('2315'))->toBe('mk:municipality:berovo')
        ->and($primary('2301'))->toBe('mk:municipality:cesinovo-oblesevo')
        ->and($primary('7313'))->toBe('mk:municipality:bitola')
        ->and($primary('2411'))->toBe('mk:municipality:vasilevo')
        ->and($primary('2432'))->toBe('mk:municipality:strumica')
        ->and($primary('1316'))->toBe('mk:municipality:rankovce')
        ->and($primary('1332'))->toBe('mk:municipality:rankovce');

    // GeoNames-homonym keeps: the official unit name + village
    // municipality win over stale/wrong-twin GN coords.
    expect($primary('1054'))->toBe('mk:municipality:sopiste')
        ->and($primary('1235'))->toBe('mk:municipality:vrapciste')
        ->and($primary('2434'))->toBe('mk:municipality:novo-selo')
        ->and($primary('2436'))->toBe('mk:municipality:novo-selo')
        ->and($primary('6260'))->toBe('mk:municipality:kicevo')
        ->and($primary('6306'))->toBe('mk:municipality:ohrid');

    // Border-crossing offices pin to the crossing site municipality.
    expect($primary('1061'))->toBe('mk:municipality:cucer-sandevo')
        ->and($primary('1259'))->toBe('mk:municipality:debar')
        ->and($primary('1318'))->toBe('mk:municipality:staro-nagoricane')
        ->and($primary('1319'))->toBe('mk:municipality:kumanovo')
        ->and($primary('1331'))->toBe('mk:municipality:kriva-palanka')
        ->and($primary('1482'))->toBe('mk:municipality:gevgelija')
        ->and($primary('1492'))->toBe('mk:municipality:dojran')
        ->and($primary('2321'))->toBe('mk:municipality:delcevo')
        ->and($primary('2437'))->toBe('mk:municipality:novo-selo')
        ->and($primary('6324'))->toBe('mk:municipality:ohrid')
        ->and($primary('6340'))->toBe('mk:municipality:struga')
        ->and($primary('7226'))->toBe('mk:municipality:bitola')
        ->and($primary('7321'))->toBe('mk:municipality:resen');

    // Skopje branch keeps: naselba/street -> city municipality.
    expect($primary('1103'))->toBe('mk:municipality:centar')
        ->and($primary('1113'))->toBe('mk:municipality:gazi-baba')
        ->and($primary('1117'))->toBe('mk:municipality:cair')
        ->and($primary('1132'))->toBe('mk:municipality:cair')
        ->and($primary('1138'))->toBe('mk:municipality:suto-orizari')
        ->and($primary('1140'))->toBe('mk:municipality:kisela-voda')
        ->and($primary('1142'))->toBe('mk:municipality:butel')
        ->and($primary('1133'))->toBe('mk:municipality:gjorce-petrov')
        ->and($primary('1126'))->toBe('mk:municipality:aerodrom');

    // Drops stay dropped: 1137 (2016-listed, commune uncertain -
    // Krste Misirkov bb straddles Cair/Centar), 7515 Novo Lagovo
    // (mk-wiki only, no official listing), the 11 GeoNames-stale
    // pre-2016 retirements, and the post-2016 (2018-list) openings.
    expect($byCode->has('1137'))->toBeFalse()
        ->and($byCode->has('7515'))->toBeFalse()
        ->and($byCode->has('1434'))->toBeFalse()
        ->and($byCode->has('6256'))->toBeFalse()
        ->and($byCode->has('7316'))->toBeFalse()
        ->and($byCode->has('7508'))->toBeFalse()
        ->and($byCode->has('1012'))->toBeFalse()
        ->and($byCode->has('1127'))->toBeFalse()
        ->and($byCode->has('1245'))->toBeFalse()
        ->and($byCode->has('2405'))->toBeFalse();
});
