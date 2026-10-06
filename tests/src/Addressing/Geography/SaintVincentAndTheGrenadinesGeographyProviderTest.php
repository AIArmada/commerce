<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saint Vincent tree of 6 parishes', function (): void {
    $areas = app(SaintVincentAndTheGrenadinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(6)
        ->and($areas->where('level', 1))->toHaveCount(6);

    // ISO 3166-2:VC codes, exact set.
    expect($byId->get('vc:parish:charlotte')->code)->toBe('01')
        ->and($byId->get('vc:parish:saint-andrew')->code)->toBe('02')
        ->and($byId->get('vc:parish:saint-david')->code)->toBe('03')
        ->and($byId->get('vc:parish:saint-george')->code)->toBe('04')
        ->and($byId->get('vc:parish:saint-patrick')->code)->toBe('05')
        ->and($byId->get('vc:parish:grenadines')->code)->toBe('06');
});

it('bundles the 56 Vincentian postcodes as single parish primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('VC', $dir . '/saint-vincent-and-the-grenadines-postal-codes.csv', $dir . '/saint-vincent-and-the-grenadines-postal-code-areas.csv', 'aiarmada.addressing.saint_vincent_and_the_grenadines');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(56)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // B2 adjudications: Layou VC0360 moved to Saint Andrew (parish
    // seat evidence); Ottley Hall VC0170 kept in Saint Andrew (the
    // delivery point is the leeward Edinboro/Ottley Hall area).
    expect((string) $byCode->get('VC0360')->areaSourceId)->toBe('vc:parish:saint-andrew')
        ->and((string) $byCode->get('VC0170')->areaSourceId)->toBe('vc:parish:saint-andrew')
        ->and((string) $byCode->get('VC0350')->areaSourceId)->toBe('vc:parish:saint-patrick')
        ->and((string) $byCode->get('VC0318')->areaSourceId)->toBe('vc:parish:saint-patrick');

    // Boundary-zone keeps: Calder/Stubbs/Enhams/Calliaqua/Evesham
    // Saint George, Richland Park Charlotte, Spring Village Saint
    // Patrick, Buccament/Vermont/Questelles/Campden Park Saint Andrew.
    expect((string) $byCode->get('VC0264')->areaSourceId)->toBe('vc:parish:saint-george')
        ->and((string) $byCode->get('VC0274')->areaSourceId)->toBe('vc:parish:saint-george')
        ->and((string) $byCode->get('VC0290')->areaSourceId)->toBe('vc:parish:saint-george')
        ->and((string) $byCode->get('VC0294')->areaSourceId)->toBe('vc:parish:charlotte')
        ->and((string) $byCode->get('VC0370')->areaSourceId)->toBe('vc:parish:saint-andrew')
        ->and((string) $byCode->get('VC0390')->areaSourceId)->toBe('vc:parish:saint-andrew')
        ->and((string) $byCode->get('VC0400')->areaSourceId)->toBe('vc:parish:grenadines')
        ->and((string) $byCode->get('VC0120')->areaSourceId)->toBe('vc:parish:saint-george');

    // Held out: VC0100 Kingstown boxes (box-only exclusion) and
    // VC0292 Mesopotamia (Photon Saint George vs GeoNames Charlotte).
    expect($byCode->has('VC0100'))->toBeFalse()
        ->and($byCode->has('VC0292'))->toBeFalse();

    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(6)
        ->and($counts->get('vc:parish:charlotte'))->toBe(18)
        ->and($counts->get('vc:parish:saint-george'))->toBe(14)
        ->and($counts->get('vc:parish:saint-andrew'))->toBe(9)
        ->and($counts->get('vc:parish:grenadines'))->toBe(7)
        ->and($counts->get('vc:parish:saint-david'))->toBe(6)
        ->and($counts->get('vc:parish:saint-patrick'))->toBe(2);
});
