<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Austria tree of 9 states, 79 districts and 14 statutory cities', function (): void {
    $areas = app(AustriaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(102)
        ->and($areas->where('type', 'state'))->toHaveCount(9)
        ->and($areas->where('type', 'district'))->toHaveCount(79)
        ->and($areas->where('type', 'statutory_city'))->toHaveCount(14)
        ->and($areas->where('level', 1))->toHaveCount(9)
        ->and($areas->where('level', 2))->toHaveCount(93);

    // ISO 3166-2:AT state codes 1-9.
    expect($areas->where('level', 1)->pluck('code')->sort()->values()->all())->toBe(
        ['1', '2', '3', '4', '5', '6', '7', '8', '9']
    );

    // Vienna is L1-only: its municipal districts are not modelled as areas.
    expect($byId->get('at:state:vienna')->code)->toBe('9')
        ->and($areas->where('parentSourceId', 'at:state:vienna'))->toHaveCount(0);

    // Full L2 Kennziffer roster: no 324 Wien-Umgebung (dissolved 2016),
    // post-2012 Styria mergers 620-623, pre-merger codes gone.
    expect($areas->where('level', 2)->pluck('code')->sort()->values()->all())->toBe([
        '101', '102', '103', '104', '105', '106', '107', '108', '109',
        '201', '202', '203', '204', '205', '206', '207', '208', '209', '210',
        '301', '302', '303', '304', '305', '306', '307', '308', '309', '310',
        '311', '312', '313', '314', '315', '316', '317', '318', '319', '320',
        '321', '322', '323', '325',
        '401', '402', '403', '404', '405', '406', '407', '408', '409', '410',
        '411', '412', '413', '414', '415', '416', '417', '418',
        '501', '502', '503', '504', '505', '506',
        '601', '603', '606', '610', '611', '612', '614', '616', '617',
        '620', '621', '622', '623',
        '701', '702', '703', '704', '705', '706', '707', '708', '709',
        '801', '802', '803', '804',
    ]);

    // Per-state L2 membership: 9/10/24/18/6/13/9/4 (Vienna childless).
    expect($areas->where('level', 2)->countBy('parentSourceId')->sortKeys()->all())->toBe([
        'at:state:burgenland' => 9,
        'at:state:carinthia' => 10,
        'at:state:lower-austria' => 24,
        'at:state:salzburg' => 6,
        'at:state:styria' => 13,
        'at:state:tyrol' => 9,
        'at:state:upper-austria' => 18,
        'at:state:vorarlberg' => 4,
    ]);

    // City/district twins share toponyms but never codes; Leoben is a
    // district, not a statutory city.
    expect($byId->get('at:statutory_city:st-polten')->code)->toBe('302')
        ->and($byId->get('at:statutory_city:wiener-neustadt')->code)->toBe('304')
        ->and($byId->get('at:district:st-polten')->code)->toBe('319')
        ->and($byId->get('at:district:wiener-neustadt')->code)->toBe('323')
        ->and($byId->get('at:district:leoben')->type)->toBe('district')
        ->and($byId->get('at:district:sudoststeiermark')->name)->toBe('Südoststeiermark');
});

it('bundles the 2501 Austrian postcodes as 2623 district links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AT', $dir . '/austria-postal-codes.csv', $dir . '/austria-postal-code-areas.csv', 'aiarmada.addressing.austria');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2623)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2501)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(122)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2501)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->min())->toBe('1000')
        ->and($postcodes->pluck('code')->max())->toBe('9992');

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;
    $secondaries = static fn (string $code): array => $byCode->get($code)->where('isPrimary', false)->pluck('areaSourceId')->all();

    // Every code carries exactly one primary; 117 are multi-linked.
    expect($byCode->every(static fn ($rows): bool => $rows->where('isPrimary', true)->count() === 1))->toBeTrue()
        ->and($byCode->filter(static fn ($rows): bool => $rows->count() > 1))->toHaveCount(117)
        ->and($byCode->filter(static fn ($rows): bool => $rows->count() > 2)->keys()->map(static fn ($key): string => (string) $key)->sort()->values()->all())->toBe(
            ['2852', '8081', '8312', '8504', '9064'] // numeric-string keys cast to int
        );

    // Vienna 1xxx block: 128 codes, all Vienna-primary except the 1300
    // airport code (Fischamend/Schwechat, Bruck an der Leitha).
    $viennaBlock = $postcodes->filter(static fn ($row): bool => str_starts_with((string) $row->code, '1'));
    expect($viennaBlock->pluck('code')->unique())->toHaveCount(128)
        ->and($primary('1300'))->toBe('at:district:bruck-an-der-leitha')
        ->and($viennaBlock->where('areaSourceId', 'at:state:vienna')->count())->toBe(127);

    // B12 St. Pölten batch: WP city infobox + Nominatim + GeoNames city
    // majorities move all seven off the surrounding district.
    expect($primary('3100'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3104'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3105'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3107'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3109'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3140'))->toBe('at:statutory_city:st-polten')
        ->and($primary('3151'))->toBe('at:statutory_city:st-polten')
        ->and($secondaries('3140'))->toBe(['at:district:st-polten']); // Böheimkirchen villages

    // B12 Wiener Neustadt batch: 2700 is the city flagship (Nominatim +
    // de.wp + displaced GN district rows), 2703/2705/2706/2707 are city
    // box codes (de.wp lists 2705; siblings share the GN signature).
    expect($primary('2700'))->toBe('at:statutory_city:wiener-neustadt')
        ->and($primary('2703'))->toBe('at:statutory_city:wiener-neustadt')
        ->and($primary('2705'))->toBe('at:statutory_city:wiener-neustadt')
        ->and($primary('2706'))->toBe('at:statutory_city:wiener-neustadt')
        ->and($primary('2707'))->toBe('at:statutory_city:wiener-neustadt');

    // B12 dupe-majority corrections: 1140 Penzing + 1210 Floridsdorf are
    // Vienna, 2231 is Strasshof (Gänserndorf), 2680 is Semmering (Neunkirchen).
    expect($primary('1140'))->toBe('at:state:vienna')
        ->and($primary('1210'))->toBe('at:state:vienna')
        ->and($primary('2231'))->toBe('at:district:ganserndorf')
        ->and($primary('2680'))->toBe('at:district:neunkirchen');

    // Holds: GN-majority district assignments kept on stability (Nominatim
    // agrees; loose de.wp city lists overruled), plus genuine-tie PLZ-map
    // breaks and the unverified-code exclusions.
    expect($byCode->get('2751')->count())->toBe(1)
        ->and($primary('2751'))->toBe('at:district:wiener-neustadt')
        ->and($byCode->get('2752')->count())->toBe(1)
        ->and($primary('2752'))->toBe('at:district:wiener-neustadt')
        ->and($byCode->get('3385')->count())->toBe(1)
        ->and($primary('3385'))->toBe('at:district:st-polten')
        ->and($primary('2381'))->toBe('at:district:st-polten') // Laab/Wolfsgraben tie
        ->and($primary('7212'))->toBe('at:district:wiener-neustadt') // Forchtenstein/Ofenbach tie
        ->and($primary('5562'))->toBe('at:district:tamsweg') // Obertauern/Tweng tie
        ->and($primary('7033'))->toBe('at:district:mattersburg') // Zillingtal/Pöttsching tie
        ->and($primary('4550'))->toBe('at:district:kirchdorf') // seat-rule triple tie
        ->and($byCode->has('2702'))->toBeFalse() // closed branch
        ->and($byCode->has('2704'))->toBeFalse() // de.wp-only, unverified
        ->and($byCode->has('8471'))->toBeFalse()
        ->and($byCode->has('8565'))->toBeFalse()
        ->and($byCode->has('9104'))->toBeFalse();

    // Per-state primary counts (Vienna holds the 1xxx block less 1300).
    $areas = app(AustriaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $stateOf = fn (string $sid): string => $areas->get($sid)->level === 1 ? $sid : (string) $areas->get($sid)->parentSourceId;
    $sc = $postcodes->where('isPrimary', true)->countBy(fn ($row): string => $stateOf((string) $row->areaSourceId));

    expect($sc->sortKeys()->all())->toBe([
        'at:state:burgenland' => 153,
        'at:state:carinthia' => 215,
        'at:state:lower-austria' => 647,
        'at:state:salzburg' => 143,
        'at:state:styria' => 376,
        'at:state:tyrol' => 290,
        'at:state:upper-austria' => 449,
        'at:state:vienna' => 127,
        'at:state:vorarlberg' => 101,
    ]);

    // Per-area primaries: largest blocks, thin cities, singletons.
    $dc = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($dc)->toHaveCount(94)
        ->and($dc->get('at:district:innsbruck-land'))->toBe(64)
        ->and($dc->get('at:district:st-polten'))->toBe(51)
        ->and($dc->get('at:district:ganserndorf'))->toBe(45)
        ->and($dc->get('at:statutory_city:st-polten'))->toBe(10)
        ->and($dc->get('at:statutory_city:wiener-neustadt'))->toBe(5)
        ->and($dc->get('at:statutory_city:eisenstadt'))->toBe(3)
        ->and($dc->get('at:statutory_city:rust'))->toBe(1)
        ->and($dc->get('at:statutory_city:waidhofen-an-der-ybbs'))->toBe(1)
        ->and($dc->get('at:district:dornbirn'))->toBe(7);
});
