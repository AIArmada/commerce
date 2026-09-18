<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Ethiopian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ET')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'SW',
        'name' => 'South West',
        'label' => 'South West',
    ]);

    State::query()->create([
        'country_id' => $country->id,
        'code' => 'SN',
        'name' => 'Southern Nations, Nationalities, and Peoples',
        'label' => 'Southern Nations, Nationalities, and Peoples',
    ]);

    app(EthiopiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Southwest Ethiopia')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'SN')->exists())->toBeFalse();
});

it('maps every Ethiopian state code to its area', function (): void {
    $mappings = app(EthiopiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_keys($mappings))->toBe(['AA', 'AF', 'AM', 'BE', 'CE', 'DD', 'GA', 'HA', 'OR', 'SE', 'SI', 'SO', 'SW', 'TI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EthiopiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ethiopian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ET');
    $country = AddressCountry::query()->where('iso2', 'ET')->firstOrFail();

    expect($result['seeded'])->toContain('ET')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
