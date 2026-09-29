<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Fiji\FijiGeographyProvider;

it('ships 14 provinces under divisions with parent links', function (): void {
    $areas = app(FijiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(14)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fj:province:ba')->name)->toBe('Ba')
        ->and($byId->get('fj:province:cakaudrove')->parentSourceId)->toBe('fj:division:northern');
});
