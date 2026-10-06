<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Mozambique tree of 10 provinces, Maputo City and 136 districts', function (): void {
    $areas = app(MozambiqueGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'province'))->toHaveCount(10)
        ->and($areas->where('type', 'city'))->toHaveCount(1)
        ->and($areas->where('type', 'district'))->toHaveCount(136)
        ->and($areas->where('level', 1))->toHaveCount(11)
        ->and($areas->where('level', 2))->toHaveCount(136);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:MZ codes (Zambezia ships ASCII).
    expect($byId->get('mz:province:niassa')->code)->toBe('A')
        ->and($byId->get('mz:province:manica')->code)->toBe('B')
        ->and($byId->get('mz:province:gaza')->code)->toBe('G')
        ->and($byId->get('mz:province:inhambane')->code)->toBe('I')
        ->and($byId->get('mz:province:maputo-province')->code)->toBe('L')
        ->and($byId->get('mz:city:maputo-city')->code)->toBe('MPM')
        ->and($byId->get('mz:province:nampula')->code)->toBe('N')
        ->and($byId->get('mz:province:cabo-delgado')->code)->toBe('P')
        ->and($byId->get('mz:province:zambezia')->code)->toBe('Q')
        ->and($byId->get('mz:province:sofala')->code)->toBe('S')
        ->and($byId->get('mz:province:tete')->code)->toBe('T');

    // Maputo City's seven municipal districts (Maxixe stays city-only).
    expect($byId->get('mz:district:kampfumo')->parentSourceId)->toBe('mz:city:maputo-city')
        ->and($areas->where('parentSourceId', 'mz:city:maputo-city'))->toHaveCount(7)
        ->and($areas->where('sourceId', 'mz:district:maxixe'))->toHaveCount(0);

    // Per-province district counts.
    $counts = $areas->where('level', 2)->countBy('parentSourceId');

    expect($counts->get('mz:province:nampula'))->toBe(18)
        ->and($counts->get('mz:province:cabo-delgado'))->toBe(16)
        ->and($counts->get('mz:province:zambezia'))->toBe(16)
        ->and($counts->get('mz:province:niassa'))->toBe(15)
        ->and($counts->get('mz:province:tete'))->toBe(13)
        ->and($counts->get('mz:province:inhambane'))->toBe(12)
        ->and($counts->get('mz:province:sofala'))->toBe(12)
        ->and($counts->get('mz:province:gaza'))->toBe(11)
        ->and($counts->get('mz:province:manica'))->toBe(9)
        ->and($counts->get('mz:province:maputo-province'))->toBe(7)
        ->and($counts->get('mz:city:maputo-city'))->toBe(7);
});

it('bundles the 145 district postcodes with 4 shared-code multis', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MZ', $dir . '/mozambique-postal-codes.csv', $dir . '/mozambique-postal-code-areas.csv', 'aiarmada.addressing.mozambique');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(151)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(145);

    $byCode = $postcodes->groupBy(static fn ($row): string => (string) $row->code);
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // UPU mozEn anchors: 1100 Maputo, 3314 Mecula.
    expect($primary('1100'))->toBe('mz:district:kampfumo')
        ->and($primary('3314'))->toBe('mz:district:mecula');

    // M5 fill anchors (Mapanet cell + OSM containment).
    expect($primary('1304'))->toBe('mz:district:vilanculos')
        ->and($primary('1310'))->toBe('mz:district:panda')
        ->and($primary('1312'))->toBe('mz:district:zavala')
        ->and($primary('2106'))->toBe('mz:district:nhamatanda')
        ->and($primary('2112'))->toBe('mz:district:muanza')
        ->and($primary('2114'))->toBe('mz:district:marromeu')
        ->and($primary('2309'))->toBe('mz:district:tsangano')
        ->and($primary('2312'))->toBe('mz:district:zumbo')
        ->and($primary('2401'))->toBe('mz:district:nicoadala')
        ->and($primary('2402'))->toBe('mz:district:namacurra')
        ->and($primary('2405'))->toBe('mz:district:pebane')
        ->and($primary('2409'))->toBe('mz:district:morrumbala')
        ->and($primary('2413'))->toBe('mz:district:mopeia')
        ->and($primary('2415'))->toBe('mz:district:namarroi')
        ->and($primary('3104'))->toBe('mz:district:mossuril')
        ->and($primary('3107'))->toBe('mz:district:muecate')
        ->and($primary('3110'))->toBe('mz:district:murrupula')
        ->and($primary('3113'))->toBe('mz:district:nacala-a-velha')
        ->and($primary('3115'))->toBe('mz:district:erati')
        ->and($primary('3119'))->toBe('mz:district:ribaue')
        ->and($primary('3202'))->toBe('mz:district:metuge')
        ->and($primary('3205'))->toBe('mz:district:metuge')
        ->and($primary('3209'))->toBe('mz:district:namuno')
        ->and($primary('3213'))->toBe('mz:district:quissanga')
        ->and($primary('3215'))->toBe('mz:district:muidumbe')
        ->and($primary('3218'))->toBe('mz:district:nangade')
        ->and($primary('3219'))->toBe('mz:district:palma')
        ->and($primary('3302'))->toBe('mz:district:sanga')
        ->and($primary('3307'))->toBe('mz:district:metarica')
        ->and($primary('3308'))->toBe('mz:district:n-gauma')
        ->and($primary('3310'))->toBe('mz:district:nipepe')
        ->and($primary('3311'))->toBe('mz:district:muembe');

    // The four multis: 3206 + 3211 triples, 3301 + 2307 duals.
    $legs = static fn (string $code): array => $byCode->get($code)
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();

    expect($legs('3206'))->toBe(['mz:district:ancuabe' => true, 'mz:district:chiure' => false, 'mz:district:mecufi' => false])
        ->and($legs('3211'))->toBe(['mz:district:macomia' => true, 'mz:district:ibo' => false, 'mz:district:quissanga' => false])
        ->and($legs('3301'))->toBe(['mz:district:mecanhelas' => true, 'mz:district:mandimba' => false])
        ->and($legs('2307'))->toBe(['mz:district:mutarara' => true, 'mz:district:doa' => false]);

    // Four districts stay codeless in every source.
    expect($postcodes->where('areaSourceId', 'mz:district:xai-xai'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mz:district:mogincual'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mz:district:nampula'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mz:district:kamaxaquene'))->toHaveCount(0);
});
