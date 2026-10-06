<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('types the seven city municipalities with miestas names', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $cities = $areas->where('type', 'city_municipality')->keyBy->code;

    expect($cities)->toHaveCount(7)
        ->and($cities->get('02')->name)->toBe('Alytaus miestas')
        ->and($cities->get('15')->name)->toBe('Kauno miestas')
        ->and($cities->get('20')->name)->toBe('Klaipėdos miestas')
        ->and($cities->get('31')->name)->toBe('Palangos miestas')
        ->and($cities->get('32')->name)->toBe('Panevėžio miestas')
        ->and($cities->get('43')->name)->toBe('Šiaulių miestas')
        ->and($cities->get('57')->name)->toBe('Vilniaus miestas');

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('lt:district_municipality:klaipeda')->code)->toBe('21')
        ->and($byId->get('lt:district_municipality:panevezys')->code)->toBe('33');
});

it('ships 60 municipalities under counties with parent links', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['district_municipality', 'municipality', 'city_municipality']);

    expect($l2)->toHaveCount(60)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('lt:district_municipality:alytus-03')->parentSourceId)->toBe('lt:county:alytus')
        ->and($byId->get('lt:municipality:marijampole')->type)->toBe('municipality')
        ->and($byId->get('lt:municipality:marijampole')->parentSourceId)->toBe('lt:county:marijampole');
});

it('seeds consistently and resolves every municipal type through the municipality role', function (): void {
    expect($this->seedProviderConsistently(LithuaniaGeographyProvider::class))->toBe([]);

    expect(AddressAreaRole::query()->where('role', 'municipality')->count())->toBe(60)
        ->and(AddressAreaRole::query()->whereIn('role', ['district_municipality', 'city_municipality'])->exists())->toBeFalse();

    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('LT', 'municipality'))->not->toBeNull();

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Vilniaus miestas', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Vilniaus miestas')
        ->and($search->execute(query: 'Akmenė', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Akmenė')
        ->and($search->execute(query: 'Marijampolė', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Marijampolė');
});

// B10 revisit 2026-10-04 (verify-only): tree + overlay pins below.

it('pins the verified Lithuania tree of 10 counties and 60 municipalities', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(70)
        ->and($areas->where('type', 'county'))->toHaveCount(10)
        ->and($areas->where('type', 'district_municipality'))->toHaveCount(43)
        ->and($areas->where('type', 'municipality'))->toHaveCount(10)
        ->and($areas->where('type', 'city_municipality'))->toHaveCount(7)
        ->and($areas->where('level', 1))->toHaveCount(10)
        ->and($areas->where('level', 2))->toHaveCount(60);

    // ISO 3166-2:LT county codes.
    expect($areas->where('level', 1)->pluck('code')->sort()->values()->all())->toBe(
        ['AL', 'KL', 'KU', 'MR', 'PN', 'SA', 'TA', 'TE', 'UT', 'VL']
    );

    // Municipal statistical codes 01-60 complete.
    expect($areas->where('level', 2)->pluck('code')->sort()->values()->all())->toBe(
        array_map(static fn (int $i): string => sprintf('%02d', $i), range(1, 60))
    );

    // Per-county L2 membership: 5/8/7/5/6/7/4/4/6/8.
    $byParent = $areas->where('level', 2)->countBy('parentSourceId')->sortKeys();

    expect($byParent->all())->toBe([
        'lt:county:alytus' => 5,
        'lt:county:kaunas' => 8,
        'lt:county:klaipeda' => 7,
        'lt:county:marijampole' => 5,
        'lt:county:panevezys' => 6,
        'lt:county:siauliai' => 7,
        'lt:county:taurage' => 4,
        'lt:county:telsiai' => 4,
        'lt:county:utena' => 6,
        'lt:county:vilnius' => 8,
    ]);

    // Marijampolė stays a plain municipality (code 25); Kazlų Rūda keeps
    // the nominative name (the ISO-page display genitive is its typo).
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('lt:municipality:marijampole')->code)->toBe('25')
        ->and($byId->get('lt:municipality:kazlu-ruda')->name)->toBe('Kazlų Rūda');
});

it('bundles the 2023 GeoNames LT codes as 2068 municipality links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LT', $dir . '/lithuania-postal-codes.csv', $dir . '/lithuania-postal-code-areas.csv', 'aiarmada.addressing.lithuania');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2068)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2023)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(45)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2023)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->min())->toBe('00001')
        ->and($postcodes->pluck('code')->max())->toBe('99069');

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Every code carries exactly one primary.
    expect($byCode->every(static fn ($rows): bool => $rows->where('isPrimary', true)->count() === 1))->toBeTrue();

    // Exactly the 45 same-county GeoNames splits are dual-linked.
    expect($byCode->filter(static fn ($rows): bool => $rows->count() > 1))->toHaveCount(45);

    // Cluster pins.
    expect($primary('01001'))->toBe('lt:city_municipality:vilniaus-miestas')
        ->and($primary('00001'))->toBe('lt:city_municipality:palangos-miestas')
        ->and($primary('99069'))->toBe('lt:district_municipality:silute')
        ->and($primary('62001'))->toBe('lt:district_municipality:alytus-03')
        ->and($primary('44001'))->toBe('lt:city_municipality:kauno-miestas') // 2:2 tie, city kept
        ->and($primary('69068'))->toBe('lt:municipality:kazlu-ruda')
        ->and($primary('72028'))->toBe('lt:municipality:pagegiai');

    // Dual-pair secondaries.
    $secondary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId;

    expect($secondary('00001'))->toBe('lt:district_municipality:klaipeda')
        ->and($secondary('62001'))->toBe('lt:city_municipality:alytaus-miestas')
        ->and($secondary('69068'))->toBe('lt:municipality:marijampole')
        ->and($secondary('91001'))->toBe('lt:city_municipality:klaipedos-miestas');

    // The 3 dropped cross-county strays stay single-linked to the majority.
    expect($byCode->get('81001')->count())->toBe(1)
        ->and($primary('81001'))->toBe('lt:district_municipality:siauliai-44')
        ->and($byCode->get('96001')->count())->toBe(1)
        ->and($primary('96001'))->toBe('lt:district_municipality:klaipeda')
        ->and($byCode->get('96047')->count())->toBe(1)
        ->and($primary('96047'))->toBe('lt:district_municipality:klaipeda');

    // Per-county primary counts (Vilnius county holds the street-level city block).
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $countyOf = fn (string $sid): string => (string) $areas->get($sid)->parentSourceId;
    $cc = $postcodes->where('isPrimary', true)->countBy(fn ($row): string => $countyOf((string) $row->areaSourceId));

    expect($cc->sortKeys()->all())->toBe([
        'lt:county:alytus' => 51,
        'lt:county:kaunas' => 102,
        'lt:county:klaipeda' => 52,
        'lt:county:marijampole' => 59,
        'lt:county:panevezys' => 63,
        'lt:county:siauliai' => 71,
        'lt:county:taurage' => 51,
        'lt:county:telsiai' => 41,
        'lt:county:utena' => 74,
        'lt:county:vilnius' => 1459,
    ]);

    // Per-municipality primaries: largest, district block, singletons.
    $mc = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($mc)->toHaveCount(60)
        ->and($mc->get('lt:city_municipality:vilniaus-miestas'))->toBe(1349)
        ->and($mc->get('lt:district_municipality:vilnius-58'))->toBe(32)
        ->and($mc->get('lt:district_municipality:kaunas-16'))->toBe(22)
        ->and($mc->get('lt:city_municipality:kauno-miestas'))->toBe(17)
        ->and($mc->get('lt:city_municipality:alytaus-miestas'))->toBe(1)
        ->and($mc->get('lt:city_municipality:klaipedos-miestas'))->toBe(1)
        ->and($mc->get('lt:city_municipality:siauliu-miestas'))->toBe(1)
        ->and($mc->get('lt:municipality:birstonas'))->toBe(1);
});
