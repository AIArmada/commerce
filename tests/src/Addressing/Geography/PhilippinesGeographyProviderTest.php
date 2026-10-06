<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 1656 municipalities and cities under provinces plus NCR', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(1656)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(149)
        ->and($l2->where('type', 'municipality'))->toHaveCount(1493)
        ->and($l2->where('type', 'sub_municipality'))->toHaveCount(14)
        ->and($byId->get('ph:sub_municipality:tondo-i-ii')->parentSourceId)->toBe('ph:region:national-capital-region');
});

it('ships 42011 barangays under their municipalities', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('level', 3);

    expect($l3)->toHaveCount(42011)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p) && $byId->get($p)->level === 2))->toBeTrue()
        ->and($byId->get('ph:barangay:50th-district')->parentSourceId)->toBe('ph:city:city-of-ozamiz');
});

it('pins the B22 tree pass: 3 PSGC-literal renames, H1 forward-delta held', function (): void {
    $areas = app(PhilippinesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('ph:barangay:araceli:sto-nino')->name)->toBe('Santo Niño')
        ->and($byId->get('ph:barangay:abra-de-ilog:sta-maria')->name)->toBe('Santa Maria')
        ->and($byId->get('ph:barangay:mangingisda')->name)->toBe('Barangay ng mga Mangingisda')
        ->and($byId->get('ph:barangay:city-of-calaca:san-rafael')->name)->toBe('San Rafael')
        ->and($byId->get('ph:barangay:lubusan')->name)->toBe('Lubusan')
        ->and($byId->get('ph:municipality:sanchez-mira')->name)->toBe('Sanchez-Mira');
});

it('pins the B22 postal pass: 15 drops, 2 relegs, 2222 add', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PH', $dir . '/philippines-postal-codes.csv', $dir . '/philippines-postal-code-areas.csv', 'aiarmada.addressing.philippines');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1919)
        ->and($postcodes)->toHaveCount(1919);

    foreach (['0905', '1099', '2566', '4532', '6088', '6099', '8103', '8108', '8109', '8110', '8111', '8114', '8115', '8116', '8117'] as $dropped) {
        expect($byCode->has($dropped))->toBeFalse();
    }

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('9610'))->toBe('ph:province:maguindanao-del-sur')
        ->and($primary('9612'))->toBe('ph:province:maguindanao-del-sur')
        ->and($primary('2222'))->toBe('ph:province:zambales')
        ->and($primary('1045'))->toBe('ph:region:national-capital-region')
        ->and($primary('8016'))->toBe('ph:province:davao-del-sur');
});
