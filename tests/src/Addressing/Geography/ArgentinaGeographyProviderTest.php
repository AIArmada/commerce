<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 24 provinces/CABA with 529 departments under L1 parents', function (): void {
    $areas = app(ArgentinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(553)
        ->and($l1)->toHaveCount(24)
        ->and($l2)->toHaveCount(529)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('ar:province:salta')->code)->toBe('A')
        ->and($byId->get('ar:province:buenos-aires')->code)->toBe('B')
        ->and($byId->get('ar:city:autonomous-city-of-buenos-aires')->code)->toBe('C')
        ->and($byId->get('ar:province:corrientes')->code)->toBe('W');
});

it('pins the B19 renames with old slugs retired', function (): void {
    $areas = app(ArgentinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('ar:department:juan-martin-de-pueyrredon')->name)->toBe('Juan Martín de Pueyrredón')
        ->and($byId->has('ar:department:la-capital-san-luis'))->toBeFalse()
        ->and($byId->get('ar:department:feliciano')->name)->toBe('Feliciano')
        ->and($byId->has('ar:department:san-jose-de-feliciano'))->toBeFalse()
        ->and($byId->get('ar:department:cafayate')->name)->toBe('Cafayate')
        ->and($byId->get('ar:department:hucal')->name)->toBe('Hucal')
        ->and($byId->get('ar:department:san-miguel-corrientes')->name)->toBe('San Miguel (Corrientes)')
        ->and($byId->get('ar:department:atreuco')->name)->toBe('Atreucó')
        ->and($byId->get('ar:partido:puan')->name)->toBe('Puán');
});

it('pins the B19 postal fixes: San Miguel retargets, tie flips, CABA remap', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AR', $dir . '/argentina-postal-codes.csv', $dir . '/argentina-postal-code-areas.csv', 'aiarmada.addressing.argentina');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(2501)
        ->and($postcodes)->toHaveCount(3115);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('3483'))->toBe('ar:department:san-miguel-corrientes')
        ->and($primary('3485'))->toBe('ar:department:san-miguel-corrientes')
        ->and($primary('3151'))->toBe('ar:department:victoria')
        ->and($primary('5400'))->toBe('ar:department:capital-san-juan')
        ->and($primary('C1405'))->toBe('ar:commune:comuna-6')
        ->and($primary('C1406'))->toBe('ar:commune:comuna-7')
        ->and($primary('C1416'))->toBe('ar:commune:comuna-11')
        ->and($primary('C1424'))->toBe('ar:commune:comuna-6')
        ->and($primary('C1426'))->toBe('ar:commune:comuna-13')
        ->and($primary('C1439'))->toBe('ar:commune:comuna-8');
});
