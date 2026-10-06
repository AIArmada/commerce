<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Switzerland\SwitzerlandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 26 cantons and 146 districts with corrected names', function (): void {
    $areas = app(SwitzerlandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(26)
        ->and($areas->where('level', 2))->toHaveCount(146)
        // BFS commune register 2026-01-01 spellings.
        ->and($byId->get('ch:district:jura-north-vaudois')->name)->toBe('Jura-Nord vaudois')
        ->and($byId->get('ch:district:zurich')->name)->toBe('Zürich')
        // EN-Wikipedia Districts form kept for Moutier.
        ->and($byId->get('ch:district:moutier-district')->name)->toBe('Moutier District');
});

it('links 3177 codes with 3220 legs and 42 multis', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CH', $dir . '/switzerland-postal-codes.csv', $dir . '/switzerland-postal-code-areas.csv', 'aiarmada.addressing.switzerland');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3220);

    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(3177)
        ->and($byCode->filter(fn ($legs) => $legs->count() > 1))->toHaveCount(42);
});

it('pins post-reform links: Moutier, Clavaleyres, campus code', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CH', $dir . '/switzerland-postal-codes.csv', $dir . '/switzerland-postal-code-areas.csv', 'aiarmada.addressing.switzerland');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    // 2740 Moutier: primary Moutier district (2026 JU transfer), Jura
    // bernois secondary (Roches sliver).
    expect($byCode->get('2740')->where('isPrimary', true)->first()->areaSourceId)->toBe('ch:district:moutier-district')
        ->and($byCode->get('2740'))->toHaveCount(2)
        // 1595: Broye-Vully primary + See/Lac secondary (Clavaleyres
        // merged into Murten 2022).
        ->and($byCode->get('1595')->where('isPrimary', true)->first()->areaSourceId)->toBe('ch:district:broye-vully')
        ->and($byCode->get('1595')->pluck('areaSourceId')->sort()->values()->all())->toBe(['ch:district:broye-vully', 'ch:district:see-lac'])
        // 1015: single Ouest lausannois (EPFL/UNIL campus, zero
        // Lausanne-commune rows in swisstopo).
        ->and($byCode->get('1015')->pluck('areaSourceId')->all())->toBe(['ch:district:ouest-lausannois'])
        // Stale-GeoNames duals dissolved to singles.
        ->and($byCode->get('1911')->pluck('areaSourceId')->all())->toBe(['ch:district:martigny'])
        ->and($byCode->get('6825')->pluck('areaSourceId')->all())->toBe(['ch:district:mendrisio'])
        ->and($byCode->get('2333')->pluck('areaSourceId')->all())->toBe(['ch:district:jura-bernois'])
        // Locality-population primaries (not commune pops).
        ->and($byCode->get('3994')->where('isPrimary', true)->first()->areaSourceId)->toBe('ch:district:goms')
        ->and($byCode->get('1958')->where('isPrimary', true)->first()->areaSourceId)->toBe('ch:district:sierre');
});
