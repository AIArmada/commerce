<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;

it('pins the verified Syria tree of 14 provinces and 66 districts', function (): void {
    $areas = app(SyriaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(14)
        ->and($areas->where('type', 'district'))->toHaveCount(66)
        ->and($areas->where('parentSourceId', 'sy:province:aleppo'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'sy:province:rif-dimashq'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'sy:province:homs'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sy:province:hama'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'sy:province:latakia'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'sy:province:daraa'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'sy:province:quneitra'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sy:province:damascus'))->toHaveCount(1);

    // ISO 3166-2:SY codes; Districts-of-Syria oracle: Taldou (2010
    // split) under Homs, Atarib under Aleppo, Damascus city as its
    // own district.
    expect($byId->get('sy:province:damascus')->code)->toBe('DI')
        ->and($byId->get('sy:province:aleppo')->code)->toBe('HL')
        ->and($byId->get('sy:province:rif-dimashq')->code)->toBe('RD')
        ->and($byId->get('sy:district:taldou')->parentSourceId)->toBe('sy:province:homs')
        ->and($byId->get('sy:district:atarib')->parentSourceId)->toBe('sy:province:aleppo')
        ->and($byId->get('sy:district:damascus')->parentSourceId)->toBe('sy:province:damascus');
});
