<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;

it('ships 50 provinces under communities plus Ceuta and Melilla with parent links', function (): void {
    $areas = app(SpainGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1->where('type', 'autonomous_community'))->toHaveCount(17)
        ->and($l1->where('type', 'autonomous_city'))->toHaveCount(2)
        ->and($areas->where('type', 'province'))->toHaveCount(50)
        ->and($byId->get('es:province:almeria')->parentSourceId)->toBe('es:autonomous_community:andalusia');
});
