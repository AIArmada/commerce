<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 18 French states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'FR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'IDF',
        'name' => 'Paris Region',
        'label' => 'Paris Region',
    ]);

    app(FranceGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Île-de-France')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18);
});

it('maps every French state code to its area', function (): void {
    $mappings = app(FranceGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(18)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['20R', '971', '972', '973', '974', '976', 'ARA', 'BFC', 'BRE', 'CVL', 'GES', 'HDF', 'IDF', 'NAQ', 'NOR', 'OCC', 'PAC', 'PDL']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FranceGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the French tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('FR');
    $country = AddressCountry::query()->where('iso2', 'FR')->firstOrFail();

    expect($result['seeded'])->toContain('FR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});
