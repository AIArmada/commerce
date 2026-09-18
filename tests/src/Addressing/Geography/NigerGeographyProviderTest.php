<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 8 Nigerien states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'NE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '1',
        'name' => 'Agadez (legacy)',
        'label' => 'Agadez (legacy)',
    ]);

    app(NigerGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Agadez')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8);
});

it('maps every Nigerien state code to its area', function (): void {
    $mappings = app(NigerGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(8)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['1', '2', '3', '4', '8', '5', '6', '7']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NigerGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Nigerien tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('NE');
    $country = AddressCountry::query()->where('iso2', 'NE')->firstOrFail();

    expect($result['seeded'])->toContain('NE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_community')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});
