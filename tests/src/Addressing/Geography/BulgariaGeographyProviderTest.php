<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 293 areas with the disambiguated homonym municipalities', function (): void {
    $areas = app(BulgariaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(293)
        ->and($areas->get('bg:municipality:malko-tarnovo')->name)->toBe('Malko Tarnovo')
        ->and($areas->get('bg:municipality:malko-tarnovo')->parentSourceId)->toBe('bg:district:burgas')
        ->and($areas->get('bg:municipality:byala')->parentSourceId)->toBe('bg:district:ruse')
        ->and($areas->get('bg:municipality:varna:byala')->parentSourceId)->toBe('bg:district:varna')
        ->and($areas->get('bg:municipality:dobrichka')->parentSourceId)->toBe('bg:district:dobrich')
        ->and($areas->get('bg:municipality:dobrich')->parentSourceId)->toBe('bg:district:dobrich');
});

it('links the B17-verified Malko Tarnovo renumbering and village fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BG', $dir . '/bulgaria-postal-codes.csv', $dir . '/bulgaria-postal-code-areas.csv', 'aiarmada.addressing.bulgaria');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(4363);

    $byCode = $postcodes->groupBy->code;

    // Post-CRC-2016 renumber 835x->816x (operator + Google + OSM + 2017 tender).
    foreach (['8162', '8163', '8165', '8166', '8170'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('bg:municipality:malko-tarnovo');
    }
    foreach (['8350', '8357', '8359', '8370', '8360'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }

    // Village fills across 14 municipalities.
    expect($byCode->get('2655')->first()->areaSourceId)->toBe('bg:municipality:nevestino')
        ->and($byCode->get('9024')->first()->areaSourceId)->toBe('bg:municipality:varna')
        ->and($byCode->get('9495')->first()->areaSourceId)->toBe('bg:municipality:dobrichka')
        ->and($byCode->get('8289')->first()->areaSourceId)->toBe('bg:municipality:primorsko')
        ->and($byCode->get('4888')->first()->areaSourceId)->toBe('bg:municipality:laki');

    // Dead blocks + orphan singles + branch-quarter codes removed.
    foreach (['5721', '5749', '5735', '5768', '9633', '3036', '9434', '6609', '7101'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }

    // Dual-carrier fix + single-carrier correction.
    expect($byCode->get('2789')->map->areaSourceId->sort()->values()->all())
        ->toBe(['bg:municipality:belitsa', 'bg:municipality:yakoruda'])
        ->and($byCode->get('2791')->map->areaSourceId->all())->toBe(['bg:municipality:belitsa']);

    // Office-live codes kept over stale wiki/GN counterparts.
    expect($byCode->get('2076')->first()->areaSourceId)->toBe('bg:municipality:mirkovo')
        ->and($byCode->get('3056')->first()->areaSourceId)->toBe('bg:municipality:vratsa')
        ->and($byCode->get('9433')->first()->areaSourceId)->toBe('bg:municipality:dobrichka')
        ->and($byCode->has('2096'))->toBeFalse()
        ->and($byCode->has('3264'))->toBeFalse()
        ->and($byCode->has('9494'))->toBeFalse();
});
