<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Eritrean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ER')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AN',
        'name' => 'Anseba (legacy)',
        'label' => 'Anseba (legacy)',
    ]);

    app(EritreaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Anseba')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Eritrean state code to its area', function (): void {
    $mappings = app(EritreaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AN', 'DU', 'GB', 'MA', 'SK', 'DK']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EritreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Eritrean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ER');
    $country = AddressCountry::query()->where('iso2', 'ER')->firstOrFail();

    expect($result['seeded'])->toContain('ER')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
