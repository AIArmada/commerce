<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;

it('exposes the corrected Jonglei state slug and name', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ss:state:jonglei')->name)->toBe('Jonglei')
        ->and($areas->get('ss:state:jonglei')->code)->toBe('JG')
        ->and($areas->get('ss:state:jonglei')->type)->toBe('state')
        ->and($areas->has('ss:state:jonglei-state'))->toBeFalse();
});

it('ships 10 states with 84 counties after the B12 cleanup', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(10)
        ->and($l2)->toHaveCount(84)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->has('ss:county:districts-of-sudan'))->toBeFalse()
        ->and($byId->has('ss:county:states-of-south-sudan'))->toBeFalse()
        ->and($byId->has('ss:county:lopa'))->toBeFalse()
        ->and($byId->has('ss:county:adior'))->toBeFalse();
});

it('pins the verified SS tree of 6/8/16/8/5/9/13/6/3/10 counties', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('parentSourceId', 'ss:state:central-equatoria'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ss:state:eastern-equatoria'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ss:state:jonglei'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'ss:state:lakes'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ss:state:northern-bahr-el-ghazal'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ss:state:unity'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ss:state:upper-nile'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'ss:state:warrap'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ss:state:western-bahr-el-ghazal'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'ss:state:western-equatoria'))->toHaveCount(10);
});

it('spells Verteth and Pariang per the B12 oracles', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ss:county:verteth')->name)->toBe('Verteth')
        ->and($areas->get('ss:county:verteth')->parentSourceId)->toBe('ss:state:jonglei')
        ->and($areas->has('ss:county:vertet-county'))->toBeFalse()
        ->and($areas->get('ss:county:pariang')->name)->toBe('Pariang')
        ->and($areas->get('ss:county:pariang')->parentSourceId)->toBe('ss:state:unity')
        ->and($areas->has('ss:county:panrieng'))->toBeFalse()
        ->and($areas->get('ss:county:makal')->name)->toBe('Makal')
        ->and($areas->get('ss:county:akoka')->name)->toBe('Akoka');
});
