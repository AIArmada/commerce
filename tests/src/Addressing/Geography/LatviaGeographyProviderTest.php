<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 585 parishes, towns and cities under municipalities with parent links', function (): void {
    $areas = app(LatviaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(585)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('level', 1))->toHaveCount(42)
        ->and($byId->has('lv:municipality:varaklani'))->toBeFalse()
        ->and($l2->where('parentSourceId', 'lv:municipality:madona'))->toHaveCount(25)
        ->and($byId->get('lv:parish:marupe:sala-parish')->parentSourceId)->toBe('lv:municipality:marupe');
});

it('pins the B20 leg-collapse fixes: rural moves, city duals, Varaklani to Madona', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LV', $dir . '/latvia-postal-codes.csv', $dir . '/latvia-postal-code-areas.csv', 'aiarmada.addressing.latvia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(697)
        ->and($postcodes)->toHaveCount(731);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Rural moves off collapsed city legs.
    expect($primary('LV-3011'))->toBe('lv:municipality:jelgava')
        ->and($primary('LV-4206'))->toBe('lv:municipality:valmiera')
        ->and($primary('LV-5011'))->toBe('lv:municipality:ogre')
        ->and($primary('LV-5204'))->toBe('lv:municipality:jekabpils')
        // City singles + new duals (municipality primary).
        ->and($primary('LV-3602'))->toBe('lv:state_city:ventspils')
        ->and($primary('LV-3001'))->toBe('lv:municipality:jelgava')
        ->and($byCode->get('LV-3001')->pluck('areaSourceId')->contains('lv:state_city:jelgava'))->toBeTrue()
        ->and($primary('LV-3601'))->toBe('lv:municipality:ventspils')
        ->and($primary('LV-5001'))->toBe('lv:municipality:ogre')
        ->and($primary('LV-5201'))->toBe('lv:municipality:jekabpils')
        ->and($primary('LV-5015'))->toBe('lv:municipality:ogre')
        // Varaklani codes follow the 2025 merger into Madona.
        ->and($primary('LV-4838'))->toBe('lv:municipality:madona')
        // Verified keep: town single.
        ->and($primary('LV-5003'))->toBe('lv:city:ogre');
});
