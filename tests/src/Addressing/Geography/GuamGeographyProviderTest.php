<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('leads renamed villages with the official Chamorro name', function (): void {
    $areas = app(GuamGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(19)
        ->and($areas->get('gu:village:hagat')->name)->toBe('Hågat (Agat)')
        ->and($areas->get('gu:village:inarajan-inalahan')->name)->toBe('Inalåhan (Inarajan)')
        ->and($areas->get('gu:village:merizo-malesso')->name)->toBe('Malesso\' (Merizo)')
        ->and($areas->get('gu:village:santa-rita-santa-rita-sumai')->name)->toBe('Sånta Rita-Sumai (Santa Rita)')
        ->and($areas->get('gu:village:talofofo-talofofo')->name)->toBe('Talo\'fo\'fo (Talofofo)')
        ->and($areas->get('gu:village:umatac-humatak')->name)->toBe('Humåtak (Umatac)');
});

it('bundles the 21 Guam ZIPs as single village primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GU', $dir . '/guam-postal-codes.csv', $dir . '/guam-postal-code-areas.csv', 'aiarmada.addressing.guam');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(21)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // GeoNames GU dump, exact (USPS city mapping). Multi-code
    // villages: Hagåtña 96910+96932, Tamuning 96911+96931,
    // Barrigada 96913+96921.
    expect((string) $byCode->get('96910')->areaSourceId)->toBe('gu:village:hagatna')
        ->and((string) $byCode->get('96932')->areaSourceId)->toBe('gu:village:hagatna')
        ->and((string) $byCode->get('96912')->areaSourceId)->toBe('gu:village:dededo')
        ->and((string) $byCode->get('96929')->areaSourceId)->toBe('gu:village:yigo');

    // Held out: 96920/96924 unassigned (absent from GN + mirrors);
    // Chalan Pago-Ordot has no post office (96910 spill overlap is
    // ZCTA-style, not USPS city assignment, so no secondary).
    expect($byCode->has('96920'))->toBeFalse()
        ->and($byCode->has('96924'))->toBeFalse();
});
