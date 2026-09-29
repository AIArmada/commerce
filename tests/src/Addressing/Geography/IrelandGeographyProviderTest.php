<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;

it('ships 26 counties under provinces with parent links', function (): void {
    $areas = app(IrelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'county');

    expect($l2)->toHaveCount(26)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ie:county:carlow')->name)->toBe('Carlow')
        ->and($byId->get('ie:county:cavan')->parentSourceId)->toBe('ie:province:ulster');
});
