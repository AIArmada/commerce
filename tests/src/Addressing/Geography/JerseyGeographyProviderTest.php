<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Jersey\JerseyGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Jersey tree of 12 parishes and 56 subdivisions', function (): void {
    $areas = app(JerseyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // 12 parishes (no ISO 3166-2:JE codes; St spellings per the
    // JE/GG in-repo convention) + 56 vingtaines/cantons/
    // cueillettes exact vs the WP master table (census-sourced).
    expect($areas->where('level', 1))->toHaveCount(12)
        ->and($areas->where('level', 2))->toHaveCount(56)
        ->and($byId->get('je:parish:grouville')->code)->toBe('01')
        ->and($byId->get('je:parish:st-helier')->code)->toBe('04')
        ->and($byId->get('je:parish:trinity')->code)->toBe('12')
        ->and($areas->where('parentSourceId', 'je:parish:st-helier'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'je:parish:st-ouen'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'je:parish:st-mary'))->toHaveCount(2);
});

it('bundles the JE2/JE3 districts with exact parish legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('JE', $dir . '/jersey-postal-codes.csv', $dir . '/jersey-postal-code-areas.csv', 'aiarmada.addressing.jersey');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(12);

    $byCode = $postcodes->groupBy->code;

    // JE postcode-area table, exact. JE1 large-users + JE4
    // PO-boxes + JE5 bespoke are non-geographic and excluded.
    // JE3 primary Grouville = lowest parish code among the 9
    // single-sector legs (stability keep).
    expect($byCode->get('JE2'))->toHaveCount(3)
        ->and($byCode->get('JE3'))->toHaveCount(9);

    $legs = static fn (string $code): array => $byCode->get($code)
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();

    expect($legs('JE2'))->toBe(['je:parish:st-helier' => true, 'je:parish:st-clement' => false, 'je:parish:st-saviour' => false])
        ->and($legs('JE3')['je:parish:grouville'])->toBeTrue()
        ->and($legs('JE3')['je:parish:st-lawrence'])->toBeFalse();
});
