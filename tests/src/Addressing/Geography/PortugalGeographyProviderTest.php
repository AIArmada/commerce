<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 20 districts/regions with 308 municipalities under L1 parents', function (): void {
    $areas = app(PortugalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(328)
        ->and($l1)->toHaveCount(20)
        ->and($l2)->toHaveCount(308)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('pt:district:aveiro')->code)->toBe('01')
        ->and($byId->get('pt:district:faro')->code)->toBe('08')
        ->and($byId->get('pt:district:porto')->code)->toBe('13')
        ->and($byId->get('pt:district:viseu')->code)->toBe('18')
        ->and($byId->get('pt:autonomous_region:acores')->code)->toBe('20')
        ->and($byId->get('pt:autonomous_region:madeira')->code)->toBe('30');
});

it('spells Lisboa per the B18 pass (district + municipality)', function (): void {
    $areas = app(PortugalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('pt:district:lisbon')->name)->toBe('Lisboa')
        ->and($byId->get('pt:district:lisbon')->code)->toBe('11')
        ->and($byId->get('pt:municipality:lisbon')->name)->toBe('Lisboa')
        ->and($byId->get('pt:municipality:lisbon')->parentSourceId)->toBe('pt:district:lisbon')
        ->and($areas->pluck('name')->contains('Lisbon'))->toBeFalse();
});

it('pins 197,772 CP7 codes with 1:1 primary links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PT', $dir . '/portugal-postal-codes.csv', $dir . '/portugal-postal-code-areas.csv', 'aiarmada.addressing.portugal');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(197772)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(197772)
        ->and($postcodes->every(fn ($p) => (bool) preg_match('/^\d{4}-\d{3}$/', $p->code)))->toBeTrue()
        ->and($postcodes->every(fn ($p) => $p->isPrimary))->toBeTrue();

    $byCode = $postcodes->groupBy->code;

    expect($byCode->get('1000-001')->first()->areaSourceId)->toBe('pt:municipality:lisbon');
});
