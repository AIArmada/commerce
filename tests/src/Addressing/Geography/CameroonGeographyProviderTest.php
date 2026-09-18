<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 10 Cameroonian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CE',
        'name' => 'Center',
        'label' => 'Center',
    ]);

    app(CameroonGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Centre')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10);
});

it('maps every Cameroonian state code to its area', function (): void {
    $mappings = app(CameroonGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(10)
        ->and(array_keys($mappings))->toBe(['AD', 'CE', 'EN', 'ES', 'LT', 'NO', 'NW', 'OU', 'SU', 'SW']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CameroonGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cameroonian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CM');
    $country = AddressCountry::query()->where('iso2', 'CM')->firstOrFail();

    expect($result['seeded'])->toContain('CM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});
