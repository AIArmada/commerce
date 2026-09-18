<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 26 Congolese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'KN',
        'name' => 'Leo',
        'label' => 'Leo',
    ]);

    app(DemocraticRepublicOfCongoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Kinshasa')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(26);
});

it('maps every Congolese state code to its area', function (): void {
    $mappings = app(DemocraticRepublicOfCongoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(26)
        ->and(array_keys($mappings))->toBe(['BC', 'BU', 'EQ', 'HK', 'HL', 'HU', 'IT', 'KC', 'KE', 'KG', 'KL', 'KN', 'KS', 'LO', 'LU', 'MA', 'MN', 'MO', 'NK', 'NU', 'SA', 'SK', 'SU', 'TA', 'TO', 'TU']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(DemocraticRepublicOfCongoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Congolese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CD');
    $country = AddressCountry::query()->where('iso2', 'CD')->firstOrFail();

    expect($result['seeded'])->toContain('CD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(26)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(26);
});
