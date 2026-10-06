<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 981 communes and cities under districts with parent links', function (): void {
    $areas = app(MoldovaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(981)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(66)
        ->and($l2->where('type', 'commune'))->toHaveCount(915)
        ->and($l2->where('parentSourceId', 'md:territorial_unit:transnistria'))->toHaveCount(79)
        ->and($byId->get('md:city:donduseni')->name)->toBe('Dondușeni (city)')
        ->and($byId->get('md:commune:donduseni')->parentSourceId)->toBe('md:district:donduseni');
});

it('pins the B20 GN-admin1 leg fixes: Basarabeasca, Bender zone, Roghi, MD-5219', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MD', $dir . '/moldova-postal-codes.csv', $dir . '/moldova-postal-code-areas.csv', 'aiarmada.addressing.moldova');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1215)
        ->and($postcodes)->toHaveCount(1220);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Basarabeasca unfolded from Cimișlia; Troițcoe correctly stays.
    expect($primary('MD-6701'))->toBe('md:district:basarabeasca')
        ->and($primary('MD-6716'))->toBe('md:district:basarabeasca')
        ->and($primary('MD-6717'))->toBe('md:district:cimislia')
        // Right-bank Bender zone out of Transnistria (de-jure Law 764).
        ->and($primary('MD-3200'))->toBe('md:city:bender')
        ->and($primary('MD-3251'))->toBe('md:district:anenii-noi')
        ->and($primary('MD-3252'))->toBe('md:city:bender')
        ->and($primary('MD-4316'))->toBe('md:district:causeni')
        ->and($primary('MD-3351'))->toBe('md:district:causeni')
        ->and($primary('MD-5714'))->toBe('md:district:causeni')
        ->and($primary('MD-4523'))->toBe('md:district:dubasari')
        // Stepanovca leg deleted; Lazo code added.
        ->and($byCode->get('MD-5222'))->toHaveCount(1)
        ->and($primary('MD-5219'))->toBe('md:district:drochia');
});
