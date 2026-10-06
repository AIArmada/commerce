<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 22 L1 areas with 368 townships under L1 parents', function (): void {
    $areas = app(TaiwanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(390)
        ->and($l1)->toHaveCount(22)
        ->and($l2)->toHaveCount(368)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('tw:city:chiayi')->code)->toBe('CYI')
        ->and($byId->get('tw:county:chiayi-county')->code)->toBe('CYQ')
        ->and($byId->get('tw:city:hsinchu')->code)->toBe('HSZ')
        ->and($byId->get('tw:county:hsinchu-county')->code)->toBe('HSQ')
        ->and($byId->get('tw:special_municipality:taipei')->code)->toBe('TPE');
});

it('pins the B19 Chiayi/Hsinchu county reparenting', function (): void {
    $areas = app(TaiwanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2->where('parentSourceId', 'tw:city:chiayi'))->toHaveCount(2)
        ->and($l2->where('parentSourceId', 'tw:county:chiayi-county'))->toHaveCount(18)
        ->and($l2->where('parentSourceId', 'tw:city:hsinchu'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'tw:county:hsinchu-county'))->toHaveCount(13);

    expect($byId->get('tw:county_administered_city:zhubei-city')->parentSourceId)->toBe('tw:county:hsinchu-county')
        ->and($byId->get('tw:county_administered_city:taibao-city')->parentSourceId)->toBe('tw:county:chiayi-county');

    // MOI 5-digit prefix invariant per parent (cross-scheme ISO-prefix check is void for TW).
    $prefixes = fn (string $parent) => $l2->where('parentSourceId', $parent)->map(fn ($a) => mb_substr($a->code, 0, 5))->unique()->values()->all();

    expect($prefixes('tw:county:chiayi-county'))->toBe(['10010'])
        ->and($prefixes('tw:county:hsinchu-county'))->toBe(['10004'])
        ->and($prefixes('tw:city:chiayi'))->toBe(['10020'])
        ->and($prefixes('tw:city:hsinchu'))->toBe(['10018']);
});

it('pins 365 codes with the 300/600 shared-code primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TW', $dir . '/taiwan-postal-codes.csv', $dir . '/taiwan-postal-code-areas.csv', 'aiarmada.addressing.taiwan');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(365)
        ->and($postcodes)->toHaveCount(368);

    expect($byCode->get('300')->where('isPrimary', true)->first()->areaSourceId)->toBe('tw:district:hsinchu:north-district')
        ->and($byCode->get('600')->where('isPrimary', true)->first()->areaSourceId)->toBe('tw:district:chiayi:east-district')
        ->and($byCode->get('302')->first()->areaSourceId)->toBe('tw:county_administered_city:zhubei-city');
});
