<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 36 states and UTs with 786 districts under L1 parents', function (): void {
    $areas = app(IndiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(822)
        ->and($l1)->toHaveCount(36)
        ->and($l2)->toHaveCount(786)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    // B20: the 5 notified Ladakh districts (gazette 27 Apr 2026) are HELD OUT:
    // no LGD codes exist yet and invented ids break the LGD-keying policy.
    expect($l2->where('parentSourceId', 'in:union_territory:ladakh'))->toHaveCount(2)
        ->and($byId->has('in:district:797'))->toBeFalse()
        ->and($areas->pluck('name'))->not->toContain('Nubra', 'Sham', 'Changthang', 'Zanskar', 'Drass');
});

it('pins the B20 postal pass: 195 re-legged pins plus 83 added pins', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IN', $dir . '/india-postal-codes.csv', $dir . '/india-postal-code-areas.csv', 'aiarmada.addressing.india');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(19238)
        ->and($postcodes)->toHaveCount(19488);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // L1 fixes: AP/NTR + TN restructure.
    expect($primary('521178'))->toBe('in:district:749')
        ->and($primary('605101'))->toBe('in:district:596')
        ->and($primary('605106'))->toBe('in:district:600')
        ->and($byCode->get('605106'))->toHaveCount(3)
        // District batches: Bengaluru, Godavari, Nagaon, Sambhal tri-border.
        ->and($primary('560001'))->toBe('in:district:525')
        ->and($primary('534001'))->toBe('in:district:748')
        ->and($primary('782002'))->toBe('in:district:297')
        ->and($primary('244102'))->toBe('in:district:659')
        ->and($byCode->get('244102'))->toHaveCount(3)
        // Missing pins: Karimnagar block + singles.
        ->and($primary('505001'))->toBe('in:district:508')
        ->and($primary('505101'))->toBe('in:district:686')
        ->and($primary('605502'))->toBe('in:district:600')
        ->and($primary('110001'))->toBe('in:district:79');
});
