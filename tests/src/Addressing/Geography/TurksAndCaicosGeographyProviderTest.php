<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Turks and Caicos tree of 6 districts', function (): void {
    $areas = app(TurksAndCaicosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(6)
        ->and($areas->where('level', 1))->toHaveCount(6);

    // No ISO 3166-2:TC codes; 01-06 synthetic. Six districts (2
    // Turks + 4 Caicos); East Caicos administers under South
    // Caicos, West Caicos under Providenciales.
    expect($byId->get('tc:district:providenciales')->code)->toBe('01')
        ->and($byId->get('tc:district:north-caicos')->code)->toBe('02')
        ->and($byId->get('tc:district:middle-caicos')->code)->toBe('03')
        ->and($byId->get('tc:district:south-caicos')->code)->toBe('04')
        ->and($byId->get('tc:district:grand-turk')->code)->toBe('05')
        ->and($byId->get('tc:district:salt-cay')->code)->toBe('06');
});

it('bundles the single Turks and Caicos postcode code-only', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TC', $dir . '/turks-and-caicos-postal-codes.csv', $dir . '/turks-and-caicos-postal-code-areas.csv', 'aiarmada.addressing.turks_and_caicos');

    $postcodes = $source->postalCodes()->collect();

    // UPU tcaEn: single TKCA 1ZZ for the whole territory;
    // code-only import with no area links.
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('TKCA 1ZZ');
});
