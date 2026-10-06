<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Samoa\SamoaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 11 districts with 342 villages under itumalo parents', function (): void {
    $areas = app(SamoaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(353)
        ->and($l1)->toHaveCount(11)
        ->and($l2)->toHaveCount(342)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('ws:district:atua')->code)->toBe('AT')
        ->and($byId->get('ws:district:vaisigano')->code)->toBe('VS')
        ->and($byId->get('ws:district:tuamasaga')->code)->toBe('TU');
});

it('pins the B18 itumalo re-parents for the 5 exclave villages', function (): void {
    $areas = app(SamoaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('ws:village:satuimalufilufi')->parentSourceId)->toBe('ws:district:aana')
        ->and($byId->get('ws:village:faleapuna')->parentSourceId)->toBe('ws:district:vaa-o-fonoti')
        ->and($byId->get('ws:village:salamumu-tai')->parentSourceId)->toBe('ws:district:gagaemauga')
        ->and($byId->get('ws:village:salamumu-uta')->parentSourceId)->toBe('ws:district:gagaemauga')
        ->and($byId->get('ws:village:leauvaa')->parentSourceId)->toBe('ws:district:gagaemauga');
});

it('pins 223 postcodes with the 2 district-level moves', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('WS', $dir . '/samoa-postal-codes.csv', $dir . '/samoa-postal-code-areas.csv', 'aiarmada.addressing.samoa');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(223)
        ->and($postcodes)->toHaveCount(240);

    expect($byCode->get('WS1434')->first()->areaSourceId)->toBe('ws:district:atua')
        ->and($byCode->get('WS2491')->first()->areaSourceId)->toBe('ws:district:vaisigano')
        ->and($byCode->get('WS1364')->where('isPrimary', true)->first()->areaSourceId)->toBe('ws:village:vaitele-fou');
});
