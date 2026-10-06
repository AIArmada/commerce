<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 16 voivodeships with 380 counties under voivodeship parents', function (): void {
    $areas = app(PolandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(396)
        ->and($l1)->toHaveCount(16)
        ->and($l2)->toHaveCount(380)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('pl:voivodeship:mazovia')->code)->toBe('14')
        ->and($byId->get('pl:voivodeship:silesia')->code)->toBe('24')
        ->and($byId->get('pl:voivodeship:greater-poland')->code)->toBe('30')
        ->and($byId->get('pl:land_county:powiat-sredzki')->parentSourceId)->toBe('pl:voivodeship:lower-silesia')
        ->and($byId->get('pl:land_county:greater-poland:powiat-sredzki')->parentSourceId)->toBe('pl:voivodeship:greater-poland');
});

it('pins the B19 rounds 1+2 pass: twin flips, swaps, artifact drops, stale-code drops', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PL', $dir . '/poland-postal-codes.csv', $dir . '/poland-postal-code-areas.csv', 'aiarmada.addressing.poland');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(20248)
        ->and($postcodes)->toHaveCount(20395);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Same-name-twin flips (bundle joined the wrong voivodeship twin).
    expect($primary('55-300'))->toBe('pl:land_county:powiat-sredzki')
        ->and($primary('58-100'))->toBe('pl:land_county:powiat-swidnicki')
        ->and($primary('22-600'))->toBe('pl:land_county:powiat-tomaszowski')
        ->and($primary('24-300'))->toBe('pl:land_county:powiat-opolski')
        ->and($primary('66-600'))->toBe('pl:land_county:powiat-krosnienski')
        ->and($primary('32-800'))->toBe('pl:land_county:powiat-brzeski')
        ->and($primary('05-825'))->toBe('pl:land_county:powiat-grodziski')
        ->and($primary('05-100'))->toBe('pl:land_county:powiat-nowodworski')
        ->and($primary('07-300'))->toBe('pl:land_county:powiat-ostrowski')
        ->and($primary('17-100'))->toBe('pl:land_county:powiat-bielski');

    // Swaps + artifact drops + tie keep.
    expect($primary('15-521'))->toBe('pl:land_county:powiat-bialostocki')
        ->and($primary('82-300'))->toBe('pl:land_county:powiat-elblaski')
        ->and($byCode->get('50-003')->pluck('areaSourceId')->contains('pl:land_county:powiat-wroclawski'))->toBeFalse()
        ->and($primary('89-525'))->toBe('pl:land_county:powiat-tucholski');

    // Round 2: one pin per fix class.
    expect($primary('07-304'))->toBe('pl:land_county:powiat-ostrowski')
        ->and($primary('05-101'))->toBe('pl:land_county:powiat-nowodworski')
        ->and($byCode->get('05-101')->pluck('areaSourceId')->contains('pl:land_county:powiat-legionowski'))->toBeTrue()
        ->and($primary('43-300'))->toBe('pl:city_county:bielsko-biala')
        ->and($byCode->get('43-300'))->toHaveCount(1)
        ->and($primary('07-401'))->toBe('pl:city_county:ostroleka')
        ->and($byCode->get('00-100')->pluck('areaSourceId')->contains('pl:land_county:powiat-plonski'))->toBeFalse()
        ->and($primary('32-312'))->toBe('pl:land_county:powiat-olkuski')
        ->and($byCode->has('00-320'))->toBeFalse();
});
