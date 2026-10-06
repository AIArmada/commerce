<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;

it('ships the verified pre-July-2026 tree with Gunwi under Daegu and Michuhol', function (): void {
    $areas = app(SouthKoreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(245)
        ->and($areas->where('level', '1'))->toHaveCount(17)
        ->and($byId->get('kr:county:gunwi')->parentSourceId)->toBe('kr:metropolitan_city:daegu')
        ->and($byId->has('kr:district:michuhol'))->toBeTrue();
});

it('holds the 2026-07-01 reorgs out of the tree pending the structural decision', function (): void {
    $areas = app(SouthKoreaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Old entities still present (held state is explicit, not silent).
    foreach (['kr:metropolitan_city:gwangju', 'kr:province:south-jeolla', 'kr:district:incheon:jung', 'kr:district:incheon:dong', 'kr:district:incheon:seo'] as $slug) {
        expect($areas->has($slug))->toBeTrue();
    }

    // New entities absent until the merger/Incheon reorg is applied as a whole.
    foreach (['kr:special_metropolitan_city:jeonnam-gwangju', 'kr:district:incheon:jemulpo', 'kr:district:incheon:yeongjong', 'kr:district:incheon:geomdan', 'kr:district:incheon:seohae'] as $slug) {
        expect($areas->has($slug))->toBeFalse();
    }
});
