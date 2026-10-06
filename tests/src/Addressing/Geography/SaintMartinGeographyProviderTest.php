<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintMartin\SaintMartinGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saint-Martin single collectivity and code 97150', function (): void {
    $areas = app(SaintMartinGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:MF defines no codes (no subdivisions).
    expect($areas)->toHaveCount(1)
        ->and($byId->get('mf:overseas_collectivity:saint-martin')->name)->toBe('Saint-Martin');

    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MF', $dir . '/saint-martin-postal-codes.csv', $dir . '/saint-martin-postal-code-areas.csv', 'aiarmada.addressing.saint_martin');

    $postcodes = $source->postalCodes()->collect();

    // UPU mafEn: single code 97150 left of the locality.
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('97150')
        ->and((string) $postcodes->first()->areaSourceId)->toBe('mf:overseas_collectivity:saint-martin')
        ->and($postcodes->first()->isPrimary)->toBeTrue();
});
