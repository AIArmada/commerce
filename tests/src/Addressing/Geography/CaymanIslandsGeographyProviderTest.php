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
