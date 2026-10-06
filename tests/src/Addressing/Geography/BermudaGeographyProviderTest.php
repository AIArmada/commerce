<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 9 parishes plus the two municipalities under their parishes', function (): void {
    $areas = app(BermudaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(9)
        ->and($areas->where('type', 'municipality'))->toHaveCount(2)
        ->and($byId->get('bm:municipality:hamilton')->parentSourceId)->toBe('bm:parish:pembroke')
        ->and($byId->get('bm:municipality:saint-georges')->parentSourceId)->toBe('bm:parish:saint-georges');
});

it('pins the BPO-verified municipality display name', function (): void {
    $areas = app(BermudaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // BPO Blue Pages spells it with the period; no ISO codes exist for BM.
    expect($byId->get('bm:municipality:saint-georges')->name)->toBe('Town of St. George')
        ->and($byId->get('bm:parish:hamilton')->code)->toBe('HA');
});

it('bundles the 80 BPO street postcodes across 113 parish links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BM', $dir . '/bermuda-postal-codes.csv', $dir . '/bermuda-postal-code-areas.csv', 'aiarmada.addressing.bermuda');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(113)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(80);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // B4 primary moves from the BPO street vote: DV 04 Paget 18v2,
    // FL 01 Devonshire 13v6, FL 03 Devonshire 17v10, FL 04 Hamilton
    // 18v11, SB 04 Southampton 25v0, WK 01 Southampton 13v7, HS 02
    // Saint George's 15v13v9 plurality, GE 03/GE 05 Town majorities.
    expect($primary('DV 04'))->toBe('bm:parish:paget')
        ->and($primary('FL 01'))->toBe('bm:parish:devonshire')
        ->and($primary('FL 03'))->toBe('bm:parish:devonshire')
        ->and($primary('FL 04'))->toBe('bm:parish:hamilton')
        ->and($primary('SB 04'))->toBe('bm:parish:southampton')
        ->and($primary('WK 01'))->toBe('bm:parish:southampton')
        ->and($primary('HS 02'))->toBe('bm:parish:saint-georges')
        ->and($primary('GE 03'))->toBe('bm:municipality:saint-georges')
        ->and($primary('GE 05'))->toBe('bm:municipality:saint-georges');

    // HM restructure: BPO lists every HM street under a parish, with
    // City rows only for HM 08/09/10/11/12/17/19 (GeoNames agrees on
    // the exact same set); Pembroke-only codes dropped the city link.
    expect($primary('HM 01'))->toBe('bm:parish:pembroke')
        ->and($primary('HM 10'))->toBe('bm:municipality:hamilton')
        ->and($primary('HM 16'))->toBe('bm:parish:pembroke')
        ->and($byCode->get('HM 01'))->toHaveCount(1)
        ->and($byCode->get('HM 19'))->toHaveCount(3);

    // Duals and triples: DV 03 exact 12v12 tie keeps Devonshire first,
    // PG 01 near-tie keeps Paget, GE 01 is parish-only (0 town rows in
    // BPO and GeoNames), box codes stay excluded.
    expect($primary('DV 03'))->toBe('bm:parish:devonshire')
        ->and($primary('PG 01'))->toBe('bm:parish:paget')
        ->and($byCode->get('GE 01'))->toHaveCount(1)
        ->and($byCode->get('HS 02'))->toHaveCount(3)
        ->and($byCode->has('GE CX'))->toBeFalse();
});
