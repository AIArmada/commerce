<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified American Samoa tree of districts, atolls, and counties', function (): void {
    $areas = app(AmericanSamoaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(20)
        ->and($areas->where('type', 'district'))->toHaveCount(3)
        ->and($areas->where('type', 'atoll'))->toHaveCount(2)
        ->and($areas->where('type', 'county'))->toHaveCount(15);

    // No ISO 3166-2:AS codes. Rose + Swains ship as atolls
    // (Statoids rolls the pair into one "Unorganized" row); Fofo
    // county corroborated by its WP article (Statoids predates it).
    expect($byId->get('as:county:fofo')->parentSourceId)->toBe('as:district:western')
        ->and($byId->get('as:county:maoputasi')->parentSourceId)->toBe('as:district:eastern')
        ->and($byId->get('as:county:fitiuta')->parentSourceId)->toBe('as:district:manua')
        ->and($byId->get('as:atoll:swains')->code)->toBe('04');
});

it('bundles the single American Samoa postcode code-only', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AS', $dir . '/american-samoa-postal-codes.csv', $dir . '/american-samoa-postal-code-areas.csv', 'aiarmada.addressing.american_samoa');

    $postcodes = $source->postalCodes()->collect();

    // UPU asm profile + GeoNames AS dump: territory-wide 96799
    // with no area links.
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('96799');
});
