<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Liechtenstein tree of 11 communes', function (): void {
    $areas = app(LiechtensteinGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'commune'))->toHaveCount(11)
        ->and($areas->where('level', 1))->toHaveCount(11);

    // ISO 3166-2:LI codes, exact set.
    expect($byId->get('li:commune:balzers')->code)->toBe('01')
        ->and($byId->get('li:commune:eschen')->code)->toBe('02')
        ->and($byId->get('li:commune:gamprin')->code)->toBe('03')
        ->and($byId->get('li:commune:mauren')->code)->toBe('04')
        ->and($byId->get('li:commune:planken')->code)->toBe('05')
        ->and($byId->get('li:commune:ruggell')->code)->toBe('06')
        ->and($byId->get('li:commune:schaan')->code)->toBe('07')
        ->and($byId->get('li:commune:schellenberg')->code)->toBe('08')
        ->and($byId->get('li:commune:triesen')->code)->toBe('09')
        ->and($byId->get('li:commune:triesenberg')->code)->toBe('10')
        ->and($byId->get('li:commune:vaduz')->code)->toBe('11');
});

it('bundles the 13 Liechtenstein postcodes as single commune primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LI', $dir . '/liechtenstein-postal-codes.csv', $dir . '/liechtenstein-postal-code-areas.csv', 'aiarmada.addressing.liechtenstein');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(13)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // GeoNames LI dump, exact: Nendeln 9485 -> Eschen, Schaanwald
    // 9486 -> Mauren; 9489 unassigned (no GN row, no swisstopo hit).
    expect((string) $byCode->get('9485')->areaSourceId)->toBe('li:commune:eschen')
        ->and((string) $byCode->get('9492')->areaSourceId)->toBe('li:commune:eschen')
        ->and((string) $byCode->get('9486')->areaSourceId)->toBe('li:commune:mauren')
        ->and((string) $byCode->get('9493')->areaSourceId)->toBe('li:commune:mauren')
        ->and((string) $byCode->get('9490')->areaSourceId)->toBe('li:commune:vaduz')
        ->and((string) $byCode->get('9498')->areaSourceId)->toBe('li:commune:planken')
        ->and($byCode->has('9489'))->toBeFalse();
});
