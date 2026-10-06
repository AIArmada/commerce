<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mayotte\MayotteGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Mayotte tree of 17 communes', function (): void {
    $areas = app(MayotteGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'commune'))->toHaveCount(17)
        ->and($areas->where('level', 1))->toHaveCount(17);

    // No ISO 3166-2:YT codes; 01-17 synthetic. Names 17/17 vs
    // Hexasmal INSEE 97601-97617 + GeoNames YT admin2.
    expect($byId->get('yt:commune:dzaoudzi')->code)->toBe('01')
        ->and($byId->get('yt:commune:pamandzi')->code)->toBe('02')
        ->and($byId->get('yt:commune:mamoudzou')->code)->toBe('03')
        ->and($byId->get('yt:commune:mtsangamouji')->name)->toBe("M'Tsangamouji")
        ->and($byId->get('yt:commune:koungou')->code)->toBe('17');
});

it('bundles the 11 Mayotte postcodes across 18 commune links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('YT', $dir . '/mayotte-postal-codes.csv', $dir . '/mayotte-postal-code-areas.csv', 'aiarmada.addressing.mayotte');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(18)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(11);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Hexasmal + GeoNames YT agree on both sides of all 7 duals;
    // primaries keep bundled orientation (no contradicting signal).
    expect($primary('97600'))->toBe('yt:commune:mamoudzou')
        ->and($primary('97615'))->toBe('yt:commune:dzaoudzi')
        ->and($primary('97620'))->toBe('yt:commune:chirongui')
        ->and($primary('97630'))->toBe('yt:commune:mtsamboro')
        ->and($primary('97650'))->toBe('yt:commune:bandraboua')
        ->and($primary('97660'))->toBe('yt:commune:dembeni')
        ->and($primary('97670'))->toBe('yt:commune:ouangani')
        ->and($byCode->get('97600'))->toHaveCount(2)
        ->and($byCode->get('97680'))->toHaveCount(1);
});
