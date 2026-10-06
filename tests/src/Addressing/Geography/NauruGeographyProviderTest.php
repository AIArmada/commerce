<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Nauru\NauruGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Nauru tree of 14 districts', function (): void {
    $areas = app(NauruGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(14)
        ->and($areas->where('level', 1))->toHaveCount(14);

    // ISO 3166-2:NR codes, exact set. NR-05 stays "Baiti" (Statoids
    // + ISO-noted local variant; Baitsi held out).
    expect($byId->get('nr:district:aiwo')->code)->toBe('01')
        ->and($byId->get('nr:district:anabar')->code)->toBe('02')
        ->and($byId->get('nr:district:anetan')->code)->toBe('03')
        ->and($byId->get('nr:district:anibare')->code)->toBe('04')
        ->and($byId->get('nr:district:baiti')->code)->toBe('05')
        ->and($byId->get('nr:district:boe')->code)->toBe('06')
        ->and($byId->get('nr:district:buada')->code)->toBe('07')
        ->and($byId->get('nr:district:denigomodu')->code)->toBe('08')
        ->and($byId->get('nr:district:ewa')->code)->toBe('09')
        ->and($byId->get('nr:district:ijuw')->code)->toBe('10')
        ->and($byId->get('nr:district:meneng')->code)->toBe('11')
        ->and($byId->get('nr:district:nibok')->code)->toBe('12')
        ->and($byId->get('nr:district:uaboe')->code)->toBe('13')
        ->and($byId->get('nr:district:yaren')->code)->toBe('14');
});

it('bundles the single Nauru postcode code-only', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NR', $dir . '/nauru-postal-codes.csv', $dir . '/nauru-postal-code-areas.csv', 'aiarmada.addressing.nauru');

    $postcodes = $source->postalCodes()->collect();

    // UPU nru profile + GeoNames NR dump: island-wide NRU68 with no
    // area links (district-only addressing).
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('NRU68');
});
