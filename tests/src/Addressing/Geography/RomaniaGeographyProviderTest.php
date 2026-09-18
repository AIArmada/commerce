<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 42 Romanian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'RO')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AB',
        'name' => 'Alba (legacy)',
        'label' => 'Alba (legacy)',
    ]);

    app(RomaniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Alba')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(42);
});

it('maps every Romanian state code to its area', function (): void {
    $mappings = app(RomaniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(42)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AB', 'AR', 'AG', 'BC', 'BH', 'BN', 'BT', 'BR', 'BV', 'B', 'BZ', 'CL', 'CS', 'CJ', 'CT', 'CV', 'DB', 'DJ', 'GL', 'GR', 'GJ', 'HR', 'HD', 'IL', 'IS', 'IF', 'MM', 'MH', 'MS', 'NT', 'OT', 'PH', 'SJ', 'SM', 'SB', 'SV', 'TR', 'TM', 'TL', 'VL', 'VS', 'VN']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RomaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Romanian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('RO');
    $country = AddressCountry::query()->where('iso2', 'RO')->firstOrFail();

    expect($result['seeded'])->toContain('RO')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(41)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(42);
});
