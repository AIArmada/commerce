<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;

it('ships 80 MIMU districts under regions with parent links', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(80)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'mm:state:shan'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'mm:region:bago'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'mm:region:sagaing'))->toHaveCount(12)
        ->and($byId->get('mm:district:kengtung')->code)->toBe('MMR016001')
        ->and($byId->get('mm:district:oke-ta-ra')->parentSourceId)->toBe('mm:union_territory:naypyidaw');
});
