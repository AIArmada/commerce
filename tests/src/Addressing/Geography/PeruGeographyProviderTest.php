<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 196 provinces with the B16 Loreto ubigeo fix and renames', function (): void {
    $areas = app(PeruGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(222)
        ->and($areas->where('type', 'province'))->toHaveCount(196)
        ->and($byId->get('pe:province:requena')->code)->toBe('1605')
        ->and($byId->get('pe:province:ucayali')->code)->toBe('1606')
        ->and($byId->get('pe:province:datem-del-maranon')->code)->toBe('1607')
        ->and($byId->get('pe:province:putumayo')->code)->toBe('1608')
        ->and($byId->get('pe:province:antonio-raimondi')->name)->toBe('Antonio Raimondi')
        ->and($byId->has('pe:province:antonio-raymondi'))->toBeFalse()
        ->and($byId->get('pe:province:daniel-alcides-carrion')->name)->toBe('Daniel Alcides Carrión')
        ->and($byId->get('pe:province:cusco')->name)->toBe('Cusco');
});

it('links 2671 postcodes with the B16 Loreto remaps and Lambayeque duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PE', $dir . '/peru-postal-codes.csv', $dir . '/peru-postal-code-areas.csv', 'aiarmada.addressing.peru');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2671);

    $byCode = $postcodes->groupBy->code;

    // GN row-count plurality primaries (14000 Chiclayo 72 > Lambayeque 62; 14013 Lambayeque 7 > Chiclayo 1).
    expect($byCode->get('14000')->map->areaSourceId->sort()->values()->all())
        ->toBe(['pe:province:chiclayo', 'pe:province:lambayeque'])
        ->and($byCode->get('14000')->where('isPrimary', true)->first()->areaSourceId)->toBe('pe:province:chiclayo')
        ->and($byCode->get('14013')->map->areaSourceId->sort()->values()->all())
        ->toBe(['pe:province:chiclayo', 'pe:province:lambayeque'])
        ->and($byCode->get('14013')->where('isPrimary', true)->first()->areaSourceId)->toBe('pe:province:lambayeque');

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Loreto rotation remaps (GN-unanimous admin2).
    expect($primary('16110'))->toBe('pe:province:putumayo')
        ->and($primary('16320'))->toBe('pe:province:requena')
        ->and($primary('16440'))->toBe('pe:province:ucayali')
        ->and($primary('16600'))->toBe('pe:province:datem-del-maranon')
        ->and($primary('08000'))->toBe('pe:province:cusco');
});
