<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 14 regions with 332 municipalities under region parents', function (): void {
    $areas = app(GreeceGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(346)
        ->and($l1)->toHaveCount(14)
        ->and($l2)->toHaveCount(332)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('gr:administrative_region:attica')->code)->toBe('I')
        ->and($byId->get('gr:administrative_region:crete')->code)->toBe('M')
        ->and($byId->get('gr:administrative_region:mount-athos')->code)->toBe('69')
        ->and($byId->get('gr:municipality:irakleio')->parentSourceId)->toBe('gr:administrative_region:attica')
        ->and($byId->get('gr:municipality:heraklion')->parentSourceId)->toBe('gr:administrative_region:crete');
});

it('pins the B18-verified 974 codes with the 3 moved links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GR', $dir . '/greece-postal-codes.csv', $dir . '/greece-postal-code-areas.csv', 'aiarmada.addressing.greece');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(974);

    foreach (['14121', '14122'] as $code) {
        $legs = $byCode->get($code);
        expect($legs)->toHaveCount(1)
            ->and($legs->first()->areaSourceId)->toBe('gr:municipality:irakleio')
            ->and($legs->first()->isPrimary)->toBeTrue();
    }

    expect($byCode->get('49083')->first()->areaSourceId)->toBe('gr:municipality:central-corfu-and-diapontia-islands')
        ->and($byCode->get('10431')->first()->areaSourceId)->toBe('gr:municipality:athens');
});
