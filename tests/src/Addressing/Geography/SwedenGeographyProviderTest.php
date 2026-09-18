<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 21 Swedish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'K',
        'name' => 'Blekinge (legacy)',
        'label' => 'Blekinge (legacy)',
    ]);

    app(SwedenGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Blekinge')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(21);
});

it('maps every Swedish state code to its area', function (): void {
    $mappings = app(SwedenGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(21)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['K', 'W', 'X', 'I', 'N', 'Z', 'F', 'H', 'G', 'BD', 'T', 'E', 'M', 'D', 'AB', 'C', 'S', 'AC', 'Y', 'U', 'O']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SwedenGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Swedish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SE');
    $country = AddressCountry::query()->where('iso2', 'SE')->firstOrFail();

    expect($result['seeded'])->toContain('SE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(21)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(21);
});
