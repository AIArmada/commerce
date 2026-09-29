<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kiribati\KiribatiGeographyProvider;

it('ships 24 councils under island groups with parent links', function (): void {
    $areas = app(KiribatiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(24)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ki:island:gilbert'))->toHaveCount(20)
        ->and($l2->where('parentSourceId', 'ki:island:line'))->toHaveCount(3)
        ->and($byId->get('ki:council:canton')->parentSourceId)->toBe('ki:island:phoenix')
        ->and($byId->get('ki:council:south-tarawa')->parentSourceId)->toBe('ki:island:gilbert');
});
