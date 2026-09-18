<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 5 Danish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'DK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '82',
        'name' => 'Central Denmark (legacy)',
        'label' => 'Central Denmark (legacy)',
    ]);

    app(DenmarkGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central Denmark')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5);
});

it('maps every Danish state code to its area', function (): void {
    $mappings = app(DenmarkGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(5)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['82', '84', '81', '83', '85']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(DenmarkGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Danish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('DK');
    $country = AddressCountry::query()->where('iso2', 'DK')->firstOrFail();

    expect($result['seeded'])->toContain('DK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});
