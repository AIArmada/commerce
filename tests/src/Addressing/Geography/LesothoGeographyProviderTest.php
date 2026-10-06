<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;

it('ships 10 ISO-coded districts with 80 constituencies', function (): void {
    $areas = app(LesothoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'constituency');

    expect($areas->where('level', 1))->toHaveCount(10)
        ->and($l2)->toHaveCount(80)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ls:district:maseru')->code)->toBe('A')
        ->and($byId->get('ls:district:berea')->code)->toBe('D')
        ->and($byId->get('ls:district:quthing')->code)->toBe('G')
        ->and($byId->get('ls:district:thaba-tseka')->code)->toBe('K');
});

it('pins the verified 2022-delimitation LS tree of 11/5/13/22/7/6/4/3/4/5', function (): void {
    $areas = app(LesothoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'ls:district:berea'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'ls:district:butha-buthe'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ls:district:leribe'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'ls:district:maseru'))->toHaveCount(22)
        ->and($areas->where('parentSourceId', 'ls:district:mafeteng'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'ls:district:mohales-hoek'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ls:district:mokhotlong'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ls:district:qachas-nek'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'ls:district:quthing'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ls:district:thaba-tseka'))->toHaveCount(5);

    // Mokhotlong #78 is Senqu per IEC (WP duplicates Malingoaneng in error).
    expect($byId->get('ls:constituency:senqu')->parentSourceId)->toBe('ls:district:mokhotlong')
        ->and($areas->where('name', 'Malingoaneng'))->toHaveCount(1)
        ->and($byId->has('ls:constituency:mokhotlong:malingoaneng'))->toBeFalse();
});
