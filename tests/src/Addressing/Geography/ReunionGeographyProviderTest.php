<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Reunion tree of 4 districts and 24 communes', function (): void {
    $areas = app(ReunionGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(4)
        ->and($areas->where('type', 'commune'))->toHaveCount(24);

    // INSEE commune codes + WP arrondissement parents, exact.
    expect($byId->get('re:commune:saint-denis')->code)->toBe('97411')
        ->and($byId->get('re:commune:saint-pierre')->code)->toBe('97416')
        ->and($byId->get('re:commune:saint-paul')->code)->toBe('97415')
        ->and($byId->get('re:commune:cilaos')->parentSourceId)->toBe('re:district:saint-pierre')
        ->and($byId->get('re:commune:salazie')->parentSourceId)->toBe('re:district:saint-benoit')
        ->and($byId->get('re:commune:sainte-marie')->parentSourceId)->toBe('re:district:saint-denis')
        ->and($byId->get('re:commune:le-port')->parentSourceId)->toBe('re:district:saint-paul');
});

it('bundles the 37 Reunion postcodes with the 97490 fix and no 97428', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('RE', $dir . '/reunion-postal-codes.csv', $dir . '/reunion-postal-code-areas.csv', 'aiarmada.addressing.reunion');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(37)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // B7 swap: 97428 phantom out (absent from Hexasmal, GeoNames
    // RE.txt, and BAN), 97490 Sainte-Clotilde in (all three carry
    // it for Saint-Denis). Hexasmal mapping, exact.
    expect($byCode->has('97428'))->toBeFalse()
        ->and((string) $byCode->get('97490')->areaSourceId)->toBe('re:commune:saint-denis')
        ->and((string) $byCode->get('97400')->areaSourceId)->toBe('re:commune:saint-denis')
        ->and((string) $byCode->get('97417')->areaSourceId)->toBe('re:commune:saint-denis')
        ->and((string) $byCode->get('97411')->areaSourceId)->toBe('re:commune:saint-paul')
        ->and((string) $byCode->get('97460')->areaSourceId)->toBe('re:commune:saint-paul')
        ->and((string) $byCode->get('97432')->areaSourceId)->toBe('re:commune:saint-pierre')
        ->and((string) $byCode->get('97450')->areaSourceId)->toBe('re:commune:saint-louis')
        ->and((string) $byCode->get('97470')->areaSourceId)->toBe('re:commune:saint-benoit');
});
