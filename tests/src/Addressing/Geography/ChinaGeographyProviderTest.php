<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;

it('ships 333 prefecture-level divisions under provinces with parent links', function (): void {
    $areas = app(ChinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(333)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'prefecture_city'))->toHaveCount(293)
        ->and($areas->where('type', 'autonomous_prefecture'))->toHaveCount(30)
        ->and($areas->where('type', 'prefecture'))->toHaveCount(7)
        ->and($areas->where('type', 'league'))->toHaveCount(3)
        ->and($byId->get('cn:prefecture_city:suzhou-anhui')->name)->toBe('Suzhou, Anhui')
        ->and($byId->get('cn:prefecture_city:suzhou-jiangsu')->parentSourceId)->toBe('cn:province:jiangsu')
        ->and($byId->get('cn:autonomous_prefecture:yanbian')->parentSourceId)->toBe('cn:province:jilin')
        ->and($byId->get('cn:prefecture:ngari')->parentSourceId)->toBe('cn:autonomous_region:tibet')
        ->and($byId->get('cn:league:alxa')->parentSourceId)->toBe('cn:autonomous_region:inner-mongolia');
});

it('exposes the prefecture level in the address hierarchy', function (): void {
    $levels = collect(app(ChinaGeographyProvider::class)->addressHierarchies()[0]->levels)->keyBy->key;

    expect($levels->has('prefecture'))->toBeTrue()
        ->and($levels->get('prefecture')->parentKey)->toBe('province');
});
