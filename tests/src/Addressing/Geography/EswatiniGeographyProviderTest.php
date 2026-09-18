<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 4 Eswatini states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'HH',
        'name' => 'Hhohho (legacy)',
        'label' => 'Hhohho (legacy)',
    ]);

    app(EswatiniGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Hhohho')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4);
});

it('maps every Eswatini state code to its area', function (): void {
    $mappings = app(EswatiniGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(4)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['HH', 'LU', 'MA', 'SH']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EswatiniGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Eswatini tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SZ');
    $country = AddressCountry::query()->where('iso2', 'SZ')->firstOrFail();

    expect($result['seeded'])->toContain('SZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});
