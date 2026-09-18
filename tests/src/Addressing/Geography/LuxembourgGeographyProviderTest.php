<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Luxembourger states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LU')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CA',
        'name' => 'Capellen (legacy)',
        'label' => 'Capellen (legacy)',
    ]);

    app(LuxembourgGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Capellen')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Luxembourger state code to its area', function (): void {
    $mappings = app(LuxembourgGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['CA', 'CL', 'DI', 'EC', 'ES', 'G', 'L', 'ME', 'RD', 'RM', 'VD', 'WI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LuxembourgGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('canton')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Luxembourger tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LU');
    $country = AddressCountry::query()->where('iso2', 'LU')->firstOrFail();

    expect($result['seeded'])->toContain('LU')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'canton')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
