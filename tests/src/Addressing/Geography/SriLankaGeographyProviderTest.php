<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;

it('ships 25 districts under provinces with parent links', function (): void {
    $areas = app(SriLankaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(25)
        ->and($byId->get('lk:district:ampara')->parentSourceId)->toBe('lk:province:eastern');
});
