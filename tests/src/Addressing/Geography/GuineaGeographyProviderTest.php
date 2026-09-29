<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;

it('ships 33 prefectures under regions with parent links', function (): void {
    $areas = app(GuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'prefecture');

    expect($l2)->toHaveCount(33)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gn:prefecture:boke')->name)->toBe('Boké')
        ->and($byId->get('gn:prefecture:beyla')->parentSourceId)->toBe('gn:administrative_region:nzerekore');
});
