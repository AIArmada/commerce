<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;

it('pins the verified Somalia tree of 18 regions and 89 districts', function (): void {
    $areas = app(SomaliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(18)
        ->and($areas->where('type', 'district'))->toHaveCount(89)
        ->and($areas->where('parentSourceId', 'so:region:banaadir'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'so:region:lower-shebelle'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'so:region:bari'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'so:region:gedo'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'so:region:bakool'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'so:region:galguduud'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'so:region:mudug'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'so:region:sool'))->toHaveCount(4);

    // ISO 3166-2:SO codes; Regions-and-districts oracle: Kismayo
    // under Lower Juba, Hargeisa/Berbera/Gabiley under Woqooyi
    // Galbeed (oracle table prints the Somaliland rename Maroodi
    // Jeex; CSV follows ISO). CORRECTED names (fail pre-fix by
    // design): Lower/Middle Shabelle.
    expect($byId->get('so:region:woqooyi-galbeed')->code)->toBe('WO')
        ->and($byId->get('so:region:banaadir')->code)->toBe('BN')
        ->and($byId->get('so:region:lower-juba')->code)->toBe('JH')
        ->and($byId->get('so:region:lower-shebelle')->name)->toBe('Lower Shabelle')
        ->and($byId->get('so:region:middle-shebelle')->name)->toBe('Middle Shabelle')
        ->and($byId->get('so:district:kismayo')->parentSourceId)->toBe('so:region:lower-juba')
        ->and($byId->get('so:district:hargeisa')->parentSourceId)->toBe('so:region:woqooyi-galbeed')
        ->and($byId->get('so:district:garowe')->parentSourceId)->toBe('so:region:nugal');
});
