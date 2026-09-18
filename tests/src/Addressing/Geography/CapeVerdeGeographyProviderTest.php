<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 24 Cape Verdean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CV')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'B',
        'name' => 'Barlavento Islands (legacy)',
        'label' => 'Barlavento Islands (legacy)',
    ]);

    app(CapeVerdeGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Barlavento Islands')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(24);
});

it('maps every Cape Verdean state code to its area', function (): void {
    $mappings = app(CapeVerdeGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(24)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['B', 'BV', 'BR', 'MA', 'MO', 'PA', 'PN', 'PR', 'RB', 'RG', 'RS', 'SL', 'CA', 'CF', 'CR', 'SD', 'SF', 'SO', 'SM', 'SS', 'SV', 'S', 'TA', 'TS']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CapeVerdeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cape Verdean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CV');
    $country = AddressCountry::query()->where('iso2', 'CV')->firstOrFail();

    expect($result['seeded'])->toContain('CV')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'geographical_region')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(24);
});
