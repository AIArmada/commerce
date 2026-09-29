<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;

it('exposes the corrected West Azerbaijan province slug and name', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ir:province:west-azerbaijan')->name)->toBe('West Azerbaijan')
        ->and($areas->get('ir:province:west-azerbaijan')->code)->toBe('04')
        ->and($areas->get('ir:province:west-azerbaijan')->type)->toBe('province')
        ->and($areas->has('ir:province:west-azarbaijan'))->toBeFalse();
});

it('ships 429 May-2019 counties under provinces with parent links', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(429)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ir:province:tehran'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'ir:province:qom'))->toHaveCount(1)
        ->and($byId->get('ir:county:abadan')->parentSourceId)->toBe('ir:province:khuzestan');
});
