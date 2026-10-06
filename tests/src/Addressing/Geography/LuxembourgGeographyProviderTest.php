<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 12 cantons and 100 post-fusion communes with parent links', function (): void {
    $areas = app(LuxembourgGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'canton'))->toHaveCount(12)
        ->and($areas->where('type', 'commune'))->toHaveCount(100)
        ->and($byId->get('lu:commune:bous-waldbredimus')->parentSourceId)->toBe('lu:canton:remich')
        ->and($byId->get('lu:commune:groussbus-wal')->parentSourceId)->toBe('lu:canton:redange')
        ->and($byId->get('lu:commune:garnich')->parentSourceId)->toBe('lu:canton:capellen')
        ->and($areas->pluck('name'))->not->toContain('Bous', 'Septfontaines', 'Boevange-sur-Attert');
});

it('drops the 15 phantom and retired codes', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LU', $dir . '/luxembourg-postal-codes.csv', $dir . '/luxembourg-postal-code-areas.csv', 'aiarmada.addressing.luxembourg');

    $codes = $source->postalCodes()->collect()->pluck('code')->all();

    // 10 GeoNames-only phantoms + 4 retired street codes + L-4008.
    foreach (['L-3208', 'L-3556', 'L-4006', 'L-4007', 'L-4009', 'L-4100', 'L-7202', 'L-8007', 'L-8302', 'L-9203', 'L-3613', 'L-3923', 'L-4008', 'L-4262', 'L-6721'] as $dropped) {
        expect($codes)->not->toContain($dropped);
    }
});

it('files the airport code L-1110 dual with a Sandweiler primary', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LU', $dir . '/luxembourg-postal-codes.csv', $dir . '/luxembourg-postal-code-areas.csv', 'aiarmada.addressing.luxembourg');

    $legs = $source->postalCodes()->collect()->where('code', 'L-1110');

    // CACLR TR: Sandweiler/Findel airport rows + Niederanven/Senningerberg
    // Avenue de l'Aéroport; 2018 file: FINDEL/AEROPORT.
    expect($legs)->toHaveCount(2)
        ->and($legs->firstWhere('isPrimary', true)->areaSourceId)->toBe('lu:commune:sandweiler')
        ->and($legs->firstWhere('isPrimary', false)->areaSourceId)->toBe('lu:commune:niederanven');
});

it('pins the new-quarter fills and secondaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LU', $dir . '/luxembourg-postal-codes.csv', $dir . '/luxembourg-postal-code-areas.csv', 'aiarmada.addressing.luxembourg');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    // New Esch-Grenz quarter singles + new Mondercange quarter + Boxhorn survivor.
    expect($byCode->get('L-4090')->sole()->areaSourceId)->toBe('lu:commune:esch-sur-alzette')
        ->and($byCode->get('L-3942')->sole()->areaSourceId)->toBe('lu:commune:mondercange')
        ->and($byCode->get('L-9741')->sole()->areaSourceId)->toBe('lu:commune:wincrange');

    // Cross-commune secondaries: Belvaux streets span Esch+Sanem, Rue des
    // Thermes spans Strassen+Bertrange, L-9378 grows to four legs.
    $secondary = static fn (string $code): array => $byCode->get($code)->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all();

    expect($secondary('L-4374'))->toBe(['lu:commune:sanem'])
        ->and($secondary('L-8018'))->toBe(['lu:commune:bertrange'])
        ->and($byCode->get('L-9378'))->toHaveCount(4)
        ->and($byCode->get('L-9378')->firstWhere('isPrimary', true)->areaSourceId)->toBe('lu:commune:bourscheid');

    // 2026-10-06 open-case retry: L-7533 Fischbach secondary dropped
    // (BD-Adresses 18/18 Mersch + Nominatim Mersch-only); L-1634 keeps
    // its Hesperange secondary (Turbelfiels/Itzig) and L-2632 its
    // Luxembourg-city secondary, both confirmed against the same extract.
    expect($byCode->get('L-7533')->sole()->areaSourceId)->toBe('lu:commune:mersch')
        ->and($secondary('L-1634'))->toBe(['lu:commune:hesperange'])
        ->and($secondary('L-2632'))->toBe(['lu:commune:luxembourg-city']);
});
