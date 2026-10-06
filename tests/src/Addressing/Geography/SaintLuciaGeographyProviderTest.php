<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saint Lucia tree of 10 quarters', function (): void {
    $areas = app(SaintLuciaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(10)
        ->and($areas->where('level', 1))->toHaveCount(10);

    // ISO 3166-2:LC codes (04/09 unassigned: former Dauphin +
    // Praslin quarters), exact mapping incl. LC-12 Canaries.
    expect($byId->get('lc:district:anse-la-raye')->code)->toBe('01')
        ->and($byId->get('lc:district:castries')->code)->toBe('02')
        ->and($byId->get('lc:district:choiseul')->code)->toBe('03')
        ->and($byId->get('lc:district:dennery')->code)->toBe('05')
        ->and($byId->get('lc:district:gros-islet')->code)->toBe('06')
        ->and($byId->get('lc:district:laborie')->code)->toBe('07')
        ->and($byId->get('lc:district:micoud')->code)->toBe('08')
        ->and($byId->get('lc:district:soufriere')->code)->toBe('10')
        ->and($byId->get('lc:district:vieux-fort')->code)->toBe('11')
        ->and($byId->get('lc:district:canaries')->code)->toBe('12');
});

it('bundles the 47 Saint Lucian delivery codes with the Marisule dual', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LC', $dir . '/saint-lucia-postal-codes.csv', $dir . '/saint-lucia-postal-code-areas.csv', 'aiarmada.addressing.saint_lucia');

    $postcodes = $source->postalCodes()->collect();

    // Government postcode table: 47 delivery codes + the Marisule
    // secondary = 48 link rows; 7 private letter-box codes excluded.
    expect($postcodes)->toHaveCount(48);

    $primaries = $postcodes->where('isPrimary', true);

    expect($primaries)->toHaveCount(47);

    $byCode = $primaries->keyBy->code;

    // Marisule LC01 501: Castries primary per the government
    // table, Gros Islet secondary (border area).
    expect((string) $byCode->get('LC01 501')->areaSourceId)->toBe('lc:district:castries')
        ->and($byCode->get('LC01 501')->isPrimary)->toBeTrue();

    // Babonneau town codes sit in Castries Quarter.
    expect((string) $byCode->get('LC02 101')->areaSourceId)->toBe('lc:district:castries')
        ->and((string) $byCode->get('LC02 301')->areaSourceId)->toBe('lc:district:castries')
        ->and((string) $byCode->get('LC02 401')->areaSourceId)->toBe('lc:district:castries');

    // UPU lcaEn anchors.
    expect((string) $byCode->get('LC05 201')->areaSourceId)->toBe('lc:district:castries')
        ->and((string) $byCode->get('LC04 101')->areaSourceId)->toBe('lc:district:castries')
        ->and((string) $byCode->get('LC10 101')->areaSourceId)->toBe('lc:district:choiseul')
        ->and((string) $byCode->get('LC03 201')->areaSourceId)->toBe('lc:district:castries');

    // Private letter-box codes stay out.
    expect($byCode->has('LC01 601'))->toBeFalse()
        ->and($byCode->has('LC02 601'))->toBeFalse()
        ->and($byCode->has('LC02 701'))->toBeFalse()
        ->and($byCode->has('LC02 801'))->toBeFalse()
        ->and($byCode->has('LC03 301'))->toBeFalse()
        ->and($byCode->has('LC07 301'))->toBeFalse()
        ->and($byCode->has('LC12 301'))->toBeFalse();

    $counts = $primaries->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('lc:district:castries'))->toBe(20)
        ->and($counts->get('lc:district:dennery'))->toBe(5)
        ->and($counts->get('lc:district:gros-islet'))->toBe(4)
        ->and($counts->get('lc:district:anse-la-raye'))->toBe(4)
        ->and($counts->get('lc:district:micoud'))->toBe(4);
});
