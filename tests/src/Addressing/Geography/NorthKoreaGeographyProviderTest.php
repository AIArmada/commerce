<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaGeographyProvider;

it('types Nampo and Kaesong as special cities', function (): void {
    $areas = app(NorthKoreaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('kp:special_city:nampo')->name)->toBe('Nampo')
        ->and($areas->get('kp:special_city:nampo')->code)->toBe('14')
        ->and($areas->get('kp:special_city:kaesong')->code)->toBe('15')
        ->and($areas->has('kp:metropolitan_city:nampho'))->toBeFalse()
        ->and($areas->has('kp:metropolitan_city:kaesong'))->toBeFalse();
});

it('ships 13 first-level units with ISO 3166-2:KP codes', function (): void {
    $areas = app(NorthKoreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(13)
        ->and($byId->get('kp:capital_city:pyongyang')->code)->toBe('01')
        ->and($byId->get('kp:province:ryanggang')->code)->toBe('10')
        ->and($byId->get('kp:special_city:rason')->code)->toBe('13')
        ->and($byId->get('kp:special_city:nampo')->code)->toBe('14')
        ->and($byId->get('kp:special_city:kaesong')->code)->toBe('15')
        // COD-AB covers 11 of 13: Kaesong and Rason ship childless.
        ->and($areas->where('level', 2)->where('parentSourceId', 'kp:special_city:kaesong'))->toHaveCount(0)
        ->and($areas->where('level', 2)->where('parentSourceId', 'kp:special_city:rason'))->toHaveCount(0);
});

it('ships 179 COD-AB districts under provinces with parent links', function (): void {
    $areas = app(NorthKoreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(179)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'kp:province:north-pyongan'))->toHaveCount(25)
        ->and($l2->where('parentSourceId', 'kp:special_city:nampo'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'kp:capital_city:pyongyang'))->toHaveCount(3)
        ->and($byId->get('kp:district:nampo-city')->code)->toBe('KP1102')
        ->and($byId->get('kp:district:south-pyongan:unsan')->parentSourceId)->toBe('kp:province:south-pyongan');
});
