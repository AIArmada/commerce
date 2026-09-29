<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;

it('ships 9 parishes plus the two municipalities under their parishes', function (): void {
    $areas = app(BermudaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(9)
        ->and($areas->where('type', 'municipality'))->toHaveCount(2)
        ->and($byId->get('bm:municipality:hamilton')->parentSourceId)->toBe('bm:parish:pembroke')
        ->and($byId->get('bm:municipality:saint-georges')->parentSourceId)->toBe('bm:parish:saint-georges');
});
