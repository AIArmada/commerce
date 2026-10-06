<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('types the sheadings with the singular type key', function (): void {
    $areas = app(IsleOfManGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('type', 'sheading'))->toHaveCount(6)
        ->and($areas->where('type', 'sheadings'))->toBeEmpty()
        ->and($areas->get('im:sheading:ayre')->code)->toBe('01');
});

it('bundles the 9 IM districts across 27 parish links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IM', $dir . '/isle-of-man-postal-codes.csv', $dir . '/isle-of-man-postal-code-areas.csv', 'aiarmada.addressing.isle_of_man');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(27)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(9);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // WP IM postcode-area coverage + GeoNames IM dump + Photon
    // hamlet checks. IM4 takes 7 areas (Marown via Braaid/Crosby);
    // IM7 Ballasalla GN row is centroid noise (IM9/Malew).
    expect($primary('IM1'))->toBe('im:town:douglas')
        ->and($primary('IM4'))->toBe('im:parish:braddan')
        ->and($primary('IM6'))->toBe('im:district:michael')
        ->and($primary('IM7'))->toBe('im:parish:garff')
        ->and($primary('IM9'))->toBe('im:parish:arbory-and-rushen')
        ->and($byCode->get('IM4'))->toHaveCount(7)
        ->and($byCode->get('IM7'))->toHaveCount(6)
        ->and($byCode->has('IM86'))->toBeFalse()
        ->and($byCode->has('IM99'))->toBeFalse();
});
