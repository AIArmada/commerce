<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;

it('pins the verified Rwanda tree of 5 provinces and 30 districts', function (): void {
    $areas = app(RwandaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:RW current 01-05 (B-M retired prefectures);
    // districts exact vs WP + citypopulation. No postcode system
    // (WP List "no codes" + UPU type table + no GeoNames RW.zip),
    // so no postal files ship.
    expect($areas->where('level', 1))->toHaveCount(5)
        ->and($areas->where('type', 'district'))->toHaveCount(30)
        ->and($byId->get('rw:city:kigali')->code)->toBe('01')
        ->and($byId->get('rw:province:eastern')->code)->toBe('02')
        ->and($areas->where('parentSourceId', 'rw:province:eastern'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'rw:city:kigali'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'rw:province:northern'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'rw:province:southern'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'rw:province:western'))->toHaveCount(7);
});
