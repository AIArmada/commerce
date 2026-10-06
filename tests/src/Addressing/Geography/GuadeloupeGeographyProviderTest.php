<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Guadeloupe tree of 2 districts and 32 communes', function (): void {
    $areas = app(GuadeloupeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(2)
        ->and($areas->where('type', 'commune'))->toHaveCount(32);

    // INSEE commune codes + WP names, exact. Accented forms match
    // WP titles (Morne-à-l'Eau, Pointe-à-Pitre, Trois-Rivières,
    // La Désirade, Saint-François).
    expect($byId->get('gp:commune:les-abymes')->code)->toBe('97101')
        ->and($byId->get('gp:commune:basse-terre')->code)->toBe('97105')
        ->and($byId->get('gp:commune:pointe-a-pitre')->code)->toBe('97120')
        ->and($byId->get('gp:commune:morne-a-l-eau')->name)->toBe('Morne-à-l\'Eau')
        ->and($byId->get('gp:commune:trois-rivieres')->name)->toBe('Trois-Rivières')
        ->and($byId->get('gp:commune:la-desirade')->name)->toBe('La Désirade')
        ->and($byId->get('gp:commune:saint-francois')->name)->toBe('Saint-François');
});

it('bundles the 33 Guadeloupe postcodes as single commune primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GP', $dir . '/guadeloupe-postal-codes.csv', $dir . '/guadeloupe-postal-code-areas.csv', 'aiarmada.addressing.guadeloupe');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(33)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Hexasmal + GeoNames GP.txt + WP postal column, exact.
    // Les Abymes carries two codes (97139/97142); 97133
    // Saint-Barthélemy + 97150 Saint-Martin are BL/MF rows.
    expect((string) $byCode->get('97100')->areaSourceId)->toBe('gp:commune:basse-terre')
        ->and((string) $byCode->get('97110')->areaSourceId)->toBe('gp:commune:pointe-a-pitre')
        ->and((string) $byCode->get('97134')->areaSourceId)->toBe('gp:commune:saint-louis')
        ->and((string) $byCode->get('97139')->areaSourceId)->toBe('gp:commune:les-abymes')
        ->and((string) $byCode->get('97142')->areaSourceId)->toBe('gp:commune:les-abymes')
        ->and((string) $byCode->get('97140')->areaSourceId)->toBe('gp:commune:capesterre-de-marie-galante')
        ->and($byCode->has('97133'))->toBeFalse()
        ->and($byCode->has('97150'))->toBeFalse();
});
