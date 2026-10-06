<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guernsey\GuernseyGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Guernsey tree of 10 parishes and 2 dependencies', function (): void {
    $areas = app(GuernseyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(10)
        ->and($areas->where('type', 'dependency'))->toHaveCount(2)
        ->and($areas->where('level', 1))->toHaveCount(12);

    // No ISO 3166-2:GG codes; Statoids names. St-spellings kept per
    // WP prose + UPU usage; Herm/Jethou stay inside St Peter Port.
    expect($byId->get('gg:parish:st-peter-port')->name)->toBe('St Peter Port')
        ->and($byId->get('gg:parish:st-pierre-du-bois')->name)->toBe('St Pierre du Bois')
        ->and($byId->get('gg:dependency:alderney')->name)->toBe('Alderney')
        ->and($byId->get('gg:dependency:sark')->name)->toBe('Sark')
        ->and($byId->has('gg:parish:herm'))->toBeFalse();
});

it('bundles the 10 GY districts across 13 parish links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GG', $dir . '/guernsey-postal-codes.csv', $dir . '/guernsey-postal-code-areas.csv', 'aiarmada.addressing.guernsey');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(13)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(10);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // GY postcode-area table + GeoNames GG dump agree on both sides
    // of every dual; primaries alphabetical.
    expect($primary('GY1'))->toBe('gg:parish:st-peter-port')
        ->and($primary('GY9'))->toBe('gg:dependency:alderney')
        ->and($primary('GY10'))->toBe('gg:dependency:sark')
        ->and($primary('GY6'))->toBe('gg:parish:st-andrew')
        ->and($primary('GY7'))->toBe('gg:parish:st-pierre-du-bois')
        ->and($primary('GY8'))->toBe('gg:parish:forest')
        ->and($byCode->get('GY6'))->toHaveCount(2)
        ->and($byCode->get('GY7'))->toHaveCount(2)
        ->and($byCode->get('GY8'))->toHaveCount(2);
});
