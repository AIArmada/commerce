<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 33 departments with 1141 municipalities and localities under L1 parents', function (): void {
    $areas = app(ColombiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(1174)
        ->and($l1)->toHaveCount(33)
        ->and($l2)->toHaveCount(1141)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    // B20 F1 add + N-series renames (source_ids unchanged).
    expect($byId->get('co:municipality:san-jacinto-del-cauca')->parentSourceId)->toBe('co:department:bolivar')
        ->and($byId->get('co:municipality:talaiga-nuevo')->name)->toBe('Talaigua Nuevo')
        ->and($byId->get('co:municipality:bolivar')->name)->toBe('Ciudad Bolívar')
        ->and($byId->get('co:municipality:pinto')->name)->toBe('Santa Bárbara de Pinto')
        ->and($byId->get('co:municipality:cartagena')->name)->toBe('Cartagena de Indias')
        ->and($byId->get('co:municipality:san-andres')->name)->toBe('San Andrés de Cuerquía')
        ->and($byId->get('co:municipality:san-pedro')->name)->toBe('San Pedro de los Milagros')
        // Officially identical display names coexist, scoped by parent.
        ->and($byId->get('co:municipality:los-robles-la-paz')->name)->toBe('La Paz')
        ->and($byId->get('co:municipality:la-paz')->name)->toBe('La Paz')
        // Verified keeps.
        ->and($byId->get('co:municipality:san-vicente')->name)->toBe('San Vicente')
        ->and($byId->get('co:municipality:alban')->name)->toBe('Albán');
});

it('pins the B20 postal fixes: 5 added codes and 81 Bogota locality retargets', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CO', $dir . '/colombia-postal-codes.csv', $dir . '/colombia-postal-code-areas.csv', 'aiarmada.addressing.colombia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(3681)
        ->and($postcodes)->toHaveCount(3681);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('134060'))->toBe('co:municipality:san-jacinto-del-cauca')
        ->and($primary('134068'))->toBe('co:municipality:san-jacinto-del-cauca')
        ->and($primary('474001'))->toBe('co:municipality:pinto')
        ->and($primary('474007'))->toBe('co:municipality:pinto')
        ->and($primary('110111'))->toBe('co:locality:usaquen')
        ->and($primary('110211'))->toBe('co:locality:chapinero')
        ->and($primary('111111'))->toBe('co:locality:suba')
        ->and($primary('112041'))->toBe('co:locality:sumapaz')
        ->and($postcodes->pluck('areaSourceId')->contains('co:capital_district:bogota-d-c'))->toBeFalse();
});
