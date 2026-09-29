<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;

it('ships 368 townships, cities and districts under divisions with parent links', function (): void {
    $areas = app(TaiwanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(368)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'district'))->toHaveCount(164)
        ->and($l2->where('type', 'rural_township'))->toHaveCount(122)
        ->and($byId->get('tw:district:banqiao-district')->code)->toBe('65000010')
        ->and($byId->get('tw:district:banqiao-district')->parentSourceId)->toBe('tw:special_municipality:new-taipei');
});
