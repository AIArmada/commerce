<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 17 Monegasque states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MC')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'FO',
        'name' => 'Fontvieille (legacy)',
        'label' => 'Fontvieille (legacy)',
    ]);

    app(MonacoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Fontvieille')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17);
});

it('maps every Monegasque state code to its area', function (): void {
    $mappings = app(MonacoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(17)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['FO', 'JE', 'CL', 'CO', 'GA', 'SO', 'LA', 'MA', 'MO', 'MG', 'MC', 'MU', 'PH', 'SR', 'SD', 'SP', 'VR']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MonacoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('quarter')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Monegasque tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MC');
    $country = AddressCountry::query()->where('iso2', 'MC')->firstOrFail();

    expect($result['seeded'])->toContain('MC')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'quarter')->count())->toBe(17)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});
