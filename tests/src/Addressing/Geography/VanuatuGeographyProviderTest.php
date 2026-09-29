<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Vanuatu\VanuatuGeographyProvider;

it('ships 60 area councils and 3 municipalities with parent links', function (): void {
    $areas = app(VanuatuGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'municipality'))->toHaveCount(3)
        ->and($byId->get('vu:municipality:port-vila')->code)->toBe('VU.SE.PV')
        ->and($byId->get('vu:municipality:lenakel')->name)->toBe('Lenakel')
        ->and($byId->get('vu:area_council:central-pentecost-1')->name)->toBe('Central Pentecost 1');
});
