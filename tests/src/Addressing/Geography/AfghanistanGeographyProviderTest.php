<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;

it('spells the province Uruzgan per the list page (ISO AF-URU)', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('af:province:uruzgan')->name)->toBe('Uruzgan')
        ->and($areas->get('af:province:uruzgan')->code)->toBe('URU')
        ->and($areas->has('af:province:urozgan'))->toBeFalse();
});

it('ships 401 COD-AB districts under provinces with parent links', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'af:province:badakhshan'))->toHaveCount(28)
        ->and($l2->where('parentSourceId', 'af:province:kabul'))->toHaveCount(15)
        ->and($byId->get('af:district:kabul')->code)->toBe('AF0101')
        ->and($byId->get('af:district:ghazni:jaghatu')->parentSourceId)->toBe('af:province:ghazni');
});
