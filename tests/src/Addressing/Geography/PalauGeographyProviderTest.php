<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Palau\PalauGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Palau tree of 16 states', function (): void {
    $areas = app(PalauGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'state'))->toHaveCount(16)
        ->and($areas->where('level', 1))->toHaveCount(16);

    // ISO 3166-2:PW codes, exact set.
    expect($byId->get('pw:state:aimeliik')->code)->toBe('002')
        ->and($byId->get('pw:state:airai')->code)->toBe('004')
        ->and($byId->get('pw:state:angaur')->code)->toBe('010')
        ->and($byId->get('pw:state:hatohobei')->code)->toBe('050')
        ->and($byId->get('pw:state:kayangel')->code)->toBe('100')
        ->and($byId->get('pw:state:koror')->code)->toBe('150')
        ->and($byId->get('pw:state:melekeok')->code)->toBe('212')
        ->and($byId->get('pw:state:ngaraard')->code)->toBe('214')
        ->and($byId->get('pw:state:ngarchelong')->code)->toBe('218')
        ->and($byId->get('pw:state:ngardmau')->code)->toBe('222')
        ->and($byId->get('pw:state:ngatpang')->code)->toBe('224')
        ->and($byId->get('pw:state:ngchesar')->code)->toBe('226')
        ->and($byId->get('pw:state:ngeremlengui')->code)->toBe('227')
        ->and($byId->get('pw:state:ngiwal')->code)->toBe('228')
        ->and($byId->get('pw:state:peleliu')->code)->toBe('350')
        ->and($byId->get('pw:state:sonsorol')->code)->toBe('370');
});

it('bundles the 2 Palau ZIPs with Melekeok-only 96939', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PW', $dir . '/palau-postal-codes.csv', $dir . '/palau-postal-code-areas.csv', 'aiarmada.addressing.palau');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(16);

    $byCode = $postcodes->groupBy->code;

    // UPU plw (US system, two codes): 96939 is Ngerulmud-only
    // (capital settlement in Melekeok), 96940 the rest with Koror
    // primary (USPS bulletin build source).
    expect($byCode->get('96939'))->toHaveCount(1)
        ->and((string) $byCode->get('96939')->first()->areaSourceId)->toBe('pw:state:melekeok')
        ->and($byCode->get('96940'))->toHaveCount(15)
        ->and((string) $byCode->get('96940')->firstWhere('isPrimary', true)->areaSourceId)->toBe('pw:state:koror');
});
