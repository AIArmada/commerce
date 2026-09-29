<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;

it('ships 1656 municipalities and cities under provinces plus NCR', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(1656)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(149)
        ->and($l2->where('type', 'municipality'))->toHaveCount(1493)
        ->and($l2->where('type', 'sub_municipality'))->toHaveCount(14)
        ->and($byId->get('ph:sub_municipality:tondo-i-ii')->parentSourceId)->toBe('ph:region:national-capital-region');
});

it('ships 42011 barangays under their municipalities', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('level', 3);

    expect($l3)->toHaveCount(42011)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p) && $byId->get($p)->level === 2))->toBeTrue()
        ->and($byId->get('ph:barangay:50th-district')->parentSourceId)->toBe('ph:city:city-of-ozamiz');
});
