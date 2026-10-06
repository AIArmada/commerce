<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsGeographyProvider;

it('pins the verified US Minor Outlying Islands tree of 9 islands', function (): void {
    $areas = app(USMinorOutlyingIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'island'))->toHaveCount(9)
        ->and($areas->where('level', 1))->toHaveCount(9);

    // ISO 3166-2:UM codes (FIPS-derived), exact set. No UM
    // domestic postcode system (UPU: islands follow the US
    // system; no permanent population).
    expect($byId->get('um:island:baker-island')->code)->toBe('81')
        ->and($byId->get('um:island:howland-island')->code)->toBe('84')
        ->and($byId->get('um:island:jarvis-island')->code)->toBe('86')
        ->and($byId->get('um:island:johnston-atoll')->code)->toBe('67')
        ->and($byId->get('um:island:kingman-reef')->code)->toBe('89')
        ->and($byId->get('um:island:midway-islands')->code)->toBe('71')
        ->and($byId->get('um:island:navassa-island')->code)->toBe('76')
        ->and($byId->get('um:island:palmyra-atoll')->code)->toBe('95')
        ->and($byId->get('um:island:wake-island')->code)->toBe('79');
});
