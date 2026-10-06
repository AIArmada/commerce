<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Czechia tree of 14 regions and 76 districts', function (): void {
    $areas = app(CzechRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(90)
        ->and($areas->where('type', 'region'))->toHaveCount(13)
        ->and($areas->where('type', 'capital_city'))->toHaveCount(1)
        ->and($areas->where('type', 'district'))->toHaveCount(76)
        ->and($areas->where('level', 1))->toHaveCount(14)
        ->and($areas->where('level', 2))->toHaveCount(76);

    // ISO 3166-2:CZ region codes, exact set (Prague is the capital city).
    expect($areas->where('level', 1)->pluck('code')->sort()->values()->all())->toBe(
        ['10', '20', '31', '32', '41', '42', '51', '52', '53', '63', '64', '71', '72', '80']
    );

    // Prague is L1-only: current ISO 3166-2:CZ defines no Prague district.
    expect($byId->get('cz:capital_city:praha-hlavni-mesto')->code)->toBe('10')
        ->and($byId->get('cz:capital_city:praha-hlavni-mesto')->name)->toBe('Praha, Hlavní město')
        ->and($areas->where('parentSourceId', 'cz:capital_city:praha-hlavni-mesto'))->toHaveCount(0);

    // District LAU1 codes incl. the 20A/20B/20C split-district letters.
    expect($areas->where('level', 2)->pluck('code')->sort()->values()->all())->toBe([
        '201', '202', '203', '204', '205', '206', '207', '208', '209', '20A', '20B', '20C',
        '311', '312', '313', '314', '315', '316', '317', '321', '322', '323', '324', '325',
        '326', '327', '411', '412', '413', '421', '422', '423', '424', '425', '426', '427',
        '511', '512', '513', '514', '521', '522', '523', '524', '525', '531', '532', '533',
        '534', '631', '632', '633', '634', '635', '641', '642', '643', '644', '645', '646',
        '647', '711', '712', '713', '714', '715', '721', '722', '723', '724', '801', '802',
        '803', '804', '805', '806',
    ]);

    // Per-region district membership: 7/7/3/5/5/4/6/5/4/7/12/7/4.
    expect($areas->where('level', 2)->countBy('parentSourceId')->sortKeys()->all())->toBe([
        'cz:region:jihocesky-kraj' => 7,
        'cz:region:jihomoravsky-kraj' => 7,
        'cz:region:karlovarsky-kraj' => 3,
        'cz:region:kraj-vysocina' => 5,
        'cz:region:kralovehradecky-kraj' => 5,
        'cz:region:liberecky-kraj' => 4,
        'cz:region:moravskoslezsky-kraj' => 6,
        'cz:region:olomoucky-kraj' => 5,
        'cz:region:pardubicky-kraj' => 4,
        'cz:region:plzensky-kraj' => 7,
        'cz:region:stredocesky-kraj' => 12,
        'cz:region:ustecky-kraj' => 7,
        'cz:region:zlinsky-kraj' => 4,
    ]);

    // ISO name spots (accents and split-district codes).
    expect($byId->get('cz:district:brno-mesto')->name)->toBe('Brno-město')
        ->and($byId->get('cz:district:praha-zapad')->code)->toBe('20A')
        ->and($byId->get('cz:district:pribram')->code)->toBe('20B')
        ->and($byId->get('cz:district:rakovnik')->code)->toBe('20C')
        ->and($byId->get('cz:region:kraj-vysocina')->name)->toBe('Kraj Vysočina');
});

it('bundles the 2694 Czech postcodes as 2738 district links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CZ', $dir . '/czech-republic-postal-codes.csv', $dir . '/czech-republic-postal-code-areas.csv', 'aiarmada.addressing.czech_republic');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2738)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2694)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(44)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2694)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{3} \d{2}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->min())->toBe('100 00')
        ->and($postcodes->pluck('code')->max())->toBe('798 62');

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;
    $secondary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId;

    // Every code carries exactly one primary; exactly 44 are dual-linked.
    expect($byCode->every(static fn ($rows): bool => $rows->where('isPrimary', true)->count() === 1))->toBeTrue()
        ->and($byCode->filter(static fn ($rows): bool => $rows->count() > 1))->toHaveCount(44);

    // Prague block: 58 codes 100 00-199 00, all single-linked capital_city.
    $prague = $postcodes->filter(static fn ($row): bool => str_starts_with((string) $row->code, '1'));
    expect($prague)->toHaveCount(58)
        ->and($prague->every(static fn ($row): bool => (string) $row->areaSourceId === 'cz:capital_city:praha-hlavni-mesto'))->toBeTrue()
        ->and($prague->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    // GeoNames row-majority ties, OSM-boundary confirmed.
    expect($primary('507 91'))->toBe('cz:district:jicin') // 4:4, Stará Paka office
        ->and($secondary('507 91'))->toBe('cz:district:semily')
        ->and($primary('544 43'))->toBe('cz:district:trutnov') // 1:1, Kuks office
        ->and($secondary('544 43'))->toBe('cz:district:nachod')
        ->and($primary('569 94'))->toBe('cz:district:svitavy') // 1:1, Telecí
        ->and($secondary('569 94'))->toBe('cz:district:zdar-nad-sazavou');

    // B11 fill: 15 RÚIAN-corroborated secondaries (primaries unchanged).
    expect($secondary('273 51'))->toBe('cz:district:praha-zapad') // Červený Újezd
        ->and($secondary('289 14'))->toBe('cz:district:kolin') // Poříčany
        ->and($secondary('294 13'))->toBe('cz:district:liberec') // Chlístov
        ->and($secondary('321 00'))->toBe('cz:district:plzen-jih') // Šlovice
        ->and($secondary('334 52'))->toBe('cz:district:domazlice') // Háje
        ->and($secondary('357 35'))->toBe('cz:district:karlovy-vary') // Mírová
        ->and($secondary('364 64'))->toBe('cz:district:sokolov') // Nová Ves
        ->and($secondary('380 01'))->toBe('cz:district:trebic') // Radkovice u Budče
        ->and($secondary('385 01'))->toBe('cz:district:klatovy') // Horská Kvilda
        ->and($secondary('507 13'))->toBe('cz:district:semily') // Bradlecká Lhota
        ->and($secondary('517 61'))->toBe('cz:district:usti-nad-orlici') // Záhory
        ->and($secondary('539 44'))->toBe('cz:district:svitavy') // Příluka
        ->and($secondary('563 01'))->toBe('cz:district:svitavy') // Koruna
        ->and($secondary('675 26'))->toBe('cz:district:jihlava') // Jindřichovice
        ->and($secondary('783 42'))->toBe('cz:district:prostejov'); // Slatinky

    // Held single: 384 01 Prachatice-only (Chlístovice row is a GeoNames
    // coordinate-duplicate misfile), plus the three unresolved holds.
    expect($byCode->get('384 01')->count())->toBe(1)
        ->and($primary('384 01'))->toBe('cz:district:prachatice')
        ->and($byCode->get('285 09')->count())->toBe(1)
        ->and($primary('285 09'))->toBe('cz:district:kutna-hora')
        ->and($byCode->get('331 62')->count())->toBe(1)
        ->and($primary('331 62'))->toBe('cz:district:plzen-sever')
        ->and($byCode->get('353 01')->count())->toBe(2)
        ->and($primary('353 01'))->toBe('cz:district:cheb')
        ->and($secondary('353 01'))->toBe('cz:district:karlovy-vary');

    // Per-region primary counts (Středočeský kraj holds the Prague ring).
    $areas = app(CzechRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $regionOf = fn (string $sid): string => $areas->get($sid)->level === 1 ? $sid : (string) $areas->get($sid)->parentSourceId;
    $rc = $postcodes->where('isPrimary', true)->countBy(fn ($row): string => $regionOf((string) $row->areaSourceId));

    expect($rc->sortKeys()->all())->toBe([
        'cz:capital_city:praha-hlavni-mesto' => 58,
        'cz:region:jihocesky-kraj' => 241,
        'cz:region:jihomoravsky-kraj' => 289,
        'cz:region:karlovarsky-kraj' => 48,
        'cz:region:kraj-vysocina' => 213,
        'cz:region:kralovehradecky-kraj' => 234,
        'cz:region:liberecky-kraj' => 130,
        'cz:region:moravskoslezsky-kraj' => 217,
        'cz:region:olomoucky-kraj' => 174,
        'cz:region:pardubicky-kraj' => 191,
        'cz:region:plzensky-kraj' => 136,
        'cz:region:stredocesky-kraj' => 411,
        'cz:region:ustecky-kraj' => 218,
        'cz:region:zlinsky-kraj' => 134,
    ]);

    // Per-district primaries: largest, thin rural blocks, singletons.
    $dc = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($dc)->toHaveCount(77)
        ->and($dc->get('cz:district:trutnov'))->toBe(60)
        ->and($dc->get('cz:district:opava'))->toBe(58)
        ->and($dc->get('cz:district:usti-nad-orlici'))->toBe(54)
        ->and($dc->get('cz:district:cheb'))->toBe(9)
        ->and($dc->get('cz:district:tachov'))->toBe(9)
        ->and($dc->get('cz:district:klatovy'))->toBe(10)
        ->and($dc->get('cz:district:plzen-mesto'))->toBe(12)
        ->and($dc->get('cz:district:sokolov'))->toBe(13);
});
