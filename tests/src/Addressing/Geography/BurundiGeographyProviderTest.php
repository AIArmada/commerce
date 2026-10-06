<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;

it('pins the verified Burundi tree of 5 provinces and 42 communes', function (): void {
    $areas = app(BurundiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // 2025 structure per Loi Organique 1/05 (enacted law Article 5
    // OCR + citypopulation 42/42; ISO still lists the 18 former
    // provinces). No postcode system (UPU bdi profile + WP List
    // "no codes" + UPU type table + no GeoNames BI.zip), so no
    // postal files ship.
    expect($areas->where('level', 1))->toHaveCount(5)
        ->and($areas->where('type', 'commune'))->toHaveCount(42)
        ->and($byId->get('bi:province:buhumuza')->code)->toBe('01')
        ->and($byId->get('bi:province:gitega')->code)->toBe('05')
        ->and($areas->where('parentSourceId', 'bi:province:buhumuza'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'bi:province:bujumbura'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'bi:province:burunga'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'bi:province:butanyerera'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bi:province:gitega'))->toHaveCount(9);
});
