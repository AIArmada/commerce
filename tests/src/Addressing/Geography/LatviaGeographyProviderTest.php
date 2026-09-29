<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;

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
