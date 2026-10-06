<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Anguilla\AnguillaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Anguilla tree of 14 districts', function (): void {
    $areas = app(AnguillaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(14)
        ->and($areas->where('level', 1))->toHaveCount(14);

    // No ISO 3166-2:AI codes; 01-14 synthetic. Names 14/14 vs Statoids.
    expect($byId->get('ai:district:blowing-point')->code)->toBe('01')
        ->and($byId->get('ai:district:east-end')->code)->toBe('02')
        ->and($byId->get('ai:district:george-hill')->code)->toBe('03')
        ->and($byId->get('ai:district:island-harbour')->code)->toBe('04')
        ->and($byId->get('ai:district:north-hill')->code)->toBe('05')
        ->and($byId->get('ai:district:north-side')->code)->toBe('06')
        ->and($byId->get('ai:district:sandy-ground')->code)->toBe('07')
        ->and($byId->get('ai:district:sandy-hill')->code)->toBe('08')
        ->and($byId->get('ai:district:south-hill')->code)->toBe('09')
        ->and($byId->get('ai:district:stoney-ground')->code)->toBe('10')
        ->and($byId->get('ai:district:the-farrington')->code)->toBe('11')
        ->and($byId->get('ai:district:the-quarter')->code)->toBe('12')
        ->and($byId->get('ai:district:the-valley')->code)->toBe('13')
        ->and($byId->get('ai:district:west-end')->code)->toBe('14');
});

it('bundles the single Anguilla postcode code-only', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AI', $dir . '/anguilla-postal-codes.csv', $dir . '/anguilla-postal-code-areas.csv', 'aiarmada.addressing.anguilla');

    $postcodes = $source->postalCodes()->collect();

    // GeoNames AI dump + The Anguillian 2007: island-wide AI-2640
    // with no area links (UPU has no AI profile).
    expect($postcodes)->toHaveCount(1)
        ->and((string) $postcodes->first()->code)->toBe('AI-2640');
});
