<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 22 departments with 340 municipalities under department parents', function (): void {
    $areas = app(GuatemalaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(362)
        ->and($l1)->toHaveCount(22)
        ->and($l2)->toHaveCount(340)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('gt:department:guatemala')->code)->toBe('01')
        ->and($byId->get('gt:department:sacatepequez')->code)->toBe('03')
        ->and($byId->get('gt:department:peten')->code)->toBe('17')
        ->and($byId->get('gt:department:jutiapa')->code)->toBe('22');
});

it('pins the B19 municipality renames with the old slug retired', function (): void {
    $areas = app(GuatemalaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('gt:municipality:antigua')->name)->toBe('Antigua Guatemala')
        ->and($byId->get('gt:municipality:antigua')->parentSourceId)->toBe('gt:department:sacatepequez')
        ->and($byId->get('gt:municipality:san-bartolo')->name)->toBe('San Bartolo Aguas Calientes')
        ->and($byId->get('gt:municipality:san-bartolo')->parentSourceId)->toBe('gt:department:totonicapan')
        ->and($byId->get('gt:municipality:quezaltepeque')->name)->toBe('Quezaltepeque')
        ->and($byId->has('gt:municipality:quetzaltepeque'))->toBeFalse();
});

it('pins 549 L1-linked codes with the 01025 Zona 25 fill', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GT', $dir . '/guatemala-postal-codes.csv', $dir . '/guatemala-postal-code-areas.csv', 'aiarmada.addressing.guatemala');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(549)
        ->and($postcodes)->toHaveCount(549);

    expect($byCode->get('01025')->first()->areaSourceId)->toBe('gt:department:guatemala')
        ->and($byCode->get('17001')->first()->areaSourceId)->toBe('gt:department:peten')
        ->and($byCode->has('01000'))->toBeFalse();
});
