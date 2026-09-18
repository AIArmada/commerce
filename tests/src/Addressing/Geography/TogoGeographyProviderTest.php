<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 5 Togolese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'C',
        'name' => 'Centrale (legacy)',
        'label' => 'Centrale (legacy)',
    ]);

    app(TogoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Centrale')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5);
});

it('maps every Togolese state code to its area', function (): void {
    $mappings = app(TogoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(5)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['C', 'K', 'M', 'P', 'S']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TogoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Togolese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TG');
    $country = AddressCountry::query()->where('iso2', 'TG')->firstOrFail();

    expect($result['seeded'])->toContain('TG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});
