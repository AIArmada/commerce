<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;

it('ships 10 ISO-coded provinces with 64 districts', function (): void {
    $areas = app(ZimbabweGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($areas->where('type', 'province'))->toHaveCount(10)
        ->and($l2)->toHaveCount(64)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('zw:province:bulawayo')->code)->toBe('BU')
        ->and($byId->get('zw:province:harare')->code)->toBe('HA')
        ->and($byId->get('zw:province:mashonaland-east')->code)->toBe('ME')
        ->and($byId->get('zw:province:matabeleland-north')->code)->toBe('MN')
        ->and($byId->get('zw:province:midlands')->code)->toBe('MI');
});

it('pins the verified ZW tree of 1/3/7/8/9/7/7/7/7/8 districts', function (): void {
    $areas = app(ZimbabweGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'zw:province:bulawayo'))->toHaveCount(1)
        ->and($areas->where('parentSourceId', 'zw:province:harare'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'zw:province:manicaland'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'zw:province:mashonaland-central'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'zw:province:mashonaland-east'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'zw:province:mashonaland-west'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'zw:province:masvingo'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'zw:province:matabeleland-north'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'zw:province:matabeleland-south'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'zw:province:midlands'))->toHaveCount(8);

    // B10 holds: the 4 post-Statoids splits stay (census/RDC-backed),
    // and Harare keeps 3 — the WP section's 15 suburb entries are
    // pollution contradicted by the article's own 64 lede.
    expect($byId->has('zw:district:mbire'))->toBeTrue()
        ->and($byId->has('zw:district:mhondoro-ngezi'))->toBeTrue()
        ->and($byId->has('zw:district:sanyati'))->toBeTrue()
        ->and($byId->has('zw:district:vungu'))->toBeTrue()
        ->and($areas->where('name', 'Glen View'))->toHaveCount(0)
        ->and($areas->where('name', 'Budiriro'))->toHaveCount(0);
});
