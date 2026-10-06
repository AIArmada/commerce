<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;

it('ships 10 provinces with ISO codes and 116 districts', function (): void {
    $areas = app(ZambiaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(10)
        ->and($areas->where('level', 2))->toHaveCount(116);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('zm:province:western')->code)->toBe('01')
        ->and($byId->get('zm:province:central')->code)->toBe('02')
        ->and($byId->get('zm:province:eastern')->code)->toBe('03')
        ->and($byId->get('zm:province:luapula')->code)->toBe('04')
        ->and($byId->get('zm:province:northern')->code)->toBe('05')
        ->and($byId->get('zm:province:northwestern')->code)->toBe('06')
        ->and($byId->get('zm:province:southern')->code)->toBe('07')
        ->and($byId->get('zm:province:copperbelt')->code)->toBe('08')
        ->and($byId->get('zm:province:lusaka')->code)->toBe('09')
        ->and($byId->get('zm:province:muchinga')->code)->toBe('10');
});

it('names Mansa bare and pins per-province district counts', function (): void {
    $areas = app(ZambiaGeographyProvider::class)->addressAreaSource()->areas();
    $byId = $areas->keyBy('sourceId');

    // B13: 'Mansa District, Zambia' page-title scrape fixed (WP + Statoids).
    expect($byId->has('zm:district:mansa-district-zambia'))->toBeFalse()
        ->and($byId->get('zm:district:mansa')->name)->toBe('Mansa')
        ->and($byId->get('zm:district:mansa')->parentSourceId)->toBe('zm:province:luapula');

    // April-2018 116-district set.
    expect($areas->where('parentSourceId', 'zm:province:western'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'zm:province:eastern'))->toHaveCount(15)
        ->and($areas->where('parentSourceId', 'zm:province:southern'))->toHaveCount(15)
        ->and($areas->where('parentSourceId', 'zm:province:luapula'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'zm:province:northern'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'zm:province:central'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'zm:province:northwestern'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'zm:province:copperbelt'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'zm:province:muchinga'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'zm:province:lusaka'))->toHaveCount(6);
});
