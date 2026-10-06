<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;

it('spells the comarcas Ngäbe-Buglé and Guna Yala', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->name)->toBe('Ngäbe-Buglé Comarca')
        ->and($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->code)->toBe('NB')
        ->and($areas->get('pa:indigenous_region:guna-yala')->name)->toBe('Guna Yala')
        ->and($areas->get('pa:indigenous_region:guna-yala')->code)->toBe('KY')
        ->and($areas->has('pa:indigenous_region:ngobe-bugle-comarca'))->toBeFalse()
        ->and($areas->has('pa:indigenous_region:guna'))->toBeFalse();
});

it('ships 10 provinces plus 4 comarcas with 81 districts', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(14)
        ->and($l2)->toHaveCount(81)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('pa:province:panama-oeste')->code)->toBe('10')
        ->and($byId->get('pa:indigenous_region:naso-tjer-di')->name)->toBe('Naso Tjër Di')
        ->and($byId->get('pa:indigenous_region:naso-tjer-di')->code)->toBe('NT');
});

it('pins the verified PA tree of 4/14/6/6/3/2/7/7/9/6/5/12 districts', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('parentSourceId', 'pa:province:bocas-del-toro'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'pa:province:chiriqui-province'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'pa:province:cocle'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'pa:province:colon'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'pa:province:darien'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'pa:indigenous_region:embera-wounaan-comarca'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'pa:province:herrera'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'pa:province:los-santos'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'pa:indigenous_region:ngabe-bugle-comarca'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'pa:province:panama'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'pa:province:panama-oeste'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'pa:province:veraguas'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'pa:indigenous_region:guna-yala'))->toHaveCount(0)
        ->and($areas->where('parentSourceId', 'pa:indigenous_region:naso-tjer-di'))->toHaveCount(0);
});
