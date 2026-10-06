<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Lebanon tree of 9 governorates and 25 cazas', function (): void {
    $areas = app(LebanonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(9)
        ->and($areas->where('type', 'caza'))->toHaveCount(25)
        ->and($areas->where('parentSourceId', 'lb:governorate:akkar'))->toHaveCount(1)
        ->and($areas->where('parentSourceId', 'lb:governorate:baalbek-hermel'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'lb:governorate:beirut'))->toHaveCount(0)
        ->and($areas->where('parentSourceId', 'lb:governorate:beqaa'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'lb:governorate:keserwan-jbeil'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'lb:governorate:mount-lebanon'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'lb:governorate:nabatieh'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'lb:governorate:north'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'lb:governorate:south'))->toHaveCount(3);

    // Districts-of-Lebanon oracle: Beirut is childless, Keserwan-Jbeil
    // holds Byblos + Keserwan, Miniyeh-Danniyeh sits under North.
    expect($byId->get('lb:caza:byblos')->parentSourceId)->toBe('lb:governorate:keserwan-jbeil')
        ->and($byId->get('lb:caza:keserwan')->parentSourceId)->toBe('lb:governorate:keserwan-jbeil')
        ->and($byId->get('lb:caza:miniyeh-danniyeh')->parentSourceId)->toBe('lb:governorate:north')
        ->and($byId->get('lb:caza:hermel')->parentSourceId)->toBe('lb:governorate:baalbek-hermel')
        ->and($byId->get('lb:governorate:keserwan-jbeil')->code)->toBe('KJ');
});

it('bundles the verified 688-code overlay with exact primaries and duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LB', $dir . '/lebanon-postal-codes.csv', $dir . '/lebanon-postal-code-areas.csv', 'aiarmada.addressing.lebanon');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(701)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(688)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(688)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code) || (string) $row->code === '1107-2090'))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Retargeted primaries: 56ok directory + fresh Photon + wiki oracle +
    // GeoNames governorate splits all agree against the old single-point vote.
    expect($primary('1835'))->toBe('lb:caza:zahle')
        ->and($primary('1855'))->toBe('lb:caza:rashaya')
        ->and($primary('3018'))->toBe('lb:caza:miniyeh-danniyeh')
        ->and($primary('3514'))->toBe('lb:caza:miniyeh-danniyeh')
        ->and($primary('3769'))->toBe('lb:caza:bsharri')
        ->and($primary('4215'))->toBe('lb:caza:batroun')
        ->and($primary('4362'))->toBe('lb:caza:byblos')
        ->and($primary('4384'))->toBe('lb:caza:byblos')
        ->and($primary('5649'))->toBe('lb:caza:chouf')
        ->and($primary('6642'))->toBe('lb:caza:jezzine')
        ->and($primary('6710'))->toBe('lb:caza:jezzine')
        ->and($primary('6851'))->toBe('lb:caza:tyre')
        ->and($primary('6875'))->toBe('lb:caza:tyre')
        ->and($primary('6893'))->toBe('lb:caza:tyre')
        ->and($primary('7121'))->toBe('lb:caza:nabatieh')
        ->and($primary('7150'))->toBe('lb:caza:jezzine')
        ->and($primary('7192'))->toBe('lb:caza:nabatieh');

    // Hermel fill: the caza capital plus four hamlet codes, all four
    // signals unanimous (Mapanet + Photon + 56ok + GeoNames adm1=11).
    expect($primary('8123'))->toBe('lb:caza:hermel')
        ->and($primary('8128'))->toBe('lb:caza:hermel')
        ->and($primary('8151'))->toBe('lb:caza:hermel')
        ->and($primary('8173'))->toBe('lb:caza:hermel')
        ->and($primary('8242'))->toBe('lb:caza:hermel');

    // Dual links: exact primary + secondaries for shared codes.
    $legs = static fn (string $code): array => $byCode->get($code)
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();
    expect($legs('3914'))->toBe(['lb:caza:bsharri' => true, 'lb:caza:koura' => false])
        ->and($legs('5428'))->toBe(['lb:caza:aley' => true, 'lb:caza:chouf' => false])
        ->and($legs('8119'))->toBe(['lb:caza:baalbek' => true, 'lb:caza:hermel' => false])
        ->and($legs('8226'))->toBe(['lb:caza:baalbek' => true, 'lb:caza:western-beqaa' => false, 'lb:caza:zahle' => false]);

    // Hyphen keep: Mapanet-published Hazmiyeh code, wiki Baabda.
    expect($primary('1107-2090'))->toBe('lb:caza:baabda');

    // Beirut links at L1 (no caza); Tripoli city is codeless in every source.
    expect($primary('1107'))->toBe('lb:governorate:beirut')
        ->and($primary('2050'))->toBe('lb:governorate:beirut')
        ->and($postcodes->where('areaSourceId', 'lb:caza:tripoli'))->toHaveCount(0);
});
