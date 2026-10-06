<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saint-Pierre and Miquelon single collectivity and code 97500', function (): void {
    $areas = app(SaintPierreAndMiquelonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:PM defines no codes.
    expect($areas)->toHaveCount(1)
        ->and($byId->get('pm:overseas_collectivity:saint-pierre-and-miquelon')->name)->toBe('Saint-Pierre and Miquelon');

    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PM', $dir . '/saint-pierre-and-miquelon-postal-codes.csv', $dir . '/saint-pierre-and-miquelon-postal-code-areas.csv', 'aiarmada.addressing.saint_pierre_and_miquelon');

    $postcodes = $source->postalCodes()->collect();

    // UPU spmEn: single code 97500 shared by both communes (the
    // worked example is a Miquelon address).
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('97500')
        ->and((string) $postcodes->first()->areaSourceId)->toBe('pm:overseas_collectivity:saint-pierre-and-miquelon')
        ->and($postcodes->first()->isPrimary)->toBeTrue();
});
