<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsGeographyProvider;

it('ships 7 districts under islands with parent links', function (): void {
    $areas = app(CaymanIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(7)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ky:island:grand-cayman'))->toHaveCount(5)
        ->and($byId->get('ky:district:george-town')->parentSourceId)->toBe('ky:island:grand-cayman')
        ->and($byId->get('ky:district:cayman-brac')->parentSourceId)->toBe('ky:island:cayman-brac');
});

it('pins the verified Cayman Islands tree of 3 islands', function (): void {
    $areas = app(CaymanIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // No ISO 3166-2:KY codes; 01-03 synthetic. Seven districts
    // per the district table (prose merges the Sister Islands).
    // Box-only postcode system (UPU): nothing imported.
    expect($areas->where('type', 'island'))->toHaveCount(3)
        ->and($byId->get('ky:island:grand-cayman')->code)->toBe('01')
        ->and($byId->get('ky:island:cayman-brac')->code)->toBe('02')
        ->and($byId->get('ky:island:little-cayman')->code)->toBe('03')
        ->and($byId->get('ky:district:little-cayman')->parentSourceId)->toBe('ky:island:little-cayman');
});
