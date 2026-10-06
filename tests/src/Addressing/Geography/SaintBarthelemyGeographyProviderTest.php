<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saint-Barthélemy single collectivity and code 97133', function (): void {
    $areas = app(SaintBarthelemyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:BL defines no codes (no subdivisions).
    expect($areas)->toHaveCount(1)
        ->and($byId->get('bl:overseas_collectivity:saint-barthelemy')->name)->toBe('Saint-Barthélemy');

    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BL', $dir . '/saint-barthelemy-postal-codes.csv', $dir . '/saint-barthelemy-postal-code-areas.csv', 'aiarmada.addressing.saint_barthelemy');

    $postcodes = $source->postalCodes()->collect();

    // UPU blmEn: single code 97133 left of the locality.
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('97133')
        ->and((string) $postcodes->first()->areaSourceId)->toBe('bl:overseas_collectivity:saint-barthelemy')
        ->and($postcodes->first()->isPrimary)->toBeTrue();
});
