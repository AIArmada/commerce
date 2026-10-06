<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified US Virgin Islands tree of districts and subdistricts', function (): void {
    $areas = app(USVirginIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(3)
        ->and($areas->where('type', 'subdistrict'))->toHaveCount(20);

    // No ISO 3166-2:VI codes. Names + parents 23/23 vs Statoids
    // (Saint Croix 9, Saint Thomas 7, Saint John 4).
    expect($byId->get('vi:district:saint-croix')->code)->toBe('SC')
        ->and($byId->get('vi:district:saint-john')->code)->toBe('SJ')
        ->and($byId->get('vi:district:saint-thomas')->code)->toBe('ST')
        ->and($byId->get('vi:subdistrict:frederiksted')->parentSourceId)->toBe('vi:district:saint-croix')
        ->and($byId->get('vi:subdistrict:water-island')->parentSourceId)->toBe('vi:district:saint-thomas')
        ->and($byId->get('vi:subdistrict:cruz-bay')->parentSourceId)->toBe('vi:district:saint-john');
});

it('bundles the 16 US Virgin Islands ZIPs at district level', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('VI', $dir . '/us-virgin-islands-postal-codes.csv', $dir . '/us-virgin-islands-postal-code-areas.csv', 'aiarmada.addressing.us_virgin_islands');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(16)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // GeoNames VI dump + UPU vir range, exact at district level
    // (no public per-subdistrict directory exists).
    expect((string) $byCode->get('00801')->areaSourceId)->toBe('vi:district:saint-thomas')
        ->and((string) $byCode->get('00820')->areaSourceId)->toBe('vi:district:saint-croix')
        ->and((string) $byCode->get('00830')->areaSourceId)->toBe('vi:district:saint-john')
        ->and((string) $byCode->get('00850')->areaSourceId)->toBe('vi:district:saint-croix');
});
