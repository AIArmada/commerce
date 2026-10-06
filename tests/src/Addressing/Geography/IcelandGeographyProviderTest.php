<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 8 regions with 61 municipalities', function (): void {
    $areas = app(IcelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($areas->where('type', 'region'))->toHaveCount(8)
        ->and($l2)->toHaveCount(61)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('parentSourceId', 'is:region:capital'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'is:region:southern-peninsula'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'is:region:western'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'is:region:westfjords'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'is:region:northwestern'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'is:region:northeastern'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'is:region:eastern'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'is:region:southern'))->toHaveCount(15);
});

it('pins the 174-code overlay with 4 neighbour-served duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IS', $dir . '/iceland-postal-codes.csv', $dir . '/iceland-postal-code-areas.csv', 'aiarmada.addressing.iceland');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy('code');

    expect($postcodes)->toHaveCount(178)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(174)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(174);

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;
    $secondary = static fn (string $code): ?string => ($row = $byCode->get($code)->firstWhere('isPrimary', false)) ? (string) $row->areaSourceId : null;

    // Spot primaries across regions.
    expect($primary('101'))->toBe('is:municipality:reykjavik')
        ->and($primary('200'))->toBe('is:municipality:kopavogur')
        ->and($primary('300'))->toBe('is:municipality:akranes')
        ->and($primary('400'))->toBe('is:municipality:isafjorur')
        ->and($primary('600'))->toBe('is:municipality:akureyri')
        ->and($primary('700'))->toBe('is:municipality:mulaing')
        ->and($primary('800'))->toBe('is:municipality:arborg')
        ->and($primary('900'))->toBe('is:municipality:vestmannaeyjar');

    // The 4 duals: 276 explicit + 3 neighbour-served (no register code
    // names Tjörnes, Fljótsdalshreppur, or Ásahreppur).
    expect($secondary('276'))->toBe('is:municipality:hvalfjararsveit')
        ->and($primary('276'))->toBe('is:municipality:kjosarhreppur')
        ->and($secondary('641'))->toBe('is:municipality:tjorneshreppur')
        ->and($secondary('701'))->toBe('is:municipality:fljotsdalshreppur')
        ->and($secondary('851'))->toBe('is:municipality:asahreppur');

    // Documented exclusions stay out (18 box + 150/155 + 512).
    expect($byCode->has('512'))->toBeFalse()
        ->and($byCode->has('150'))->toBeFalse()
        ->and($byCode->has('202'))->toBeFalse();
});
