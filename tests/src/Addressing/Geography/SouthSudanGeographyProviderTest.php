<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 10 South Sudanese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SS')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'EC',
        'name' => 'Central Equatoria (legacy)',
        'label' => 'Central Equatoria (legacy)',
    ]);

    app(SouthSudanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central Equatoria')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10);
});

it('maps every South Sudanese state code to its area', function (): void {
    $mappings = app(SouthSudanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(10)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['EC', 'EE', 'JG', 'LK', 'BN', 'UY', 'NU', 'WR', 'BW', 'EW']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SouthSudanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the South Sudanese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SS');
    $country = AddressCountry::query()->where('iso2', 'SS')->firstOrFail();

    expect($result['seeded'])->toContain('SS')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});
