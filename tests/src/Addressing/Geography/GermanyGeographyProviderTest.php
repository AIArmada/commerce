<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;

it('ships 294 rural and 107 urban districts under states with parent links', function (): void {
    $areas = app(GermanyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'rural_district'))->toHaveCount(294)
        ->and($areas->where('type', 'urban_district'))->toHaveCount(107)
        ->and($byId->get('de:district:berlin')->type)->toBe('urban_district')
        ->and($byId->get('de:district:munich')->type)->toBe('rural_district')
        ->and($byId->get('de:district:bayern:munich')->type)->toBe('urban_district')
        ->and($byId->get('de:district:aachen')->type)->toBe('rural_district');
});
