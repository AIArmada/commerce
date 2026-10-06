<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 8 districts plus Ascension and Tristan da Cunha', function (): void {
    $areas = app(SaintHelenaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(10)
        ->and($areas->get('sh:island:ascension')->code)->toBe('AC')
        ->and($areas->get('sh:island:tristan-da-cunha')->code)->toBe('TA')
        ->and($areas->get('sh:district:jamestown')->type)->toBe('district');
});

it('bundles the 3 Saint Helena territory postcodes with Jamestown primary', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SH', $dir . '/saint-helena-postal-codes.csv', $dir . '/saint-helena-postal-code-areas.csv', 'aiarmada.addressing.saint_helena');

    $postcodes = $source->postalCodes()->collect();

    // UK postcode areas list: STHL 1ZZ + ASCN 1ZZ + TDCU 1ZZ.
    // STHL shared by all 8 districts, Jamestown (GPO) primary.
    expect($postcodes)->toHaveCount(10);

    $primaries = $postcodes->where('isPrimary', true)->keyBy->code;

    expect($primaries)->toHaveCount(3)
        ->and((string) $primaries->get('STHL 1ZZ')->areaSourceId)->toBe('sh:district:jamestown')
        ->and((string) $primaries->get('ASCN 1ZZ')->areaSourceId)->toBe('sh:island:ascension')
        ->and((string) $primaries->get('TDCU 1ZZ')->areaSourceId)->toBe('sh:island:tristan-da-cunha');
});
