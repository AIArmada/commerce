<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Wallis and Futuna tree of 3 kingdoms and 3 Uvea districts', function (): void {
    $areas = app(WallisAndFutunaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'administrative_precinct'))->toHaveCount(3)
        ->and($areas->where('type', 'district'))->toHaveCount(3);

    // ISO 3166-2:WF codes.
    expect($byId->get('wf:administrative_precinct:uvea')->code)->toBe('UV')
        ->and($byId->get('wf:administrative_precinct:alo')->code)->toBe('AL')
        ->and($byId->get('wf:administrative_precinct:sigave')->code)->toBe('SG')
        ->and($byId->get('wf:district:hihifo')->parentSourceId)->toBe('wf:administrative_precinct:uvea')
        ->and($byId->get('wf:district:hahake')->parentSourceId)->toBe('wf:administrative_precinct:uvea')
        ->and($byId->get('wf:district:mu-a')->parentSourceId)->toBe('wf:administrative_precinct:uvea');
});

it('bundles the 3 Wallis and Futuna postcodes as kingdom primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('WF', $dir . '/wallis-and-futuna-postal-codes.csv', $dir . '/wallis-and-futuna-postal-code-areas.csv', 'aiarmada.addressing.wallis_and_futuna');

    $postcodes = $source->postalCodes()->collect();

    // La Poste Hexasmal: 98600 UVEA, 98610 ALO, 98620 SIGAVE.
    expect($postcodes)->toHaveCount(3)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    expect((string) $byCode->get('98600')->areaSourceId)->toBe('wf:administrative_precinct:uvea')
        ->and((string) $byCode->get('98610')->areaSourceId)->toBe('wf:administrative_precinct:alo')
        ->and((string) $byCode->get('98620')->areaSourceId)->toBe('wf:administrative_precinct:sigave');
});
