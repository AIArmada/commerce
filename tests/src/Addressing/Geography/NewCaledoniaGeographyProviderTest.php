<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified New Caledonia tree with the B8 areas fixes', function (): void {
    $areas = app(NewCaledoniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // B8: Koné wiki-markup strip; Poya parent South->North (WP
    // census split 2592/2802 + GeoNames Province Nord admin tag).
    // No ISO 3166-2:NC codes exist.
    expect($areas->where('level', 1))->toHaveCount(3)
        ->and($areas->where('type', 'commune'))->toHaveCount(33)
        ->and($byId->get('nc:commune:kone')->name)->toBe('Koné')
        ->and($byId->get('nc:commune:poya')->parentSourceId)->toBe('nc:province:north-province')
        ->and($areas->where('parentSourceId', 'nc:province:north-province'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'nc:province:south-province'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'nc:province:loyalty-islands-province'))->toHaveCount(3);
});

it('bundles the 50 New Caledonia postcodes as single commune primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NC', $dir . '/new-caledonia-postal-codes.csv', $dir . '/new-caledonia-postal-code-areas.csv', 'aiarmada.addressing.new_caledonia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(50)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // OPT-NC Feb-2025 table + GeoNames NC.txt 50/50, exact.
    // 98880 LA FOA vs 98881 FARINO (the GeoNames "Farino 98880"
    // row is a locality artefact, not a second leg).
    expect((string) $byCode->get('98800')->areaSourceId)->toBe('nc:commune:noumea')
        ->and((string) $byCode->get('98880')->areaSourceId)->toBe('nc:commune:la-foa')
        ->and((string) $byCode->get('98881')->areaSourceId)->toBe('nc:commune:farino')
        ->and((string) $byCode->get('98877')->areaSourceId)->toBe('nc:commune:poya')
        ->and((string) $byCode->get('98859')->areaSourceId)->toBe('nc:commune:kone');
});
