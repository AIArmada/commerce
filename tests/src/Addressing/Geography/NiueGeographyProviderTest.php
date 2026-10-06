<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Niue\NiueGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Niue tree of 14 villages', function (): void {
    $areas = app(NiueGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'village'))->toHaveCount(14)
        ->and($areas->where('level', 1))->toHaveCount(14);

    // No ISO 3166-2:NU codes; 01-14 synthetic. Names 14/14 vs
    // Statoids Villages of Niue.
    expect($byId->get('nu:village:makefu')->code)->toBe('01')
        ->and($byId->get('nu:village:tuapa')->code)->toBe('02')
        ->and($byId->get('nu:village:namukulu')->code)->toBe('03')
        ->and($byId->get('nu:village:hikutavake')->code)->toBe('04')
        ->and($byId->get('nu:village:toi')->code)->toBe('05')
        ->and($byId->get('nu:village:mutalau')->code)->toBe('06')
        ->and($byId->get('nu:village:lakepa')->code)->toBe('07')
        ->and($byId->get('nu:village:liku')->code)->toBe('08')
        ->and($byId->get('nu:village:hakupu')->code)->toBe('09')
        ->and($byId->get('nu:village:vaiea')->code)->toBe('10')
        ->and($byId->get('nu:village:avatele')->code)->toBe('11')
        ->and($byId->get('nu:village:tamakautoga')->code)->toBe('12')
        ->and($byId->get('nu:village:alofi-south')->code)->toBe('13')
        ->and($byId->get('nu:village:alofi-north')->code)->toBe('14');
});

it('bundles the single Niue postcode code-only', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NU', $dir . '/niue-postal-codes.csv', $dir . '/niue-postal-code-areas.csv', 'aiarmada.addressing.niue');

    $postcodes = $source->postalCodes()->collect();

    // UPU niu profile + GeoNames NU dump: island-wide 9974 with no
    // area links.
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('9974');
});
